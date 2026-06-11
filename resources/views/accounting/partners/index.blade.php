@extends('layouts.odoo')

@section('title', 'Pihak Kedua')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-semibold">Pihak Kedua</h1>
            <p class="text-sm text-gray-500">Master kode pihak kedua — PDL (Piutang), HDL (Hutang) · {{ $partners->total() }} data</p>
        </div>
        <a href="{{ route('accounting.partners.create') }}" class="odoo-btn-primary">+ Tambah</a>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm mb-4 p-3">
        <form method="GET" class="flex flex-wrap gap-2 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs text-gray-500">Cari</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Kode atau nama..."
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500">Status</label>
                <select name="type" class="border border-odoo-border rounded px-3 py-2 text-sm">
                    <option value="">Semua</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="odoo-btn-secondary">Filter</button>
        </form>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
        <table class="odoo-table w-full">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Keterangan</th>
                    <th>Status</th>
                    <th>Aktif</th>
                    <th class="w-20">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($partners as $partner)
                    <tr>
                        <td class="font-mono text-sm">{{ $partner->code }}</td>
                        <td>{{ $partner->name }}</td>
                        <td>{{ $partner->region ?? '—' }}</td>
                        <td>
                            <span class="text-xs px-1.5 py-0.5 rounded {{ $partner->type === 'customer' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700' }}">
                                {{ $partner->status_label ?? $partner->typeLabel() }}
                            </span>
                        </td>
                        <td>
                            <span class="{{ $partner->is_active ? 'text-green-600' : 'text-gray-400' }} text-xs">
                                {{ $partner->is_active ? 'Ya' : 'Tidak' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('accounting.partners.edit', $partner) }}" class="text-xs odoo-link">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-gray-500 py-8">Belum ada data Pihak Kedua.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-odoo-border">
            {{ $partners->links() }}
        </div>
    </div>
@endsection
