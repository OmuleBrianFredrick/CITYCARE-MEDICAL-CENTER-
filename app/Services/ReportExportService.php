<?php

namespace App\Services;

use App\Models\ReportRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function csv(ReportRun $run): StreamedResponse
    {
        $run->loadMissing('definition', 'requester', 'facility');

        return response()->streamDownload(function () use ($run): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, ['Report', $run->definition->name]);
            fputcsv($handle, ['Status', $run->status]);
            fputcsv($handle, ['Requested By', $run->requester?->name ?? 'System']);
            fputcsv($handle, ['Facility', $run->facility?->name ?? 'All Facilities']);
            fputcsv($handle, ['Period Start', optional($run->period_start)?->toDateTimeString()]);
            fputcsv($handle, ['Period End', optional($run->period_end)?->toDateTimeString()]);
            fputcsv($handle, []);

            foreach (($run->result_metadata ?? []) as $key => $value) {
                if (is_array($value)) {
                    fputcsv($handle, [$this->label($key), json_encode($value, JSON_UNESCAPED_UNICODE)]);
                } else {
                    fputcsv($handle, [$this->label($key), $value]);
                }
            }

            fclose($handle);
        }, sprintf('report-%d.csv', $run->id), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function label(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
