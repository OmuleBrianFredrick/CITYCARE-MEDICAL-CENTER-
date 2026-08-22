<?php

namespace Database\Factories;

use App\Models\BillableService;
use App\Models\Charge;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Charge> */
class ChargeFactory extends Factory
{
    protected $model = Charge::class;

    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 5000, 100000);
        $quantity = fake()->randomFloat(3, 1, 3);
        $subtotal = round($unitPrice * $quantity, 2);

        return [
            'facility_id' => Facility::factory(),
            'patient_id' => Patient::factory(),
            'encounter_id' => null,
            'billable_service_id' => BillableService::factory(),
            'service_price_id' => ServicePrice::factory(),
            'created_by_id' => User::factory(),
            'voided_by_id' => null,
            'status' => Charge::STATUS_PENDING,
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'adjustment_amount' => 0,
            'total' => $subtotal,
            'currency' => 'UGX',
            'idempotency_key' => null,
            'voided_at' => null,
            'void_reason' => null,
        ];
    }

    public function invoiced(): static
    {
        return $this->state(fn () => ['status' => Charge::STATUS_INVOICED]);
    }

    public function voided(): static
    {
        return $this->state(fn () => ['status' => Charge::STATUS_VOIDED, 'voided_at' => now()]);
    }
}