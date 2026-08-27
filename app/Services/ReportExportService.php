<?php

namespace App\Services;

use App\Models\ReportRun;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(private readonly ReportingAccessService $access) {}

    public function csv(User $staff, ReportRun $run): StreamedResponse
    {
        $this->access->assertRunAccessible($staff, $run);
        $run->loadMissing('definition', 'requester', 'facility');

        if ($run->status !== ReportRun::STATUS_COMPLETED) {
            abort(422, 'Only completed reports can be exported.');
        }

        return response()->streamDownload(function () use ($run): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open export stream.');
            }

            $this->writeRow($handle, ['Report', $run->definition->name]);
            $this->writeRow($handle, ['Status', $run->status]);
            $this->writeRow($handle, ['Requested By', $run->requester?->name ?? 'System']);
            $this->writeRow($handle, ['Facility', $run->facility?->name ?? 'All Facilities']);
            $this->writeRow($handle, ['Period Start', $run->period_start?->toDateTimeString()]);
            $this->writeRow($handle, ['Period End', $run->period_end?->toDateTimeString()]);
            fputcsv($handle, []);

            foreach (($run->result_metadata ?? []) as $key => $value) {
                $value = ($key === 'report' && is_string($value))
                    ? $this->label($value)
                    : $this->scalarize($value);

                $this->writeRow($handle, [$this->label($key), $value]);
            }

            fclose($handle);
        }, sprintf('report-%d.csv', $run->id), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function scalarize(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }

    /**
     * Prefix formula-like spreadsheet cells so opening a CSV cannot execute
     * values originating in names or report metadata as a formula.
     *
     * @param  resource  $handle
     * @param  array<int, mixed>  $values
     */
    private function writeRow($handle, array $values): void
    {
        fputcsv($handle, array_map(function (mixed $value): mixed {
            if (is_string($value) && preg_match('/^[=+\-@\t\r\n]/u', $value) === 1) {
                return "'".$value;
            }

            return $value;
        }, $values));
    }

    private function label(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
