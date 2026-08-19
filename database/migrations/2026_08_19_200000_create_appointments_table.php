<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_point_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('appointment_number', 40)->unique();
            $table->dateTime('scheduled_start');
            $table->dateTime('scheduled_end');
            $table->string('status', 30)->default('scheduled');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['facility_id', 'scheduled_start']);
            $table->index(['patient_id', 'scheduled_start']);
            $table->index(['provider_id', 'scheduled_start']);
            $table->index(['service_point_id', 'scheduled_start']);
            $table->index(['status', 'scheduled_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
