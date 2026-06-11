@extends('layouts.odoo')

@section('title', $account->exists ? 'Edit Akun' : ($account->is_header ? 'Tambah Header' : 'Tambah Detail'))

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-semibold">
                {{ $account->exists ? 'Edit Akun' : ($account->is_header ? 'Tambah Header' : 'Tambah Detail') }}
            </h1>
            <p class="text-sm text-gray-500">
                @if ($account->is_header)
                    Header — kode berakhiran <span class="font-mono">.000</span>, tidak bisa diposting
                @else
                    Detail — kode tidak berakhiran <span class="font-mono">.000</span>, bisa diposting jurnal
                @endif
            </p>
        </div>
        <a href="{{ route('accounting.accounts.index') }}" class="odoo-btn-secondary">Batal</a>
    </div>

    <form method="POST"
        action="{{ $account->exists ? route('accounting.accounts.update', $account) : route('accounting.accounts.store') }}">
        @csrf
        @if ($account->exists)
            @method('PUT')
        @endif

        <input type="hidden" name="is_header" value="{{ $account->is_header ? '1' : '0' }}">

        <div class="bg-white rounded border border-odoo-border shadow-sm p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tipe Akun</label>
                <div class="px-3 py-2 border border-odoo-border rounded text-sm bg-gray-50">
                    <span class="text-xs px-2 py-0.5 rounded {{ $account->is_header ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ $account->is_header ? 'Header (H)' : 'Detail (D)' }}
                    </span>
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Kode Akun *</label>
                <input type="text" name="code" value="{{ old('code', $account->code) }}"
                    placeholder="{{ $account->is_header ? '1.111.000' : '1.111.001' }}"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm font-mono @error('code') border-red-400 @enderror"
                    required>
                @error('code')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Nama Akun *</label>
                <input type="text" name="name" value="{{ old('name', $account->name) }}"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm @error('name') border-red-400 @enderror"
                    required>
                @error('name')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Kelompok / Group Akun</label>
                <input type="text" name="group_name" value="{{ old('group_name', $account->group_name) }}"
                    placeholder="Contoh: Kas dan Setara Kas"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Pos Saldo *</label>
                <select name="normal_balance" class="w-full border border-odoo-border rounded px-3 py-2 text-sm" required>
                    <option value="debit" @selected(old('normal_balance', $account->normal_balance) === 'debit')>Debit (Db)</option>
                    <option value="credit" @selected(old('normal_balance', $account->normal_balance) === 'credit')>Kredit (Cr)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    Header Induk
                    @if (! $account->is_header)
                        <span class="text-red-500">*</span>
                    @endif
                </label>
                <select name="parent_id" class="w-full border border-odoo-border rounded px-3 py-2 text-sm @error('parent_id') border-red-400 @enderror"
                    {{ ! $account->is_header ? 'required' : '' }}>
                    @if ($account->is_header)
                        <option value="">— Tanpa induk —</option>
                    @endif
                    @foreach ($headerAccounts as $header)
                        <option value="{{ $header->id }}" @selected(old('parent_id', $account->parent_id) == $header->id)>
                            {{ $header->code }} — {{ $header->name }}
                        </option>
                    @endforeach
                </select>
                @error('parent_id')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Status</label>
                <select name="is_active" class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
                    <option value="1" @selected(old('is_active', $account->is_active ?? true))>Aktif</option>
                    <option value="0" @selected(! old('is_active', $account->is_active ?? true))>Nonaktif</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit" class="odoo-btn-primary">
                {{ $account->exists ? 'Simpan Perubahan' : 'Tambah Akun' }}
            </button>
        </div>
    </form>
@endsection
