<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('sku')->nullable();
            $table->string('category')->nullable();
            $table->string('unit')->default('unit');
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'sku']);
            $table->index(['facility_id', 'is_active'], 'inv_items_facility_active_idx');
        });

        Schema::create('inventory_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('service_point_id')->nullable()->constrained('service_points')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('type')->default('store');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['facility_id', 'code']);
            $table->index(['facility_id', 'is_active'], 'inv_stores_facility_active_idx');
        });

        Schema::create('inventory_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('inventory_stores')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('quantity_reserved', 14, 3)->default(0);
            $table->decimal('quantity_available', 14, 3)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['store_id', 'inventory_item_id']);
            $table->index(['store_id', 'status'], 'inv_bal_store_status_idx');
        });

        Schema::create('inventory_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['facility_id', 'code']);
            $table->index(['facility_id', 'is_active'], 'inv_suppliers_facility_active_idx');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('inventory_suppliers')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('draft');
            $table->date('ordered_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['facility_id', 'status'], 'po_facility_status_idx');
            $table->index(['supplier_id', 'status'], 'po_supplier_status_idx');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity_ordered', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'inventory_item_id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignId('received_by_id')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('status')->default('posted');
            $table->dateTime('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['facility_id', 'status'], 'receipts_facility_status_idx');
            $table->index(['purchase_order_id', 'status'], 'receipts_po_status_idx');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity_received', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['goods_receipt_id', 'purchase_order_item_id'], 'receipt_item_unique');
        });

        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->nullOnDelete();
            $table->foreignId('performed_by_id')->constrained('users')->restrictOnDelete();
            $table->string('movement_type');
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'inventory_item_id', 'created_at'], 'stock_move_store_item_created_idx');
            $table->index(['movement_type', 'created_at'], 'stock_move_type_created_idx');
            $table->index(['reference_type', 'reference_id'], 'stock_move_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('inventory_suppliers');
        Schema::dropIfExists('inventory_stock_balances');
        Schema::dropIfExists('inventory_stores');
        Schema::dropIfExists('inventory_items');
    }
};
