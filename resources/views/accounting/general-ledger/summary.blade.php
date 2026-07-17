@extends('layouts.odoo')

@section('title', 'View All — Semua Akun')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold">View All</h1>
        <p class="text-sm text-gray-500">
            Buku besar detail seluruh akun (COA) —
            {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}
            s/d {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </p>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm mb-4 p-4">
        <form method="GET" action="{{ route('accounting.general-ledger.summary') }}" class="flex flex-wrap gap-3 items-end">
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
            <button type="submit" formaction="{{ route('accounting.general-ledger.export') }}"
                class="odoo-btn-secondary" title="Export buku besar seluruh akun">
                Export Excel
            </button>
            <a href="{{ route('accounting.general-ledger.index', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                class="odoo-btn-secondary">
                GL per Akun
            </a>
        </form>
        <p class="text-xs text-gray-500 mt-2">Export Excel menghasilkan buku besar <strong>semua akun</strong> dalam satu file, dikelompokkan per akun.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-4 text-sm text-gray-600">
        <span><strong>{{ $totals['accounts'] }}</strong> akun</span>
        <span class="text-gray-300">|</span>
        <span><strong>{{ number_format($totals['lines'], 0, ',', '.') }}</strong> baris jurnal</span>
        <span class="text-gray-300">|</span>
        <span>Total Debet: <strong class="font-mono">{{ number_format($totals['debit'], 2, ',', '.') }}</strong></span>
        <span class="text-gray-300">|</span>
        <span>Total Kredit: <strong class="font-mono">{{ number_format($totals['credit'], 2, ',', '.') }}</strong></span>
    </div>

    @forelse ($accountLedgers as $ledger)
        <div id="account-{{ $ledger['account']->code }}" class="mb-6">
            <div class="bg-white rounded-t border border-odoo-border border-b-0 shadow-sm px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span class="font-semibold text-odoo-purple">{{ $ledger['account']->code }}</span>
                    <span class="text-gray-600">— {{ $ledger['account']->name }}</span>
                    <span class="text-xs text-gray-400 ml-2">({{ $ledger['account']->group_name }})</span>
                </div>
                <a href="{{ route('accounting.general-ledger.index', [
                    'account_id' => $ledger['account']->id,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ]) }}" class="odoo-link text-sm">
                    GL per Akun
                </a>
            </div>
            <div class="bg-white rounded-b border border-odoo-border shadow-sm overflow-x-auto">
                @include('accounting.general-ledger.partials.lines-table', [
                    'ledgerLines' => $ledger['lines'],
                    'showFooter' => true,
                    'totalDebit' => $ledger['total_debit'],
                    'totalCredit' => $ledger['total_credit'],
                    'endingBalance' => $ledger['ending_balance'],
                ])
            </div>
        </div>
    @empty
        <div class="bg-white rounded border border-odoo-border shadow-sm p-8 text-center text-gray-500">
            Tidak ada transaksi posted pada periode ini.
        </div>
    @endforelse
@endsection
