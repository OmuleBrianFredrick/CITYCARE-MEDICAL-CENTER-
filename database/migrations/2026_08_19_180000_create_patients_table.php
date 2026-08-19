<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('medical_record_number', 40)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('sex', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('national_id', 100)->nullable()->unique();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('country', 100)->default('Uganda');

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship', 80)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_relationship', 80)->nullable();
            $table->string('next_of_kin_phone', 40)->nullable();

            $table->string('status', 30)->default('active');
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'status']);
            $table->index(['last_name', 'first_name']);
            $table->index('phone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
