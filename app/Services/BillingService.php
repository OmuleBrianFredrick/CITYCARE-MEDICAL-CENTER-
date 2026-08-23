<?php

namespace App\Services;

use App\Models\BillableService;
use App\Models\Charge;
use App\Models\ClinicalEncounter;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function addCharge(User $staff, Patient $patient, BillableService $service, ServicePrice $price, array $data = []): Charge
    {
        $this->assertActiveStaff($staff);
        $this->assertActivePatient($patient);
        $this->assertServicePrice($patient, $service, $price);

        $quantity = (float) ($data['quantity'] ?? 1);
        $discount = (float) ($data['discount_amount'] ?? 0);
        $adjustment = (float) ($data['adjustment_amount'] ?? 0);
        $this->assertPositive($quantity, 'quantity');
        $this->assertNonNegative($discount, 'discount_amount');

        $encounter = $data['encounter'] ?? null;
        if ($encounter !== null) {
            $this->assertValidEncounter($patient, $encounter);
        }

        $subtotal = round($quantity * (float) $price->amount, 2);
        $total = round($subtotal - $discount + $adjustment, 2);
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount_amount' => 'The discount cannot exceed the charge subtotal.']);
        }
        if ($total < 0) {
            throw ValidationException::withMessages(['adjustment_amount' => 'The resulting charge total cannot be negative.']);
        }

        return DB::transaction(function () use ($staff, $patient, $service, $price, $data, $quantity, $discount, $adjustment, $subtotal, $total, $encounter) {
            $key = $data['idempotency_key'] ?? null;
            if ($key !== null) {
                $existing = Charge::query()->where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->patient_id !== $patient->id || $existing->billable_service_id !== $service->id) {
                        throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key is already associated with another charge.']);
                    }
                    return $existing;
                }
            }

            return Charge::create([
                'facility_id' => $patient->facility_id,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter?->id,
                'billable_service_id' => $service->id,
                'service_price_id' => $price->id,
                'created_by_id' => $staff->id,
                'status' => Charge::STATUS_PENDING,
                'description' => $data['description'] ?? $service->name,
                'quantity' => $quantity,
                'unit_price' => $price->amount,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'adjustment_amount' => $adjustment,
                'total' => $total,
                'currency' => $price->currency,
                'idempotency_key' => $key,
            ]);
        }, 3);
    }

    public function createInvoice(User $staff, Patient $patient, array $charges, array $data = []): Invoice
    {
        $this->assertActiveStaff($staff);
        $this->assertActivePatient($patient);
        if ($charges === []) {
            throw ValidationException::withMessages(['charges' => 'At least one charge is required.']);
        }

        return DB::transaction(function () use ($staff, $patient, $charges, $data) {
            $chargeModels = collect($charges)->map(fn ($charge) => $charge instanceof Charge ? $charge->id : $charge)->unique()->values();
            $locked = Charge::query()->whereIn('id', $chargeModels)->lockForUpdate()->get();
            if ($locked->count() !== $chargeModels->count()) {
                throw ValidationException::withMessages(['charges' => 'One or more selected charges do not exist.']);
            }

            foreach ($locked as $charge) {
                if ($charge->patient_id !== $patient->id || $charge->facility_id !== $patient->facility_id) {
                    throw ValidationException::withMessages(['charges' => 'All charges must belong to the selected patient and facility.']);
                }
                if (! $charge->isPending()) {
                    throw ValidationException::withMessages(['charges' => 'Only pending charges can be invoiced.']);
                }
                if ($charge->total < 0) {
                    throw ValidationException::withMessages(['charges' => 'A charge cannot have a negative total.']);
                }
            }

            $encounter = isset($data['encounter_id'])
                ? ClinicalEncounter::query()->findOrFail($data['encounter_id'])
                : null;
            if ($encounter !== null) {
                $this->assertValidEncounter($patient, $encounter);
            }

            $subtotal = round($locked->sum(fn ($c) => (float) $c->subtotal), 2);
            $discountTotal = round($locked->sum(fn ($c) => (float) $c->discount_amount), 2) + (float) ($data['discount_amount'] ?? 0);
            $adjustmentTotal = round($locked->sum(fn ($c) => (float) $c->adjustment_amount) + (float) ($data['adjustment_amount'] ?? 0), 2);
            if ($discountTotal < 0 || $discountTotal > $subtotal) {
                throw ValidationException::withMessages(['discount_amount' => 'The invoice discount is invalid.']);
            }
            $total = round($subtotal - $discountTotal + $adjustmentTotal, 2);
            if ($total < 0) {
                throw ValidationException::withMessages(['adjustment_amount' => 'The invoice total cannot be negative.']);
            }

            $invoice = Invoice::create([
                'facility_id' => $patient->facility_id,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter?->id ?? $locked->first()->encounter_id,
                'created_by_id' => $staff->id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'status' => Invoice::STATUS_ISSUED,
                'currency' => $data['currency'] ?? 'UGX',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'adjustment_total' => $adjustmentTotal,
                'total' => $total,
                'paid_amount' => 0,
                'balance_due' => $total,
                'notes' => $data['notes'] ?? null,
                'issued_by_id' => $staff->id,
                'issued_at' => now(),
            ]);

            foreach ($locked as $charge) {
                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'charge_id' => $charge->id,
                    'billable_service_id' => $charge->billable_service_id,
                    'service_price_id' => $charge->service_price_id,
                    'description' => $charge->description ?? $charge->billableService?->name ?? 'Billable service',
                    'quantity' => $charge->quantity,
                    'unit_price' => $charge->unit_price,
                    'line_subtotal' => $charge->subtotal,
                    'discount_amount' => $charge->discount_amount,
                    'adjustment_amount' => $charge->adjustment_amount,
                    'line_total' => $charge->total,
                    'currency' => $charge->currency,
                ]);
                $charge->update(['status' => Charge::STATUS_INVOICED]);
            }

            return $invoice->load('lineItems');
        }, 3);
    }

    public function recordPayment(User $staff, Invoice $invoice, float $amount, string $method, array $data = []): Payment
    {
        $this->assertActiveStaff($staff);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }
        if ($invoice->isCancelled() || $invoice->isPaid()) {
            throw ValidationException::withMessages(['invoice' => 'Payments cannot be recorded against a cancelled or fully paid invoice.']);
        }
        if (! in_array($method, [Payment::METHOD_CASH, Payment::METHOD_MOBILE_MONEY, Payment::METHOD_BANK_TRANSFER, Payment::METHOD_CARD, Payment::METHOD_INSURANCE, Payment::METHOD_OTHER], true)) {
            throw ValidationException::withMessages(['method' => 'The selected payment method is invalid.']);
        }

        return DB::transaction(function () use ($staff, $invoice, $amount, $method, $data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $balance = max(0, round((float) $invoice->total - (float) $invoice->paid_amount, 2));
            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the outstanding invoice balance.']);
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'received_by_id' => $staff->id,
                'receipt_number' => $this->nextReceiptNumber(),
                'method' => $method,
                'status' => Payment::STATUS_COMPLETED,
                'amount' => round($amount, 2),
                'currency' => $invoice->currency,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
            ]);

            $paid = round((float) $invoice->paid_amount + $amount, 2);
            $due = round((float) $invoice->total - $paid, 2);
            $status = $due <= 0 ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID;
            $invoice->update(['paid_amount' => $paid, 'balance_due' => max(0, $due), 'status' => $status, 'paid_at' => $status === Invoice::STATUS_PAID ? now() : null]);

            return $payment->refresh();
        }, 3);
    }

    public function cancelInvoice(User $staff, Invoice $invoice, string $reason): Invoice
    {
        $this->assertActiveStaff($staff);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A cancellation reason is required.']);
        }
        if ($invoice->isCancelled() || $invoice->isPaid() || $invoice->paid_amount > 0) {
            throw ValidationException::withMessages(['invoice' => 'Only unpaid invoices can be cancelled.']);
        }

        return DB::transaction(function () use ($staff, $invoice, $reason) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->paid_amount > 0 || $invoice->isPaid()) {
                throw ValidationException::withMessages(['invoice' => 'Only unpaid invoices can be cancelled.']);
            }
            $invoice->lineItems()->with('charge')->get()->each(function (InvoiceLineItem $item) {
                $item->charge?->update(['status' => Charge::STATUS_PENDING]);
            });
            $invoice->update(['status' => Invoice::STATUS_CANCELLED, 'cancelled_by_id' => $staff->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            return $invoice->refresh();
        }, 3);
    }

    public function refreshInvoiceTotals(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->lockForUpdate()->with('lineItems')->findOrFail($invoice->id);
            if ($invoice->isCancelled() || $invoice->isPaid()) {
                throw ValidationException::withMessages(['invoice' => 'Completed or cancelled invoices cannot be recalculated.']);
            }
            $subtotal = round($invoice->lineItems->sum(fn ($i) => (float) $i->line_subtotal), 2);
            $discount = round($invoice->lineItems->sum(fn ($i) => (float) $i->discount_amount), 2);
            $adjustment = round($invoice->lineItems->sum(fn ($i) => (float) $i->adjustment_amount), 2);
            $total = round($subtotal - $discount + $adjustment, 2);
            if ($total < 0 || $invoice->paid_amount > $total) {
                throw ValidationException::withMessages(['invoice' => 'The recalculated invoice total is invalid.']);
            }
            $due = round($total - (float) $invoice->paid_amount, 2);
            $invoice->update(['subtotal' => $subtotal, 'discount_total' => $discount, 'adjustment_total' => $adjustment, 'total' => $total, 'balance_due' => max(0, $due)]);
            return $invoice->refresh();
        }, 3);
    }

    private function assertActiveStaff(User $staff): void
    {
        if (! $staff->isStaff() || ! $staff->isActive()) {
            throw ValidationException::withMessages(['staff_id' => 'Only active staff members can perform billing operations.']);
        }
    }

    private function assertActivePatient(Patient $patient): void
    {
        if (! $patient->isActive()) {
            throw ValidationException::withMessages(['patient_id' => 'Only active patients can be billed.']);
        }
    }

    private function assertServicePrice(Patient $patient, BillableService $service, ServicePrice $price): void
    {
        $today = now()->toDateString();
        if (! $service->isActive() || $service->facility_id !== $patient->facility_id) {
            throw ValidationException::withMessages(['service_id' => 'The selected billable service is inactive or belongs to another facility.']);
        }
        if ($price->billable_service_id !== $service->id || $price->facility_id !== $patient->facility_id || ! $price->isActive() || (float) $price->amount <= 0 || $price->effective_from->toDateString() > $today || ($price->effective_to && $price->effective_to->toDateString() < $today)) {
            throw ValidationException::withMessages(['service_price_id' => 'The selected service price is invalid or not currently effective.']);
        }
    }

    private function assertValidEncounter(Patient $patient, ClinicalEncounter $encounter): void
    {
        if ($encounter->patient_id !== $patient->id || $encounter->facility_id !== $patient->facility_id || ! $encounter->isOpen()) {
            throw ValidationException::withMessages(['encounter_id' => 'The encounter is invalid, closed, cancelled, or belongs to another patient/facility.']);
        }
    }

    private function assertPositive(float $value, string $field): void
    {
        if ($value <= 0) {
            throw ValidationException::withMessages([$field => 'The value must be greater than zero.']);
        }
    }

    private function assertNonNegative(float $value, string $field): void
    {
        if ($value < 0) {
            throw ValidationException::withMessages([$field => 'The value cannot be negative.']);
        }
    }

    private function nextInvoiceNumber(): string
    {
        do { $number = 'INV-'.now()->format('Ymd').'-'.str()->upper(str()->random(6)); } while (Invoice::where('invoice_number', $number)->exists());
        return $number;
    }

    private function nextReceiptNumber(): string
    {
        do { $number = 'RCT-'.now()->format('Ymd').'-'.str()->upper(str()->random(6)); } while (Payment::where('receipt_number', $number)->exists());
        return $number;
    }
}
