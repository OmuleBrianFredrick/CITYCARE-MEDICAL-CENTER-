<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_permission_matrix_is_seeded_correctly(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $required = [
            'billing.view',
            'billing.charges.manage',
            'billing.invoices.manage',
            'billing.payments.record',
            'billing.work.manage',
            'billing.manage',
        ];

        $permissionSlugs = Permission::whereIn('slug', $required)->pluck('slug')->sort()->values()->all();
        $this->assertSame(collect($required)->sort()->values()->all(), $permissionSlugs);

        $cashierPermissions = Role::where('slug', 'cashier')->firstOrFail()
            ->permissions()
            ->pluck('slug')
            ->intersect($required)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(collect($required)->sort()->values()->all(), $cashierPermissions);

        $this->assertContains('billing.view', Role::where('slug', 'doctor')->firstOrFail()->permissions()->pluck('slug')->all());
        $this->assertContains('billing.view', Role::where('slug', 'receptionist')->firstOrFail()->permissions()->pluck('slug')->all());
        $this->assertNotContains('billing.payments.record', Role::where('slug', 'doctor')->firstOrFail()->permissions()->all());
        $this->assertNotContains('billing.payments.record', Role::where('slug', 'receptionist')->firstOrFail()->permissions()->all());
    }
}
