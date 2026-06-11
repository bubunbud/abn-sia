<?php

namespace App\Http\Requests\Accounting;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->route('account')?->id ?? $this->route('account');

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^\d+\.\d{3}\.\d{3}$/',
                Rule::unique('accounts', 'code')->ignore($accountId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'is_header' => ['required', 'boolean'],
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateCodeType($validator);
            $this->validateParent($validator);
            $this->validateNotSelfParent($validator);
        });
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Format kode akun harus seperti 1.111.001.',
            'code.unique' => 'Kode akun sudah digunakan.',
        ];
    }

    private function validateCodeType(Validator $validator): void
    {
        $code = $this->input('code');
        $isHeader = $this->boolean('is_header');

        if (! $code) {
            return;
        }

        $endsWithZero = str_ends_with($code, '.000');

        if ($isHeader && ! $endsWithZero) {
            $validator->errors()->add('code', 'Kode akun Header harus berakhiran .000 (contoh: 1.111.000).');
        }

        if (! $isHeader && $endsWithZero) {
            $validator->errors()->add('code', 'Kode akun Detail tidak boleh berakhiran .000.');
        }
    }

    private function validateParent(Validator $validator): void
    {
        $parentId = $this->input('parent_id');

        if (! $parentId) {
            if (! $this->boolean('is_header')) {
                $validator->errors()->add('parent_id', 'Akun Detail wajib memiliki Header induk.');
            }

            return;
        }

        $parent = Account::find($parentId);

        if (! $parent || ! $parent->is_header) {
            $validator->errors()->add('parent_id', 'Induk akun harus berupa akun Header.');
        }
    }

    private function validateNotSelfParent(Validator $validator): void
    {
        $account = $this->route('account');
        $parentId = $this->input('parent_id');

        if ($account && $parentId && (int) $parentId === $account->id) {
            $validator->errors()->add('parent_id', 'Akun tidak boleh menjadi induk dirinya sendiri.');
        }
    }
}
