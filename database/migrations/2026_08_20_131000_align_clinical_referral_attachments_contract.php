<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_referral_attachments', function (Blueprint $table) {
            $table->renameColumn('referral_id', 'clinical_referral_id');
            $table->renameColumn('path', 'file_path');
            $table->renameColumn('original_name', 'file_name');
            $table->renameColumn('size_bytes', 'file_size');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_referral_attachments', function (Blueprint $table) {
            $table->renameColumn('clinical_referral_id', 'referral_id');
            $table->renameColumn('file_path', 'path');
            $table->renameColumn('file_name', 'original_name');
            $table->renameColumn('file_size', 'size_bytes');
        });
    }
};
