<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->user();

        return $staff?->isStaff()
            && $staff->isActive()
            && $staff->hasPermissionTo('reports.view');
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'in:csv'],
            'report_run' => ['required', 'integer', 'min:1'],
        ];
    }

    public function reportRunId(): int
    {
        return (int) $this->validated('report_run');
    }
}
