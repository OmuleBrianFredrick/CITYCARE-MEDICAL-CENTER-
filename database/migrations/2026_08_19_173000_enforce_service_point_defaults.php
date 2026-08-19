<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_points', function (Blueprint $table) {
            $table->string('type', 60)->default('service')->change();
            $table->unsignedInteger('sort_order')->default(0)->change();
            $table->boolean('is_active')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('service_points', function (Blueprint $table) {
            $table->string('type', 60)->default(null)->change();
            $table->unsignedInteger('sort_order')->default(null)->change();
            $table->boolean('is_active')->default(null)->change();
        });
    }
};
