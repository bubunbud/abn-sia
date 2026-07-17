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
            <a href="{{ route('accounting.general-ledger.summary', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                class="odoo-btn-secondary">
                View All
            </a>
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
        @include('accounting.general-ledger.partials.lines-table', [
            'ledgerLines' => $ledgerLines,
            'emptyMessage' => $selectedAccount
                ? 'Tidak ada transaksi posted pada periode ini.'
                : 'Pilih akun untuk menampilkan buku besar.',
        ])
    </div>
@endsection
