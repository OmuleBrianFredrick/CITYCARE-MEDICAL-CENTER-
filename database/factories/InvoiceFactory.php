<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'patient_id' => Patient::factory(),
            'encounter_id' => null,
            'created_by_id' => User::factory(),
            'issued_by_id' => null,
            'cancelled_by_id' => null,
            'invoice_number' => fake()->unique()->bothify('INV-########'),
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'UGX',
            'subtotal' => 0,
            'discount_total' => 0,
            'adjustment_total' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'balance_due' => 0,
            'notes' => null,
            'issued_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_CANCELLED, 'cancelled_at' => now()]);
    }
}