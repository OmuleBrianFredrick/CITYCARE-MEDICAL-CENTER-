<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $unassignableEncounterId = DB::table('clinical_encounters')
            ->leftJoin('service_points', 'service_points.id', '=', 'clinical_encounters.service_point_id')
            ->whereNull('service_points.department_id')
            ->orderBy('clinical_encounters.id')
            ->value('clinical_encounters.id');

        if ($unassignableEncounterId !== null) {
            throw new RuntimeException(
                "Clinical encounter {$unassignableEncounterId} cannot be assigned to a department because its service point is missing or has no department. Repair that record and rerun the migration.",
            );
        }

        Schema::table('clinical_encounters', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('facility_id')->constrained()->restrictOnDelete();
        });

        DB::table('clinical_encounters')
            ->select(['id', 'service_point_id'])
            ->orderBy('id')
            ->chunkById(100, function ($encounters): void {
                $departmentIds = DB::table('service_points')
                    ->whereIn('id', $encounters->pluck('service_point_id'))
                    ->pluck('department_id', 'id');

                foreach ($encounters as $encounter) {
                    $departmentId = $departmentIds[$encounter->service_point_id] ?? null;

                    if ($departmentId === null) {
                        throw new RuntimeException(
                            "Clinical encounter {$encounter->id} cannot be assigned to a department because its service point is missing or orphaned. Repair that record and rerun the migration.",
                        );
                    }

                    DB::table('clinical_encounters')
                        ->where('id', $encounter->id)
                        ->update(['department_id' => $departmentId]);
                }
            });

        Schema::table('clinical_encounters', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable(false)->change();
            $table->index(['department_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('clinical_encounters', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id', 'started_at']);
            $table->dropColumn('department_id');
        });
    }
};
