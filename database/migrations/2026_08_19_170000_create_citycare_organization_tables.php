<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('registration_number', 100)->nullable()->unique();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('country', 100)->default('Uganda');
            $table->string('timezone', 80)->default('Africa/Kampala');
            $table->string('currency', 3)->default('UGX');
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 20)->default('#2563EB');
            $table->string('secondary_color', 20)->default('#0F172A');
            $table->string('accent_color', 20)->default('#F4C430');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['facility_id', 'code']);
            $table->index(['facility_id', 'is_active', 'sort_order']);
        });

        Schema::create('service_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('type', 60)->default('service');
            $table->string('location')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['department_id', 'is_active', 'sort_order']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->string('group', 60)->index();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('service_point_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->index(['department_id', 'employment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropForeign(['service_point_id']);
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id', 'employment_status']);
            $table->dropColumn(['department_id', 'service_point_id']);
        });

        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('service_points');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('facilities');
    }
};
