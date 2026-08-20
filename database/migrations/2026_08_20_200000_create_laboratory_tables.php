<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('specimen_type', 100)->nullable();
            $table->string('result_type', 30)->default('text');
            $table->string('unit', 50)->nullable();
            $table->string('reference_range')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['facility_id', 'code']);
            $table->index(['facility_id', 'is_active']);
        });

        Schema::create('laboratory_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('encounter_id')->constrained('clinical_encounters')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('ordered_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('order_number')->unique();
            $table->string('status', 30)->default('ordered');
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
            $table->index(['encounter_id', 'status']);
        });

        Schema::create('laboratory_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_order_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('laboratory_test_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 30)->default('ordered');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['laboratory_order_id', 'laboratory_test_id'], 'lab_order_test_unique');
            $table->index('status');
        });

        Schema::create('laboratory_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_order_item_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->longText('result_value')->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('reference_range')->nullable();
            $table->boolean('is_abnormal')->default(false);
            $table->text('comments')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_results');
        Schema::dropIfExists('laboratory_order_items');
        Schema::dropIfExists('laboratory_orders');
        Schema::dropIfExists('laboratory_tests');
    }
};
