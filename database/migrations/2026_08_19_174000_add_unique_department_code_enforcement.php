<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The organization foundation migration already defines this unique
        // constraint. This migration exists only to make the invariant explicit
        // for databases created from an earlier revision.
        // Intentionally no-op when the constraint already exists.
        if (! Schema::hasTable('departments')) {
            return;
        }
    }

    public function down(): void
    {
        // Intentionally no-op. The canonical constraint belongs to the original
        // organization migration and must not be removed from a live schema here.
    }
};
