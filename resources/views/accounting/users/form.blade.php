@extends('layouts.odoo')

@section('title', $user->exists ? 'Edit Pengguna' : 'Tambah Pengguna')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $user->exists ? 'Edit Pengguna' : 'Tambah Pengguna' }}</h1>
        <a href="{{ route('accounting.users.index') }}" class="odoo-btn-secondary">Batal</a>
    </div>

    <form method="POST"
        action="{{ $user->exists ? route('accounting.users.update', $user) : route('accounting.users.store') }}">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="bg-white rounded border border-odoo-border shadow-sm p-4 grid grid-cols-1 md:grid-cols-2 gap-4 max-w-3xl">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nama *</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm @error('name') border-red-400 @enderror"
                    required>
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Email *</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm @error('email') border-red-400 @enderror"
                    required>
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    Kata Sandi {{ $user->exists ? '' : '*' }}
                </label>
                <input type="password" name="password"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm @error('password') border-red-400 @enderror"
                    {{ $user->exists ? '' : 'required' }}
                    autocomplete="new-password">
                @if ($user->exists)
                    <p class="text-xs text-gray-400 mt-1">Kosongkan jika tidak ingin mengubah kata sandi.</p>
                @endif
                @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    Konfirmasi Kata Sandi {{ $user->exists ? '' : '*' }}
                </label>
                <input type="password" name="password_confirmation"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm"
                    {{ $user->exists ? '' : 'required' }}
                    autocomplete="new-password">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Peran *</label>
                <select name="role" class="w-full border border-odoo-border rounded px-3 py-2 text-sm @error('role') border-red-400 @enderror" required>
                    @foreach ($roleOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center pt-6">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                    class="rounded border-odoo-border text-odoo-purple focus:ring-odoo-purple"
                    @checked(old('is_active', $user->is_active ?? true))>
                <label for="is_active" class="ml-2 text-sm text-gray-700">Akun aktif</label>
            </div>
            @error('is_active')<p class="text-xs text-red-600 md:col-span-2">{{ $message }}</p>@enderror

            <div class="md:col-span-2 pt-2">
                <button type="submit" class="odoo-btn-primary">Simpan</button>
            </div>
        </div>
    </form>
@endsection
