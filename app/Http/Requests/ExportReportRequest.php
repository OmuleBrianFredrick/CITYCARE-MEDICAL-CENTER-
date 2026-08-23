<?php

namespace App\Http\Requests;

use App\Models\ReportRun;
use Illuminate\Foundation\Http\FormRequest;

class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('reports.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'in:csv'],
            'report_run' => ['required', 'integer', 'exists:report_runs,id'],
        ];
    }

    public function reportRun(): ReportRun
    {
        return ReportRun::query()->findOrFail((int) $this->validated('report_run'));
    }
}
