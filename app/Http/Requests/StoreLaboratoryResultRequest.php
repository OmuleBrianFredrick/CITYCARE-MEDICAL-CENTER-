<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLaboratoryResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'result_value' => ['required', 'string', 'max:2000'],
            'unit' => ['nullable', 'string', 'max:100'],
            'reference_range' => ['nullable', 'string', 'max:255'],
            'is_abnormal' => ['nullable', 'boolean'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
