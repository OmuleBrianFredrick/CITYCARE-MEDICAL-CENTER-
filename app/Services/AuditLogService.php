<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuditLogService
{
    public function record(
        ?User $actor,
        string $eventType,
        string $action,
        string $auditableType,
        int $auditableId,
        ?int $facilityId = null,
        ?array $before = null,
        ?array $after = null,
        array $context = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?Carbon $occurredAt = null,
    ): AuditEvent {
        $this->assertEventContract($eventType, $action, $auditableType, $auditableId);

        return AuditEvent::create([
            'actor_id' => $actor?->id,
            'facility_id' => $facilityId,
            'event_type' => $eventType,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'before_values' => $before,
            'after_values' => $after,
            'context' => Arr::except($context, ['password', 'password_confirmation', 'token', 'otp', 'secret']),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    public function query(array $filters = [])
    {
        $query = AuditEvent::query()->latest('occurred_at');

        if (isset($filters['actor_id'])) {
            $query->where('actor_id', (int) $filters['actor_id']);
        }

        if (isset($filters['facility_id'])) {
            $query->where('facility_id', (int) $filters['facility_id']);
        }

        if (isset($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (isset($filters['auditable_id'])) {
            $query->where('auditable_id', (int) $filters['auditable_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('occurred_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (isset($filters['date_to'])) {
            $query->where('occurred_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        return $query;
    }

    private function assertEventContract(string $eventType, string $action, string $auditableType, int $auditableId): void
    {
        if (trim($eventType) === '' || trim($action) === '' || trim($auditableType) === '' || $auditableId < 1) {
            throw ValidationException::withMessages(['audit' => 'A valid audit event contract is required.']);
        }
    }
}
