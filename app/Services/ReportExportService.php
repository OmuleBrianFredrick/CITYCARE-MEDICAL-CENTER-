<?php

namespace App\Services;

use App\Models\ReportRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function csv(ReportRun $run): StreamedResponse
    {
        $run->loadMissing('definition', 'requester', 'facility');

        if ($run->status !== ReportRun::STATUS_COMPLETED) {
            abort(422, 'Only completed reports can be exported.');
        }

        return response()->streamDownload(function () use ($run): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open export stream.');
            }

            fputcsv($handle, ['Report', $run->definition->name]);
            fputcsv($handle, ['Status', $run->status]);
            fputcsv($handle, ['Requested By', $run->requester?->name ?? 'System']);
            fputcsv($handle, ['Facility', $run->facility?->name ?? 'All Facilities']);
            fputcsv($handle, ['Period Start', $run->period_start?->toDateTimeString()]);
            fputcsv($handle, ['Period End', $run->period_end?->toDateTimeString()]);
            fputcsv($handle, []);

            foreach (($run->result_metadata ?? []) as $key => $value) {
                fputcsv($handle, [$this->label($key), $this->scalarize($value)]);
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

    private function label(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
