@extends('layouts.odoo')

@section('title', $partner->exists ? 'Edit Pihak Kedua' : 'Tambah Pihak Kedua')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $partner->exists ? 'Edit Pihak Kedua' : 'Tambah Pihak Kedua' }}</h1>
        <a href="{{ route('accounting.partners.index') }}" class="odoo-btn-secondary">Batal</a>
    </div>

    <form method="POST"
        action="{{ $partner->exists ? route('accounting.partners.update', $partner) : route('accounting.partners.store') }}">
        @csrf
        @if ($partner->exists)
            @method('PUT')
        @endif

        <div class="bg-white rounded border border-odoo-border shadow-sm p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Kode *</label>
                <input type="text" name="code" value="{{ old('code', $partner->code) }}"
                    placeholder="PDL 001 / HDL 001"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm font-mono @error('code') border-red-400 @enderror"
                    required>
                @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Status *</label>
                <select name="type" class="w-full border border-odoo-border rounded px-3 py-2 text-sm" required>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $partner->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Nama *</label>
                <input type="text" name="name" value="{{ old('name', $partner->name) }}"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm @error('name') border-red-400 @enderror"
                    required>
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Keterangan</label>
                <input type="text" name="region" value="{{ old('region', $partner->region) }}"
                    placeholder="Lokal"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Label Status</label>
                <input type="text" name="status_label" value="{{ old('status_label', $partner->status_label) }}"
                    placeholder="Piutang / Hutang"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Aktif</label>
                <select name="is_active" class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
                    <option value="1" @selected(old('is_active', $partner->is_active ?? true))>Ya</option>
                    <option value="0" @selected(! old('is_active', $partner->is_active ?? true))>Tidak</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit" class="odoo-btn-primary">{{ $partner->exists ? 'Simpan' : 'Tambah' }}</button>
        </div>
    </form>
@endsection
