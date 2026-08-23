<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'facility_id' => null,
            'event_type' => $this->faker->randomElement(['authentication', 'clinical', 'billing', 'inventory', 'administration']),
            'action' => $this->faker->randomElement(['created', 'updated', 'deleted', 'viewed', 'completed']),
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => 1,
            'before_values' => null,
            'after_values' => null,
            'context' => ['source' => 'test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'occurred_at' => now(),
        ];
    }
}
