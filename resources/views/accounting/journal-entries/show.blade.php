@extends('layouts.odoo')

@section('title', $entry->entry_number)

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-semibold">{{ $entry->entry_number }}</h1>
                <span class="{{ $entry->isPosted() ? 'odoo-badge-posted' : 'odoo-badge-draft' }}">
                    {{ $entry->status->label() }}
                </span>
            </div>
            <p class="text-sm text-gray-500">{{ $entry->journalType->name }} — {{ $entry->entry_date->format('d M Y') }}</p>
        </div>
        <div class="flex gap-2 items-center">
            @if (! $entry->isPosted())
                <a href="{{ route('accounting.journal-entries.edit', $entry) }}" class="odoo-btn-secondary">Edit</a>
                <form method="POST" action="{{ route('accounting.journal-entries.post', $entry) }}">
                    @csrf
                    <button type="submit" class="odoo-btn-primary" onclick="return confirm('Post jurnal ini?')">Post</button>
                </form>
            @elseif ($canUnpost)
                <form method="POST" action="{{ route('accounting.journal-entries.unpost', $entry) }}"
                    onsubmit="return confirm('Kembalikan jurnal ini ke Draft?\n\nJurnal akan dikeluarkan dari General Ledger, Trial Balance, dan semua laporan keuangan sampai diposting ulang.')">
                    @csrf
                    <button type="submit" class="odoo-btn-secondary border-red-300 text-red-700 hover:bg-red-50">
                        Kembalikan ke Draft
                    </button>
                </form>
            @endif
            <a href="{{ route('accounting.journal-entries.index') }}" class="odoo-btn-secondary">Kembali</a>
        </div>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm mb-4 p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
        <div>
            <div class="text-gray-500 text-xs uppercase">Periode</div>
            <div class="font-medium">{{ $entry->period ? 'Periode ' . $entry->period : '—' }}</div>
        </div>
        <div>
            <div class="text-gray-500 text-xs uppercase">No Doc / Giro</div>
            <div class="font-medium">{{ $entry->document_number ?? '—' }}</div>
        </div>
        <div>
            <div class="text-gray-500 text-xs uppercase">Pihak Kedua</div>
            <div class="font-medium">{{ $entry->partner?->displayName() ?? '—' }}</div>
        </div>
        <div>
            <div class="text-gray-500 text-xs uppercase">Kurs</div>
            <div class="font-medium">{{ number_format($entry->exchange_rate, 2, ',', '.') }}</div>
        </div>
        <div>
            <div class="text-gray-500 text-xs uppercase">No Bukti</div>
            <div class="font-medium">
                {{ $entry->entry_number }}
                @if ($entry->is_manual_number)
                    <span class="text-xs text-gray-400">(manual)</span>
                @endif
            </div>
        </div>
        @if ($entry->description || $entry->notes)
            <div class="md:col-span-2">
                <div class="text-gray-500 text-xs uppercase">Deskripsi</div>
                <div class="whitespace-pre-line">{{ $entry->description ?? $entry->notes }}</div>
            </div>
        @endif
        @if ($entry->isPosted() && $entry->posted_at)
            <div>
                <div class="text-gray-500 text-xs uppercase">Diposting</div>
                <div class="font-medium">{{ $entry->posted_at->format('d M Y H:i') }}</div>
            </div>
        @endif
    </div>

    @if ($entry->isPosted() && ! $canUnpost && $unpostBlockedReason)
        <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800">
            {{ $unpostBlockedReason }}
        </div>
    @endif

    <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-odoo-border font-medium">Baris Jurnal</div>
        <table class="odoo-table w-full">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kode Akun</th>
                    <th>Nama Akun</th>
                    <th>Akun Lawan</th>
                    <th>Deskripsi</th>
                    <th class="text-right">Debet</th>
                    <th class="text-right">Kredit</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entry->lines as $line)
                    <tr>
                        <td>{{ $line->line_order }}</td>
                        <td>
                            <a href="{{ route('accounting.general-ledger.index', [
                                'account_id' => $line->account_id,
                                'date_from' => $entry->entry_date->copy()->startOfMonth()->toDateString(),
                                'date_to' => $entry->entry_date->copy()->endOfMonth()->toDateString(),
                                'highlight_entry' => $entry->id,
                            ]) }}" class="odoo-link">
                                {{ $line->account->code }}
                            </a>
                        </td>
                        <td>{{ $line->account->name }}</td>
                        <td>{{ $line->counterAccount?->code ?? '—' }}</td>
                        <td>{{ $line->description ?? '—' }}</td>
                        <td class="text-right font-mono">{{ $line->debit > 0 ? number_format($line->debit, 2, ',', '.') : '—' }}</td>
                        <td class="text-right font-mono">{{ $line->credit > 0 ? number_format($line->credit, 2, ',', '.') : '—' }}</td>
                        <td>
                            <a href="{{ route('accounting.general-ledger.index', [
                                'account_id' => $line->account_id,
                                'date_from' => $entry->entry_date->copy()->startOfMonth()->toDateString(),
                                'date_to' => $entry->entry_date->copy()->endOfMonth()->toDateString(),
                                'highlight_entry' => $entry->id,
                            ]) }}" class="text-xs odoo-link">Lihat di GL →</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td colspan="5" class="text-right px-3 py-2">Total</td>
                    <td class="text-right font-mono px-3 py-2">{{ number_format($entry->totalDebit(), 2, ',', '.') }}</td>
                    <td class="text-right font-mono px-3 py-2">{{ number_format($entry->totalCredit(), 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection
