<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_inventory_staff_can_manage_facility_catalogue_and_post_signed_adjustments(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $staff = $this->staffAt($facility, 'inventory');

        $this->actingAs($staff)->post(route('inventory.catalogue.items.store'), [
            'name' => 'Surgical Mask',
            'code' => 'MASK-001',
            'sku' => 'SKU-MASK-001',
            'category' => 'PPE',
            'unit' => 'box',
            'reorder_level' => 4,
            'is_active' => 1,
        ])->assertRedirect();
        $this->actingAs($staff)->post(route('inventory.catalogue.suppliers.store'), [
            'name' => 'City Medical Supplies',
            'code' => 'SUP-001',
            'email' => 'supply@example.test',
            'is_active' => 1,
        ])->assertRedirect();
        $this->actingAs($staff)->post(route('inventory.catalogue.stores.store'), [
            'name' => 'Central Store',
            'code' => 'STORE-001',
            'type' => 'central',
            'is_active' => 1,
        ])->assertRedirect();

        $item = InventoryItem::query()->where('facility_id', $facility->id)->where('code', 'MASK-001')->firstOrFail();
        $supplier = InventorySupplier::query()->where('facility_id', $facility->id)->where('code', 'SUP-001')->firstOrFail();
        $store = InventoryStore::query()->where('facility_id', $facility->id)->where('code', 'STORE-001')->firstOrFail();
        $this->assertSame('City Medical Supplies', $supplier->name);

        $this->actingAs($staff)->post(route('inventory.adjustments.store'), [
            'store_id' => $store->id,
            'inventory_item_id' => $item->id,
            'direction' => 'increase',
            'quantity' => 5,
            'reason' => 'Opening verified stock count.',
        ])->assertRedirect();
        $this->actingAs($staff)->post(route('inventory.adjustments.store'), [
            'store_id' => $store->id,
            'inventory_item_id' => $item->id,
            'direction' => 'decrease',
            'quantity' => 2,
            'reason' => 'Two damaged boxes written off.',
        ])->assertRedirect();

        $balance = InventoryStockBalance::query()->where('store_id', $store->id)->where('inventory_item_id', $item->id)->firstOrFail();
        $this->assertSame(3.0, (float) $balance->quantity_on_hand);
        $this->assertSame(3.0, (float) $balance->quantity_available);
        $this->assertDatabaseHas('inventory_stock_movements', ['movement_type' => 'adjustment_in', 'quantity' => 5]);
        $this->assertDatabaseHas('inventory_stock_movements', ['movement_type' => 'adjustment_out', 'quantity' => -2]);

        $this->actingAs($staff)
            ->get(route('inventory.catalogue.index'))
            ->assertOk()
            ->assertSee($item->name)
            ->assertSee($supplier->name)
            ->assertSee($store->name);
    }

    public function test_adjustment_cannot_make_stock_negative_or_cross_facility_boundaries(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $otherFacility = Facility::factory()->create(['is_active' => true]);
        $staff = $this->staffAt($facility, 'inventory');
        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $item = InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        InventoryStockBalance::factory()->create(['store_id' => $store->id, 'inventory_item_id' => $item->id, 'quantity_on_hand' => 1, 'quantity_available' => 1]);
        $otherItem = InventoryItem::factory()->create(['facility_id' => $otherFacility->id, 'name' => 'Other Facility Item', 'is_active' => true]);

        $this->actingAs($staff)->post(route('inventory.adjustments.store'), [
            'store_id' => $store->id,
            'inventory_item_id' => $item->id,
            'direction' => 'decrease',
            'quantity' => 2,
            'reason' => 'Invalid decrease attempt.',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(1.0, (float) InventoryStockBalance::query()->where('store_id', $store->id)->where('inventory_item_id', $item->id)->value('quantity_on_hand'));
        $this->assertSame(0, InventoryStockMovement::query()->count());

        $this->actingAs($staff)->put(route('inventory.catalogue.items.update', $otherItem), [
            'name' => 'Forged update',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_active' => 1,
        ])->assertForbidden();
        $this->assertSame('Other Facility Item', $otherItem->fresh()->name);
    }

    public function test_pharmacy_inventory_view_is_read_only_in_catalogue(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $pharmacy = $this->staffAt($facility, 'pharmacy');
        InventoryItem::factory()->create(['facility_id' => $facility->id, 'name' => 'Visible Stock Item']);

        $this->actingAs($pharmacy)
            ->get(route('inventory.catalogue.index'))
            ->assertOk()
            ->assertSee('Visible Stock Item')
            ->assertDontSee('Create inventory item')
            ->assertDontSee('Controlled stock adjustment');

        $this->actingAs($pharmacy)->post(route('inventory.catalogue.items.store'), [])->assertForbidden();
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
