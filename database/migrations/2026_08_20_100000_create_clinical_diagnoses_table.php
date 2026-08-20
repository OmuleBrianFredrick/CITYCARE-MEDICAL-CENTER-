<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained('clinical_encounters')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('diagnosis', 500);
            $table->string('diagnosis_code', 100)->nullable();
            $table->string('type', 30)->default('primary');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['encounter_id', 'created_at']);
            $table->index(['recorded_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_diagnoses');
    }
};
