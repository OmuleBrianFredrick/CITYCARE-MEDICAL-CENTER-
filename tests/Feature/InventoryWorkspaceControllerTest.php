<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\InventoryProcurementService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_inventory_workspace_catalogue_and_actions_are_isolated_to_staff_facility(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $otherFacility = Facility::factory()->create(['is_active' => true]);
        $staff = $this->staffAt($facility, 'inventory');
        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'name' => 'Main Store', 'is_active' => true]);
        $supplier = InventorySupplier::factory()->create(['facility_id' => $facility->id, 'name' => 'Own Supplier', 'is_active' => true]);
        $item = InventoryItem::factory()->create(['facility_id' => $facility->id, 'name' => 'Own Low Item', 'reorder_level' => 5, 'is_active' => true]);
        InventoryStockBalance::factory()->create(['store_id' => $store->id, 'inventory_item_id' => $item->id, 'quantity_on_hand' => 2, 'quantity_available' => 2]);
        $order = app(InventoryProcurementService::class)->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $item->id, 'quantity_ordered' => 4, 'unit_cost' => 1000]],
        ]);

        $otherStore = InventoryStore::factory()->create(['facility_id' => $otherFacility->id, 'name' => 'Other Store', 'is_active' => true]);
        $otherSupplier = InventorySupplier::factory()->create(['facility_id' => $otherFacility->id, 'name' => 'Other Supplier', 'is_active' => true]);
        $otherItem = InventoryItem::factory()->create(['facility_id' => $otherFacility->id, 'name' => 'Other Low Item', 'reorder_level' => 10, 'is_active' => true]);
        InventoryStockBalance::factory()->create(['store_id' => $otherStore->id, 'inventory_item_id' => $otherItem->id, 'quantity_on_hand' => 1, 'quantity_available' => 1]);
        $otherOrder = PurchaseOrder::factory()->create([
            'facility_id' => $otherFacility->id,
            'supplier_id' => $otherSupplier->id,
            'store_id' => $otherStore->id,
            'order_number' => 'PO-OTHER-FACILITY',
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $otherOrder->id, 'inventory_item_id' => $otherItem->id]);

        $this->actingAs($staff)
            ->get(route('inventory.procurement.index'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($item->name)
            ->assertDontSee($otherOrder->order_number)
            ->assertDontSee($otherItem->name);

        $this->actingAs($staff)
            ->get(route('inventory.procurement.create'))
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee($item->name)
            ->assertDontSee($otherSupplier->name)
            ->assertDontSee($otherItem->name);

        $this->actingAs($staff)->get(route('inventory.procurement.show', $otherOrder))->assertForbidden();
        $this->actingAs($staff)->post(route('inventory.procurement.submit', $otherOrder))->assertForbidden();
        $this->actingAs($staff)->post(route('inventory.procurement.cancel', $otherOrder), ['reason' => 'Unauthorized'])->assertForbidden();

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $otherOrder->fresh()->status);
    }

    public function test_purchase_order_moves_through_submit_partial_and_complete_receiving_routes(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $staff = $this->staffAt($facility, 'inventory');
        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $supplier = InventorySupplier::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $item = InventoryItem::factory()->create(['facility_id' => $facility->id, 'name' => 'Sterile Gloves', 'unit' => 'box', 'is_active' => true]);
        $order = app(InventoryProcurementService::class)->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $item->id, 'quantity_ordered' => 5, 'unit_cost' => 10000]],
        ]);
        $orderItem = $order->items->first();

        $this->actingAs($staff)->post(route('inventory.procurement.submit', $order))->assertRedirect();
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $order->fresh()->status);

        $this->actingAs($staff)
            ->post(route('inventory.procurement.receive', $order), [
                'store_id' => $store->id,
                'items' => [['purchase_order_item_id' => $orderItem->id, 'quantity_received' => 2, 'unit_cost' => 10000]],
            ])
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->fresh()->status);
        $this->assertSame(2.0, (float) InventoryStockBalance::query()->where('store_id', $store->id)->where('inventory_item_id', $item->id)->value('quantity_on_hand'));
        $this->actingAs($staff)->get(route('inventory.procurement.show', $order))->assertOk()->assertSee('3.000');

        $this->actingAs($staff)
            ->post(route('inventory.procurement.receive', $order), [
                'store_id' => $store->id,
                'items' => [['purchase_order_item_id' => $orderItem->id, 'quantity_received' => 3]],
            ])
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(5.0, (float) InventoryStockBalance::query()->where('store_id', $store->id)->where('inventory_item_id', $item->id)->value('quantity_on_hand'));
    }

    public function test_view_only_pharmacy_role_does_not_receive_procurement_mutation_controls(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $inventoryStaff = $this->staffAt($facility, 'inventory');
        $pharmacy = $this->staffAt($facility, 'pharmacy');
        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $supplier = InventorySupplier::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $item = InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $order = app(InventoryProcurementService::class)->createPurchaseOrder($inventoryStaff, $supplier, $store, [
            'items' => [['inventory_item_id' => $item->id, 'quantity_ordered' => 1, 'unit_cost' => 1]],
        ]);

        $this->actingAs($pharmacy)
            ->get(route('inventory.procurement.index'))
            ->assertOk()
            ->assertDontSee('Create purchase order');

        $this->actingAs($pharmacy)
            ->get(route('inventory.procurement.show', $order))
            ->assertOk()
            ->assertDontSee('Add catalogue item')
            ->assertDontSee('Submit purchase order')
            ->assertDontSee('Cancel purchase order');

        $this->actingAs($pharmacy)->post(route('inventory.procurement.submit', $order))->assertForbidden();
    }

    private function staffAt(Facility $facility, string $roleSlug): User
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->attach(Role::query()->where('slug', $roleSlug)->valueOrFail('id'));
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        StaffProfile::create(['user_id' => $staff->id, 'department_id' => $department->id]);

        return $staff;
    }
}
