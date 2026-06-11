<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'journal_type_id' => ['required', 'exists:journal_types,id'],
            'entry_number' => ['nullable', 'string', 'max:50', Rule::unique('journal_entries', 'entry_number')],
            'entry_date' => ['required', 'date'],
            'period' => ['required', 'integer', 'min:1', 'max:12'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'description' => ['nullable', 'string'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.counter_account_id' => ['nullable', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.notes' => ['nullable', 'string'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.min' => 'Jurnal minimal harus memiliki 2 baris.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('entry_date')) {
            $this->merge([
                'period' => (int) date('n', strtotime($this->entry_date)),
            ]);
        }
    }
}
