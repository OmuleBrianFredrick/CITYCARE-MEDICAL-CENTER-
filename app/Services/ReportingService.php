<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportingService
{
    public function run(User $staff, ReportDefinition $definition, array $filters = [], ?int $facilityId = null): ReportRun
    {
        $this->assertActiveStaff($staff);

        if (! $definition->is_active) {
            throw ValidationException::withMessages(['report' => 'The selected report is inactive.']);
        }

        $filters = $this->normalizeFilters($definition, $filters);
        $facilityId ??= $filters['facility_id'] ?? null;

        if ($facilityId !== null) {
            $filters['facility_id'] = (int) $facilityId;
        }

        $run = ReportRun::create([
            'report_definition_id' => $definition->id,
            'requested_by_id' => $staff->id,
            'facility_id' => $facilityId,
            'status' => ReportRun::STATUS_RUNNING,
            'filters' => $filters,
            'period_start' => $filters['date_from'] ?? null,
            'period_end' => $filters['date_to'] ?? null,
            'started_at' => now(),
        ]);

        try {
            $result = match ($definition->code) {
                'clinical_activity' => $this->clinicalActivity($filters),
                'laboratory_activity' => $this->laboratoryActivity($filters),
                'pharmacy_activity' => $this->pharmacyActivity($filters),
                'billing_summary' => $this->billingSummary($filters),
                'inventory_summary' => $this->inventorySummary($filters),
                default => throw ValidationException::withMessages(['report' => 'Unsupported report definition.']),
            };

            $run->update([
                'status' => ReportRun::STATUS_COMPLETED,
                'result_metadata' => $result,
                'completed_at' => now(),
            ]);

            AuditEvent::create([
                'actor_id' => $staff->id,
                'facility_id' => $facilityId,
                'event_type' => 'report.run.completed',
                'action' => 'completed',
                'auditable_type' => ReportRun::class,
                'auditable_id' => $run->id,
                'before_values' => null,
                'after_values' => ['report_definition_id' => $definition->id, 'filters' => $filters],
                'context' => ['report_code' => $definition->code],
                'occurred_at' => now(),
            ]);

            return $run->fresh();
        } catch (\Throwable $exception) {
            $run->update([
                'status' => ReportRun::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function clinicalActivity(array $filters): array
    {
        $query = DB::table('clinical_encounters');
        $this->applyDateAndFacility($query, $filters, 'created_at', 'facility_id');

        return [
            'report' => 'clinical_activity',
            'total_encounters' => (int) $query->count(),
            'by_status' => $query->clone()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->orderBy('status')->pluck('total', 'status')->map(fn ($value) => (int) $value)->all(),
        ];
    }

    private function laboratoryActivity(array $filters): array
    {
        $query = DB::table('laboratory_orders');
        $this->applyDateAndFacility($query, $filters, 'created_at', 'facility_id');

        return [
            'report' => 'laboratory_activity',
            'total_orders' => (int) $query->count(),
            'by_status' => $query->clone()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->orderBy('status')->pluck('total', 'status')->map(fn ($value) => (int) $value)->all(),
        ];
    }

    private function pharmacyActivity(array $filters): array
    {
        $query = DB::table('prescriptions');
        $this->applyDateAndFacility($query, $filters, 'created_at', 'facility_id');

        return [
            'report' => 'pharmacy_activity',
            'total_prescriptions' => (int) $query->count(),
            'by_status' => $query->clone()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->orderBy('status')->pluck('total', 'status')->map(fn ($value) => (int) $value)->all(),
        ];
    }

    private function billingSummary(array $filters): array
    {
        $query = DB::table('invoices');
        $this->applyDateAndFacility($query, $filters, 'created_at', 'facility_id');

        $totals = $query->clone()->selectRaw('COALESCE(SUM(total),0) total, COALESCE(SUM(paid_amount),0) paid, COALESCE(SUM(balance_due),0) outstanding')->first();

        return [
            'report' => 'billing_summary',
            'invoice_count' => (int) $query->count(),
            'total' => (float) $totals->total,
            'paid' => (float) $totals->paid,
            'outstanding' => (float) $totals->outstanding,
        ];
    }

    private function inventorySummary(array $filters): array
    {
        $query = DB::table('inventory_stock_balances as balances')
            ->join('inventory_stores as stores', 'stores.id', '=', 'balances.store_id');

        if (isset($filters['facility_id'])) {
            $query->where('stores.facility_id', (int) $filters['facility_id']);
        }

        return [
            'report' => 'inventory_summary',
            'stock_line_count' => (int) $query->count(),
            'quantity_on_hand' => (float) $query->sum('balances.quantity_on_hand'),
            'quantity_available' => (float) $query->sum('balances.quantity_available'),
            'quantity_reserved' => (float) $query->sum('balances.quantity_reserved'),
        ];
    }

    private function normalizeFilters(ReportDefinition $definition, array $filters): array
    {
        $supported = $definition->supported_filters ?? [];
        $allowed = array_flip($supported);

        foreach ($filters as $key => $_) {
            if (! isset($allowed[$key])) {
                throw ValidationException::withMessages(["filters.{$key}" => 'This filter is not supported by the selected report.']);
            }
        }

        if (isset($filters['date_from'])) {
            $filters['date_from'] = Carbon::parse($filters['date_from'])->startOfDay()->toDateTimeString();
        }
        if (isset($filters['date_to'])) {
            $filters['date_to'] = Carbon::parse($filters['date_to'])->endOfDay()->toDateTimeString();
        }
        if (isset($filters['date_from'], $filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
            throw ValidationException::withMessages(['filters' => 'date_from must be before or equal to date_to.']);
        }

        return $filters;
    }

    private function applyDateAndFacility($query, array $filters, string $dateColumn, string $facilityColumn): void
    {
        if (isset($filters['facility_id'])) {
            $query->where($facilityColumn, (int) $filters['facility_id']);
        }
        if (isset($filters['date_from'])) {
            $query->where($dateColumn, '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where($dateColumn, '<=', $filters['date_to']);
        }
    }

    private function assertActiveStaff(User $staff): void
    {
        if (! $staff->isStaff() || ! $staff->isActive()) {
            throw ValidationException::withMessages(['staff' => 'Active staff access is required.']);
        }
    }
}
