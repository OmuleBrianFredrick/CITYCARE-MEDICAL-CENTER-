<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_balances', function (Blueprint $table) {
            $table->unique(['store_id', 'inventory_item_id'], 'inventory_stock_balances_store_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_balances', function (Blueprint $table) {
            $table->dropUnique('inventory_stock_balances_store_item_unique');
        });
    }
};
