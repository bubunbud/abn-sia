@extends('layouts.odoo')

@section('title', 'Trial Balance')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold">Trial Balance</h1>
        <p class="text-sm text-gray-500">
            Neraca saldo per periode — Periode {{ $period }} / {{ $year }}
            ({{ \Carbon\Carbon::parse($report['period_start'])->format('d M Y') }}
            s/d {{ \Carbon\Carbon::parse($report['period_end'])->format('d M Y') }})
        </p>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm mb-4 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500">Tahun</label>
                <select name="year" class="border border-odoo-border rounded px-3 py-2 text-sm">
                    @foreach ($yearOptions as $y)
                        <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Periode</label>
                <select name="period" class="border border-odoo-border rounded px-3 py-2 text-sm">
                    @foreach ($periodOptions as $p)
                        <option value="{{ $p }}" @selected($period == $p)>Periode {{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="hide_zero" value="1" id="hide_zero" @checked($hideZero)
                    class="rounded border-odoo-border text-odoo-purple focus:ring-odoo-purple">
                <label for="hide_zero" class="text-sm text-gray-600">Sembunyikan akun tanpa saldo/mutasi</label>
            </div>
            <button type="submit" class="odoo-btn-primary">Tampilkan</button>
        </form>
    </div>

    <div class="flex items-center gap-3 mb-3">
        @if ($report['is_balanced'])
            <span class="text-xs px-2 py-1 rounded bg-green-100 text-green-800">✓ Trial Balance seimbang</span>
        @else
            <span class="text-xs px-2 py-1 rounded bg-red-100 text-red-800">
                ✗ Trial Balance tidak seimbang (selisih: {{ number_format($report['totals']['closing_balance'], 2, ',', '.') }})
            </span>
        @endif
        <span class="text-xs text-gray-500">{{ $report['rows']->count() }} akun ditampilkan</span>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
        <table class="odoo-table w-full">
            <thead>
                <tr>
                    <th class="w-12">No.</th>
                    <th>Kode Akun</th>
                    <th>Nama Akun</th>
                    <th class="text-right">Saldo Awal</th>
                    <th class="text-right">Mutasi Debet</th>
                    <th class="text-right">Mutasi Kredit</th>
                    <th class="text-right">Saldo Akhir</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        <td class="text-gray-500">{{ $row['no'] }}</td>
                        <td>
                            <a href="{{ route('accounting.general-ledger.index', [
                                'account_id' => $row['account']->id,
                                'date_from' => $report['period_start'],
                                'date_to' => $report['period_end'],
                            ]) }}" class="odoo-link font-mono text-sm">
                                {{ $row['account']->code }}
                            </a>
                        </td>
                        <td>{{ $row['account']->name }}</td>
                        <td class="text-right font-mono {{ $row['opening_balance'] < 0 ? 'text-red-600' : '' }}">
                            {{ number_format($row['opening_balance'], 2, ',', '.') }}
                        </td>
                        <td class="text-right font-mono">
                            {{ $row['period_debit'] > 0 ? number_format($row['period_debit'], 2, ',', '.') : '—' }}
                        </td>
                        <td class="text-right font-mono">
                            {{ $row['period_credit'] > 0 ? number_format($row['period_credit'], 2, ',', '.') : '—' }}
                        </td>
                        <td class="text-right font-mono font-medium {{ $row['closing_balance'] < 0 ? 'text-red-600' : '' }}">
                            {{ number_format($row['closing_balance'], 2, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-gray-500 py-8">
                            Tidak ada data untuk periode ini.
                            @if ($hideZero)
                                Coba nonaktifkan filter "Sembunyikan akun tanpa saldo/mutasi".
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($report['rows']->isNotEmpty())
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td colspan="3" class="text-right px-3 py-2">TOTAL</td>
                        <td class="text-right px-3 py-2 font-mono {{ $report['totals']['opening_balance'] < 0 ? 'text-red-600' : '' }}">
                            {{ number_format($report['totals']['opening_balance'], 2, ',', '.') }}
                        </td>
                        <td class="text-right px-3 py-2 font-mono">
                            {{ number_format($report['totals']['period_debit'], 2, ',', '.') }}
                        </td>
                        <td class="text-right px-3 py-2 font-mono">
                            {{ number_format($report['totals']['period_credit'], 2, ',', '.') }}
                        </td>
                        <td class="text-right px-3 py-2 font-mono {{ $report['totals']['closing_balance'] < 0 ? 'text-red-600' : '' }}">
                            {{ number_format($report['totals']['closing_balance'], 2, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
