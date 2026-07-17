<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $entryId = $this->route('journal_entry')?->id ?? $this->route('journal_entry');

        return [
            'journal_type_id' => ['required', 'exists:journal_types,id'],
            'entry_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('journal_entries', 'entry_number')->ignore($entryId),
            ],
            'entry_date' => ['required', 'date'],
            'period' => ['required', 'integer', 'min:1', 'max:12'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.counter_account_id' => ['nullable', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.notes' => ['nullable', 'string'],
            'lines.*.exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
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
