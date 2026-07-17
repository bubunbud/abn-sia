@extends('layouts.odoo')

@section('title', 'Balance Sheet')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold">Neraca (Balance Sheet)</h1>
        <p class="text-sm text-gray-500">
            Ringkasan neraca per bulan — Tahun {{ $year }} (kolom {{ $year - 1 }} = saldo akhir tahun sebelumnya)
            @if (config('app.company_name'))
                · {{ config('app.company_name') }}
            @endif
        </p>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm mb-4 p-4">
        <form method="GET" action="{{ route('accounting.reports.balance-sheet') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500">Tahun</label>
                <select name="year" class="border border-odoo-border rounded px-3 py-2 text-sm">
                    @foreach ($yearOptions as $y)
                        <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="hide_zero" value="1" id="hide_zero" @checked($hideZero)
                    class="rounded border-odoo-border text-odoo-purple focus:ring-odoo-purple">
                <label for="hide_zero" class="text-sm text-gray-600">Sembunyikan baris tanpa saldo</label>
            </div>
            <button type="submit" class="odoo-btn-primary">Tampilkan</button>
            <button type="submit" formaction="{{ route('accounting.reports.balance-sheet.export') }}"
                class="odoo-btn-secondary" title="Export 2 sheet: BS dan BS Detail">
                Export Excel
            </button>
        </form>
        <p class="text-xs text-gray-500 mt-2">Export Excel berisi sheet <strong>BS</strong> (ringkasan) dan <strong>BS Detail</strong> (per akun).</p>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-3">
        @if ($report['is_balanced'])
            <span class="text-xs px-2 py-1 rounded bg-green-100 text-green-800">✓ TOTAL AKTIVA = TOTAL PASSIVA</span>
        @else
            <span class="text-xs px-2 py-1 rounded bg-yellow-100 text-yellow-800">
                ⚠ Aset dan Passiva belum seimbang (periksa jurnal & saldo laba rugi)
            </span>
        @endif
        <span class="text-xs text-gray-500">Geser horizontal untuk melihat semua periode</span>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
        <table class="odoo-table w-full text-sm min-w-[1200px]">
            <thead>
                <tr>
                    <th class="sticky left-0 bg-gray-50 z-10 min-w-[280px]">Uraian</th>
                    @foreach ($report['columns'] as $column)
                        <th class="text-right whitespace-nowrap min-w-[120px]">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    @php
                        $isSection = $row['type'] === 'section';
                        $rowClass = match ($row['type']) {
                            'section' => 'bg-odoo-purple/10 font-bold text-odoo-purple uppercase',
                            'total' => 'bg-gray-100 font-bold uppercase',
                            'subtotal' => 'bg-gray-50 font-semibold',
                            default => '',
                        };
                        $labelClass = match ($row['type']) {
                            'section', 'total', 'subtotal' => '',
                            default => 'pl-6',
                        };
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="sticky left-0 bg-inherit z-10 {{ $labelClass }}">
                            {{ $row['label'] }}
                        </td>
                        @foreach ($report['columns'] as $column)
                            @php
                                $amount = $row['amounts'][$column['key']] ?? null;
                            @endphp
                            <td class="text-right font-mono whitespace-nowrap">
                                @if ($isSection)
                                    —
                                @elseif ($amount === null || abs($amount) < 0.01)
                                    —
                                @else
                                    {{ number_format($amount, 2, ',', '.') }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
        @php $lastCol = collect($report['columns'])->last(); @endphp
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Total Aktiva ({{ $lastCol['label'] }})</div>
            <div class="font-mono font-semibold text-odoo-purple">
                {{ number_format($report['totals']['assets'][$lastCol['key']] ?? 0, 2, ',', '.') }}
            </div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Total Hutang Lancar</div>
            <div class="font-mono font-semibold">
                {{ number_format($report['totals']['current_liabilities'][$lastCol['key']] ?? 0, 2, ',', '.') }}
            </div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Total Modal</div>
            <div class="font-mono font-semibold">
                {{ number_format($report['totals']['equity'][$lastCol['key']] ?? 0, 2, ',', '.') }}
            </div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Total Passiva</div>
            <div class="font-mono font-semibold">
                {{ number_format($report['totals']['passiva'][$lastCol['key']] ?? 0, 2, ',', '.') }}
            </div>
        </div>
    </div>
@endsection
