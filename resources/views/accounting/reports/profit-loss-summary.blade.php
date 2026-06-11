@extends('layouts.odoo')

@section('title', 'Profit & Loss')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold">Laba Rugi (Profit & Loss)</h1>
        <p class="text-sm text-gray-500">
            Ringkasan laba rugi per bulan — Tahun {{ $year }} (kolom {{ $year - 1 }} = total tahun sebelumnya)
            @if (config('app.company_name'))
                · {{ config('app.company_name') }}
            @endif
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
            <div class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="hide_zero" value="1" id="hide_zero" @checked($hideZero)
                    class="rounded border-odoo-border text-odoo-purple focus:ring-odoo-purple">
                <label for="hide_zero" class="text-sm text-gray-600">Sembunyikan baris tanpa mutasi</label>
            </div>
            <button type="submit" class="odoo-btn-primary">Tampilkan</button>
        </form>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-3">
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
                        $rowClass = match ($row['type']) {
                            'computed', 'total' => 'bg-gray-100 font-bold',
                            'subtotal' => 'bg-gray-50 font-semibold',
                            default => '',
                        };
                        $labelClass = match ($row['type']) {
                            'computed', 'subtotal', 'total' => '',
                            default => 'pl-4',
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
                                @if ($amount === null || abs($amount) < 0.01)
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

    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        @php $lastCol = collect($report['columns'])->last(); @endphp
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Penjualan Bersih ({{ $lastCol['label'] }})</div>
            <div class="font-mono font-semibold text-odoo-purple">
                {{ number_format($report['totals']['net_sales'][$lastCol['key']] ?? 0, 2, ',', '.') }}
            </div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Laba Kotor</div>
            <div class="font-mono font-semibold">
                {{ number_format($report['totals']['gross_profit'][$lastCol['key']] ?? 0, 2, ',', '.') }}
            </div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Laba Bersih</div>
            <div class="font-mono font-semibold">
                {{ number_format($report['totals']['net_income'][$lastCol['key']] ?? 0, 2, ',', '.') }}
            </div>
        </div>
    </div>
@endsection
