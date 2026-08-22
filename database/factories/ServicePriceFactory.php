<?php

namespace Database\Factories;

use App\Models\BillableService;
use App\Models\Facility;
use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServicePrice> */
class ServicePriceFactory extends Factory
{
    protected $model = ServicePrice::class;

    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'billable_service_id' => BillableService::factory(),
            'amount' => fake()->randomFloat(2, 5000, 250000),
            'currency' => 'UGX',
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }
}