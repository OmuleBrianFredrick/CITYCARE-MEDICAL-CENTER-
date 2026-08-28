<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'received_by_id' => User::factory(),
            'voided_by_id' => null,
            'receipt_number' => fake()->unique()->bothify('RCT-########'),
            'method' => Payment::METHOD_CASH,
            'status' => Payment::STATUS_COMPLETED,
            'amount' => fake()->randomFloat(2, 1000, 250000),
            'currency' => 'UGX',
            'transaction_reference' => fake()->optional()->bothify('TXN-############'),
            'notes' => null,
            'paid_at' => now(),
            'voided_at' => null,
            'void_reason' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Payment::STATUS_PENDING, 'paid_at' => null]);
    }

    public function voided(): static
    {
        return $this->state(fn () => ['status' => Payment::STATUS_VOIDED, 'voided_at' => now()]);
    }
}
