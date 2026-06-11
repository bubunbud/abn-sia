<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = $this->route('partner')?->id ?? $this->route('partner');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('partners', 'code')->ignore($partnerId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['customer', 'vendor', 'financial', 'employee', 'other'])],
            'region' => ['nullable', 'string', 'max:50'],
            'status_label' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
