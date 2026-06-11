<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var User $user */
            $user = $this->route('user');

            if ($user->id === $this->user()->id && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'Anda tidak dapat menonaktifkan akun sendiri.');
            }

            if ($user->id === $this->user()->id && $this->input('role') !== UserRole::Admin->value) {
                $validator->errors()->add('role', 'Anda tidak dapat mengubah peran akun sendiri.');
            }
        });
    }
}
