<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLaboratoryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'test_ids' => ['required', 'array', 'min:1'],
            'test_ids.*' => ['required', 'integer', 'distinct', 'exists:laboratory_tests,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
