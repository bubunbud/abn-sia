@extends('layouts.odoo')

@section('title', 'General Ledger')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold">General Ledger</h1>
        <p class="text-sm text-gray-500">Buku besar per akun — klik No Bukti untuk kembali ke jurnal</p>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm mb-4 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="min-w-[280px]">
                <label class="text-xs text-gray-500">Akun</label>
                <select name="account_id" class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected($selectedAccount?->id == $account->id)>
                            {{ $account->displayName() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Dari Tanggal</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}"
                    class="border border-odoo-border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500">Sampai Tanggal</label>
                <input type="date" name="date_to" value="{{ $dateTo }}"
                    class="border border-odoo-border rounded px-3 py-2 text-sm">
            </div>
            <button type="submit" class="odoo-btn-primary">Tampilkan</button>
        </form>
    </div>

    @if ($selectedAccount)
        <div class="bg-white rounded border border-odoo-border shadow-sm mb-2 px-4 py-3">
            <span class="font-semibold text-odoo-purple">{{ $selectedAccount->code }}</span>
            <span class="text-gray-600">— {{ $selectedAccount->name }}</span>
            <span class="text-xs text-gray-400 ml-2">({{ $selectedAccount->group_name }})</span>
        </div>
    @endif

    <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
        <table class="odoo-table w-full">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No Bukti</th>
                    <th>No Doc</th>
                    <th>Pihak Kedua</th>
                    <th>Deskripsi</th>
                    <th class="text-right">Debet</th>
                    <th class="text-right">Kredit</th>
                    <th class="text-right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ledgerLines as $row)
                    @php $line = $row['line']; @endphp
                    <tr class="{{ $row['highlight'] ? 'bg-yellow-50 ring-1 ring-yellow-300' : '' }}">
                        <td>{{ $line->journalEntry->entry_date->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('accounting.journal-entries.show', $line->journalEntry) }}" class="odoo-link font-medium">
                                {{ $line->journalEntry->entry_number }}
                            </a>
                        </td>
                        <td>{{ $line->journalEntry->document_number ?? '—' }}</td>
                        <td>{{ $line->journalEntry->partner?->displayName() ?? '—' }}</td>
                        <td>{{ $line->description ?? $line->journalEntry->description ?? '—' }}</td>
                        <td class="text-right font-mono">{{ $line->debit > 0 ? number_format($line->debit, 2, ',', '.') : '—' }}</td>
                        <td class="text-right font-mono">{{ $line->credit > 0 ? number_format($line->credit, 2, ',', '.') : '—' }}</td>
                        <td class="text-right font-mono font-medium">{{ number_format($row['balance'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-gray-500 py-8">
                            @if ($selectedAccount)
                                Tidak ada transaksi posted pada periode ini.
                            @else
                                Pilih akun untuk menampilkan buku besar.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
