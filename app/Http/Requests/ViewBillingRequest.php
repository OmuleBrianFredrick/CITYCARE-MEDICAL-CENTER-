<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViewBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('billing.view') === true;
    }

    public function rules(): array
    {
        return [];
    }
}
