<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('portal_invited_at')->nullable()->after('registered_at');
            $table->timestamp('portal_activated_at')->nullable()->after('portal_invited_at');
            $table->timestamp('portal_disabled_at')->nullable()->after('portal_activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'portal_invited_at',
                'portal_activated_at',
                'portal_disabled_at',
            ]);
        });
    }
};
