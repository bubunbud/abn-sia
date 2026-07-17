@extends('layouts.odoo')

@section('title', 'Journal Entries')

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-semibold">Journal Entries</h1>
        <p class="text-sm text-gray-500">Daftar bukti jurnal — klik No Bukti untuk detail</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('accounting.journal-entries.import.create') }}" class="odoo-btn-secondary">Import Historis</a>
        <a href="{{ route('accounting.journal-entries.create') }}" class="odoo-btn-primary">+ Buat Jurnal</a>
    </div>
</div>

<div class="bg-white rounded border border-odoo-border shadow-sm mb-4 p-3">
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="text-xs text-gray-500">Cari</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="No Bukti, deskripsi..."
                class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500">Tipe Jurnal</label>
            <select name="journal_type_id" class="border border-odoo-border rounded px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach ($journalTypes as $type)
                <option value="{{ $type->id }}" @selected(request('journal_type_id')==$type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500">Status</label>
            <select name="status" class="border border-odoo-border rounded px-3 py-2 text-sm">
                <option value="">Semua</option>
                <option value="draft" @selected(request('status')==='draft' )>Draft</option>
                <option value="posted" @selected(request('status')==='posted' )>Posted</option>
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500">Dari Tanggal</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                class="border border-odoo-border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500">Sampai Tanggal</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                class="border border-odoo-border rounded px-3 py-2 text-sm">
        </div>
        <button type="submit" class="odoo-btn-secondary">Filter</button>
    </form>
</div>

<div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
    <table class="odoo-table w-full">
        <thead>
            <tr>
                <th>No Bukti</th>
                <th>Tanggal</th>
                <th>Tipe Jurnal</th>
                <th>Pihak Kedua</th>
                <th>Keterangan</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Kredit</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
            <tr onclick="window.location='{{ route('accounting.journal-entries.show', $entry) }}'">
                <td>
                    <a href="{{ route('accounting.journal-entries.show', $entry) }}" class="odoo-link font-medium" onclick="event.stopPropagation()">
                        {{ $entry->entry_number }}
                    </a>
                </td>
                <td>{{ $entry->entry_date->format('d M Y') }}</td>
                <td>{{ $entry->journalType->name }}</td>
                <td>{{ $entry->partner?->displayName() ?? '—' }}</td>
                <td class="max-w-xs truncate">{{ $entry->notes ?? $entry->description ?? '—' }}</td>
                <td class="text-right font-mono">{{ number_format($entry->total_debit ?? 0, 2, ',', '.') }}</td>
                <td class="text-right font-mono">{{ number_format($entry->total_credit ?? 0, 2, ',', '.') }}</td>
                <td>
                    <span class="{{ $entry->isPosted() ? 'odoo-badge-posted' : 'odoo-badge-draft' }}">
                        {{ $entry->status->label() }}
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center text-gray-500 py-8">Belum ada jurnal.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-odoo-border">
        {{ $entries->links() }}
    </div>
</div>
@endsection