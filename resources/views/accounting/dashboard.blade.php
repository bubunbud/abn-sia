@extends('layouts.odoo')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold">Dashboard Accounting</h1>
        <p class="text-sm text-gray-500">Ringkasan aktivitas akuntansi</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded border border-odoo-border p-4">
            <div class="text-sm text-gray-500">Total Jurnal</div>
            <div class="text-2xl font-bold text-odoo-purple">{{ $stats['total_entries'] }}</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-4">
            <div class="text-sm text-gray-500">Draft</div>
            <div class="text-2xl font-bold text-yellow-600">{{ $stats['draft_entries'] }}</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-4">
            <div class="text-sm text-gray-500">Posted</div>
            <div class="text-2xl font-bold text-green-600">{{ $stats['posted_entries'] }}</div>
        </div>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm">
        <div class="px-4 py-3 border-b border-odoo-border font-medium">Jurnal Terbaru</div>
        <table class="odoo-table w-full">
            <thead>
                <tr>
                    <th>No Bukti</th>
                    <th>Tanggal</th>
                    <th>Tipe</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentEntries as $entry)
                    <tr onclick="window.location='{{ route('accounting.journal-entries.show', $entry) }}'">
                        <td><a href="{{ route('accounting.journal-entries.show', $entry) }}" class="odoo-link" onclick="event.stopPropagation()">{{ $entry->entry_number }}</a></td>
                        <td>{{ $entry->entry_date->format('d M Y') }}</td>
                        <td>{{ $entry->journalType->name }}</td>
                        <td>
                            <span class="{{ $entry->isPosted() ? 'odoo-badge-posted' : 'odoo-badge-draft' }}">
                                {{ $entry->status->label() }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-gray-500 py-6">Belum ada jurnal.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
