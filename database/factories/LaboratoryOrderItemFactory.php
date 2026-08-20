<?php

namespace Database\Factories;

use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryTest;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryOrderItemFactory extends Factory
{
    protected $model = LaboratoryOrderItem::class;

    public function definition(): array
    {
        return ['laboratory_order_id' => LaboratoryOrder::factory(), 'laboratory_test_id' => LaboratoryTest::factory(), 'status' => LaboratoryOrderItem::STATUS_ORDERED, 'notes' => fake()->sentence()];
    }
}
