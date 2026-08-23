<?php

namespace Database\Factories;

use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportRunFactory extends Factory
{
    protected $model = ReportRun::class;

    public function definition(): array
    {
        return [
            'report_definition_id' => ReportDefinition::factory(),
            'requested_by_id' => User::factory(),
            'facility_id' => null,
            'status' => ReportRun::STATUS_QUEUED,
            'period_start' => now()->subDay(),
            'period_end' => now(),
            'filters' => ['facility' => null],
            'result_metadata' => null,
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ];
    }
}
