<?php

namespace Database\Factories;

use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryResultFactory extends Factory
{
    protected $model = LaboratoryResult::class;

    public function definition(): array
    {
        return ['laboratory_order_item_id' => LaboratoryOrderItem::factory(), 'recorded_by' => User::factory()->state(['user_type' => 'staff', 'is_active' => true]), 'result_value' => fake()->sentence(), 'unit' => null, 'reference_range' => null, 'is_abnormal' => false, 'comments' => fake()->sentence(), 'recorded_at' => now()];
    }
}
