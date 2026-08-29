<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_event_records_actor_facility_before_and_after_values(): void
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $facility = Facility::factory()->create();

        $event = app(AuditLogService::class)->record(
            $staff,
            'billing.invoice',
            'updated',
            'App\\Models\\Invoice',
            123,
            $facility->id,
            ['status' => 'unpaid'],
            ['status' => 'paid'],
            ['ip' => '127.0.0.1', 'secret' => 'do-not-store'],
            '127.0.0.1',
            'TestAgent',
        );

        $this->assertSame($staff->id, $event->actor_id);
        $this->assertSame($facility->id, $event->facility_id);
        $this->assertSame(['status' => 'unpaid'], $event->before_values);
        $this->assertSame(['status' => 'paid'], $event->after_values);
        $this->assertSame(['ip' => '127.0.0.1'], $event->context);
        $this->assertSame('127.0.0.1', $event->ip_address);
        $this->assertSame('TestAgent', $event->user_agent);
    }

    public function test_audit_event_allows_system_actor_without_user(): void
    {
        $event = app(AuditLogService::class)->record(
            null,
            'authentication',
            'login_failed',
            'App\\Models\\User',
            1,
        );

        $this->assertNull($event->actor_id);
        $this->assertSame('login_failed', $event->action);
    }

    public function test_audit_query_supports_operational_filters(): void
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $service = app(AuditLogService::class);

        $service->record($staff, 'inventory.purchase_order', 'created', 'App\\Models\\PurchaseOrder', 10, $facility->id);
        $service->record($staff, 'inventory.purchase_order', 'cancelled', 'App\\Models\\PurchaseOrder', 10, $facility->id);
        $service->record($staff, 'pharmacy.dispensing', 'completed', 'App\\Models\\Dispensing', 11, $facility->id);

        $results = $service->query([
            'actor_id' => $staff->id,
            'facility_id' => $facility->id,
            'event_type' => 'inventory.purchase_order',
            'action' => 'cancelled',
            'auditable_type' => 'App\\Models\\PurchaseOrder',
            'auditable_id' => 10,
        ])->get();

        $this->assertCount(1, $results);
        $this->assertSame('cancelled', $results->first()->action);
    }

    public function test_invalid_audit_contract_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(AuditLogService::class)->record(
            null,
            '',
            'created',
            'App\\Models\\User',
            0,
        );
    }
}
