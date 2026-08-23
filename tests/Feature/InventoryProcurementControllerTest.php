<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryProcurementService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryProcurementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_staff_can_create_view_receive_and_cancel_purchase_orders_through_http(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, $supplier, $item] = $this->context('inventory');

        $this->actingAs($staff)
            ->get(route('inventory.procurement.index'))
            ->assertOk()
            ->assertSee('Purchase orders');

        $create = $this->actingAs($staff)->post(route('inventory.procurement.store'), [
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'ordered_at' => now()->toDateString(),
            'items' => [[
                'inventory_item_id' => $item->id,
                'quantity_ordered' => 5,
                'unit_cost' => 1000,
            ]],
        ]);

        $order = \App\Models\PurchaseOrder::query()->latest('id')->firstOrFail();
        $create->assertRedirect(route('inventory.procurement.show', $order));

        $this->actingAs($staff)
            ->get(route('inventory.procurement.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($item->name);

        $order->update(['status' => 'ordered']);
        $poItem = $order->items()->firstOrFail();

        $this->actingAs($staff)
            ->post(route('inventory.procurement.receive', $order), [
                'store_id' => $store->id,
                'items' => [[
                    'purchase_order_item_id' => $poItem->id,
                    'quantity_received' => 5,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame('completed', $order->fresh()->status);

        $secondOrder = app(InventoryProcurementService::class)->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [[
                'inventory_item_id' => $item->id,
                'quantity_ordered' => 2,
                'unit_cost' => 1000,
            ]],
        ]);

        $this->actingAs($staff)
            ->post(route('inventory.procurement.cancel', $secondOrder), ['reason' => 'Vendor request cancelled.'])
            ->assertRedirect();

        $this->assertSame('cancelled', $secondOrder->fresh()->status);
    }

    public function test_staff_without_inventory_manage_cannot_create_or_receive_procurement_work(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$receptionist, $store, $supplier, $item] = $this->context('receptionist');

        $this->actingAs($receptionist)
            ->get(route('inventory.procurement.index'))
            ->assertForbidden();

        $this->actingAs($receptionist)
            ->post(route('inventory.procurement.store'), [
                'supplier_id' => $supplier->id,
                'store_id' => $store->id,
                'items' => [[
                    'inventory_item_id' => $item->id,
                    'quantity_ordered' => 1,
                    'unit_cost' => 1000,
                ]],
            ])
            ->assertForbidden();
    }

    public function test_inventory_request_validation_rejects_empty_items_and_invalid_quantities(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, $supplier, $item] = $this->context('inventory');

        $this->actingAs($staff)
            ->post(route('inventory.procurement.store'), [
                'supplier_id' => $supplier->id,
                'store_id' => $store->id,
                'items' => [],
            ])
            ->assertSessionHasErrors('items');

        $this->actingAs($staff)
            ->post(route('inventory.procurement.store'), [
                'supplier_id' => $supplier->id,
                'store_id' => $store->id,
                'items' => [[
                    'inventory_item_id' => $item->id,
                    'quantity_ordered' => 0,
                    'unit_cost' => 1000,
                ]],
            ])
            ->assertSessionHasErrors('items.0.quantity_ordered');
    }

    private function context(string $roleSlug): array
    {
        $facility = Facility::factory()->create();
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true, 'facility_id' => $facility->id]);
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id);

        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $supplier = InventorySupplier::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $item = InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);

        return [$user, $store, $supplier, $item];
    }
}
