<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LaboratoryResult;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Notifications\PatientPortalEventNotification;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\DatabaseNotification;

class PatientNotificationService
{
    public function appointmentScheduled(Appointment $appointment): ?DatabaseNotification
    {
        $appointment->loadMissing('patient.user', 'department', 'servicePoint', 'provider');

        return $this->notify(
            $appointment->patient,
            "appointment:{$appointment->id}:scheduled:{$appointment->scheduled_start->timestamp}",
            'Appointment scheduled',
            sprintf(
                'Your appointment is scheduled for %s%s.',
                $appointment->scheduled_start->format('d M Y \a\t H:i'),
                $appointment->department?->name ? " with {$appointment->department->name}" : '',
            ),
            '/portal#appointments',
            $this->appointmentContext($appointment),
        );
    }

    public function appointmentCancelled(Appointment $appointment): ?DatabaseNotification
    {
        $appointment->loadMissing('patient.user');

        return $this->notify(
            $appointment->patient,
            "appointment:{$appointment->id}:cancelled",
            'Appointment cancelled',
            sprintf('Your appointment for %s has been cancelled.', $appointment->scheduled_start->format('d M Y \a\t H:i')),
            '/portal#appointments',
            $this->appointmentContext($appointment),
        );
    }

    public function appointmentReminder(Appointment $appointment, int $hours): ?DatabaseNotification
    {
        if (! $this->appointmentRemindersEnabled()) {
            return null;
        }

        $appointment->loadMissing('patient.user', 'department', 'servicePoint', 'provider');

        return $this->notify(
            $appointment->patient,
            "appointment:{$appointment->id}:reminder:{$hours}h:{$appointment->scheduled_start->timestamp}",
            'Appointment reminder',
            sprintf('Reminder: your appointment is on %s.', $appointment->scheduled_start->format('d M Y \a\t H:i')),
            '/portal#appointments',
            $this->appointmentContext($appointment) + ['reminder_window_hours' => $hours],
        );
    }

    public function laboratoryResultReady(LaboratoryResult $result): ?DatabaseNotification
    {
        $result->loadMissing('orderItem.laboratoryTest', 'orderItem.order.patient.user');
        $item = $result->orderItem;
        $order = $item?->order;

        if (! $order?->patient) {
            return null;
        }

        return $this->notify(
            $order->patient,
            "laboratory-result:{$result->id}:ready",
            'Laboratory result ready',
            sprintf('Your %s result is now available in the patient portal.', $item->laboratoryTest?->name ?? 'laboratory'),
            '/portal#laboratory-results',
            [
                'laboratory_result_id' => $result->id,
                'laboratory_order_id' => $order->id,
                'laboratory_order_item_id' => $item->id,
                'recorded_at' => $result->recorded_at?->toIso8601String(),
            ],
        );
    }

    public function invoiceIssued(Invoice $invoice): ?DatabaseNotification
    {
        $invoice->loadMissing('patient.user');

        return $this->notify(
            $invoice->patient,
            "invoice:{$invoice->id}:issued",
            'New invoice issued',
            sprintf('Invoice %s for %s %s is now available.', $invoice->invoice_number, $invoice->currency, number_format((float) $invoice->total, 2)),
            '/portal#billing',
            $this->invoiceContext($invoice),
        );
    }

    public function invoiceCancelled(Invoice $invoice): ?DatabaseNotification
    {
        $invoice->loadMissing('patient.user');

        return $this->notify(
            $invoice->patient,
            "invoice:{$invoice->id}:cancelled",
            'Invoice cancelled',
            "Invoice {$invoice->invoice_number} has been cancelled.",
            '/portal#billing',
            $this->invoiceContext($invoice),
        );
    }

    public function paymentRecorded(Payment $payment): ?DatabaseNotification
    {
        $payment->loadMissing('invoice.patient.user');
        $invoice = $payment->invoice;

        return $this->notify(
            $invoice->patient,
            "payment:{$payment->id}:recorded",
            'Payment received',
            sprintf('Payment receipt %s for %s %s is now available.', $payment->receipt_number, $payment->currency, number_format((float) $payment->amount, 2)),
            '/portal#billing',
            $this->paymentContext($payment),
        );
    }

    public function paymentReversed(Payment $payment): ?DatabaseNotification
    {
        $payment->loadMissing('invoice.patient.user');
        $action = $payment->isRefunded() ? 'refunded' : 'voided';

        return $this->notify(
            $payment->invoice->patient,
            "payment:{$payment->id}:{$action}",
            $payment->isRefunded() ? 'Payment refunded' : 'Payment voided',
            sprintf('Payment receipt %s has been %s. Your invoice balance has been updated.', $payment->receipt_number, $action),
            '/portal#billing',
            $this->paymentContext($payment),
        );
    }

    public function notify(
        Patient $patient,
        string $eventKey,
        string $title,
        string $message,
        string $url,
        array $context = [],
    ): ?DatabaseNotification {
        $patient->loadMissing('user');
        $user = $patient->user;

        if (! $user?->isPatient()) {
            return null;
        }

        $notificationId = $this->notificationId($user->id, $eventKey);
        $existing = $user->notifications()->whereKey($notificationId)->first();
        if ($existing) {
            return $existing;
        }

        try {
            $user->notify(new PatientPortalEventNotification(
                $notificationId,
                $eventKey,
                $title,
                $message,
                $url,
                $context,
            ));
        } catch (QueryException $exception) {
            $existing = $user->notifications()->whereKey($notificationId)->first();
            if (! $existing) {
                throw $exception;
            }

            return $existing;
        }

        return $user->notifications()->whereKey($notificationId)->first();
    }

    public function appointmentRemindersEnabled(): bool
    {
        $setting = SystemSetting::query()->where('key', 'appointments.reminders.enabled')->first();

        return $setting === null || (bool) $setting->typedValue();
    }

    private function notificationId(int $userId, string $eventKey): string
    {
        $hex = substr(hash('sha256', "citycare-patient-notification:{$userId}:{$eventKey}"), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private function appointmentContext(Appointment $appointment): array
    {
        return [
            'appointment_id' => $appointment->id,
            'appointment_number' => $appointment->appointment_number,
            'scheduled_start' => $appointment->scheduled_start->toIso8601String(),
            'scheduled_end' => $appointment->scheduled_end->toIso8601String(),
            'status' => $appointment->status,
        ];
    }

    private function invoiceContext(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'total' => (string) $invoice->total,
            'balance_due' => (string) $invoice->balance_due,
        ];
    }

    private function paymentContext(Payment $payment): array
    {
        return [
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'receipt_number' => $payment->receipt_number,
            'status' => $payment->status,
            'currency' => $payment->currency,
            'amount' => (string) $payment->amount,
        ];
    }
}
