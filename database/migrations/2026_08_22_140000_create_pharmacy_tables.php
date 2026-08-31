<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'medications',
            'medication_formulations',
            'prescriptions',
            'prescription_items',
            'medication_dispensings',
            'medication_dispensing_items',
        ] as $table) {
            if (Schema::hasTable($table)) {
                throw new LogicException("The pharmacy foundation cannot be applied because [{$table}] already exists.");
            }
        }

        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('generic_name');
            $table->string('code')->nullable();
            $table->string('route')->nullable();
            $table->string('dosage_form')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['facility_id', 'name', 'dosage_form'], 'med_fac_name_form_unique');
        });

        Schema::create('medication_formulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained()->cascadeOnDelete();
            $table->string('strength')->nullable();
            $table->string('unit')->nullable();
            $table->string('pack_size')->nullable();
            $table->string('sku')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['medication_id', 'strength', 'unit', 'pack_size'], 'med_formulation_unique');
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('clinical_encounters')->nullOnDelete();
            $table->foreignId('prescribed_by')->constrained('users')->restrictOnDelete();
            $table->string('prescription_number')->unique();
            $table->string('status')->default('prescribed');
            $table->text('notes')->nullable();
            $table->timestamp('prescribed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained()->restrictOnDelete();
            $table->foreignId('medication_formulation_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('dose')->nullable();
            $table->string('route')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status')->default('prescribed');
            $table->timestamps();
        });

        Schema::create('medication_dispensings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispensed_by')->constrained('users')->restrictOnDelete();
            $table->string('dispensing_number')->unique();
            $table->string('status')->default('completed');
            $table->text('notes')->nullable();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('medication_dispensing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_dispensings_id')->constrained('medication_dispensings')->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_dispensed', 12, 3);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_dispensing_items');
        Schema::dropIfExists('medication_dispensings');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medication_formulations');
        Schema::dropIfExists('medications');
    }
};
