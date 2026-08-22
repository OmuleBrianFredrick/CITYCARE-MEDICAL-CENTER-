<?php

namespace Database\Factories;

use App\Models\MedicationDispensing;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\MedicationDispensingItem> */
class MedicationDispensingItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'medication_dispensings_id' => MedicationDispensing::factory(),
            'prescription_item_id' => PrescriptionItem::factory(),
            'quantity_dispensed' => fake()->randomFloat(3, 1, 20),
            'batch_number' => strtoupper(fake()->bothify('BATCH-####??')),
            'expiry_date' => now()->addMonths(12)->toDateString(),
        ];
    }
}
