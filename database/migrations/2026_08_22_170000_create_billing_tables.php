<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billable_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('category', 80)->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 40)->default('item');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['facility_id', 'code']);
            $table->index(['facility_id', 'is_active']);
        });

        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('billable_service_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('UGX');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['billable_service_id', 'effective_from']);
            $table->index(['facility_id', 'billable_service_id', 'is_active']);
            $table->index(['effective_from', 'effective_to']);
        });

        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('clinical_encounters')->restrictOnDelete();
            $table->foreignId('billable_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_price_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 30)->default('pending');
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('adjustment_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->string('currency', 3)->default('UGX');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'status']);
            $table->index(['patient_id', 'status']);
            $table->index(['encounter_id', 'status']);
            $table->index(['billable_service_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('clinical_encounters')->restrictOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('invoice_number', 60)->unique();
            $table->string('status', 30)->default('draft');
            $table->string('currency', 3)->default('UGX');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('adjustment_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'status']);
            $table->index(['patient_id', 'status']);
            $table->index(['encounter_id', 'status']);
        });

        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('billable_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('service_price_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('description', 255);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_subtotal', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('adjustment_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->string('currency', 3)->default('UGX');
            $table->timestamps();

            $table->index(['invoice_id']);
            $table->index(['billable_service_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('receipt_number', 60)->nullable()->unique();
            $table->string('method', 30);
            $table->string('status', 30)->default('completed');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('UGX');
            $table->string('transaction_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['method', 'status']);
            $table->index('transaction_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('charges');
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('billable_services');
    }
};