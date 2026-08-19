<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_point_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('clinician_id')->constrained('users')->restrictOnDelete();
            $table->string('encounter_number', 40)->unique();
            $table->string('type', 30)->default('outpatient');
            $table->string('status', 30)->default('open');
            $table->timestamp('started_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'started_at']);
            $table->index(['clinician_id', 'status']);
            $table->index(['service_point_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_encounters');
    }
};
