<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicalReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referred_to' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'in:routine,urgent,emergency'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
