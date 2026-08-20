<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained('clinical_encounters')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('plan');
            $table->date('follow_up_date')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['encounter_id', 'status']);
            $table->index(['author_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_treatment_plans');
    }
};
