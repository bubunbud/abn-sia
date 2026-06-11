@extends('layouts.odoo')

@section('title', 'Period Closing')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-semibold">Period Closing</h1>
            <p class="text-sm text-gray-500">
                Tutup periode fiskal bulanan — Tahun {{ $year }}
                @if (config('app.company_name'))
                    · {{ config('app.company_name') }}
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('accounting.period-closing.generate') }}">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">
            <button type="submit" class="odoo-btn-secondary">
                Generate Periode {{ $year }}
            </button>
        </form>
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
            <button type="submit" class="odoo-btn-primary">Tampilkan</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Total Periode</div>
            <div class="font-mono font-semibold text-odoo-purple">{{ $summary['total'] }}</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Terbuka</div>
            <div class="font-mono font-semibold text-green-700">{{ $summary['open'] }}</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Ditutup</div>
            <div class="font-mono font-semibold text-gray-700">{{ $summary['closed'] }}</div>
        </div>
    </div>

    @if ($periods->isEmpty())
        <div class="bg-white rounded border border-odoo-border shadow-sm p-8 text-center">
            <p class="text-gray-600 mb-3">Belum ada periode fiskal untuk tahun {{ $year }}.</p>
            <form method="POST" action="{{ route('accounting.period-closing.generate') }}" class="inline">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <button type="submit" class="odoo-btn-primary">Generate 12 Periode</button>
            </form>
        </div>
    @else
        <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
            <table class="odoo-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Nama</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th class="text-right">Jurnal</th>
                        <th class="text-right">Draft</th>
                        <th>Trial Balance</th>
                        <th>Ditutup</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($periods as $row)
                        @php
                            $fp = $row['period'];
                            $stats = $row['journal_stats'];
                        @endphp
                        <tr>
                            <td class="font-mono">{{ $fp->period }}</td>
                            <td class="font-medium">{{ $fp->name }}</td>
                            <td class="whitespace-nowrap text-xs text-gray-600">
                                {{ $fp->start_date->format('d M Y') }} — {{ $fp->end_date->format('d M Y') }}
                            </td>
                            <td>
                                @if ($fp->isClosed())
                                    <span class="text-xs px-2 py-1 rounded bg-gray-200 text-gray-700">Ditutup</span>
                                @else
                                    <span class="text-xs px-2 py-1 rounded bg-green-100 text-green-800">Terbuka</span>
                                @endif
                            </td>
                            <td class="text-right font-mono">{{ $stats['posted'] }}/{{ $stats['total'] }}</td>
                            <td class="text-right font-mono {{ $stats['draft'] > 0 ? 'text-red-600 font-semibold' : '' }}">
                                {{ $stats['draft'] }}
                            </td>
                            <td>
                                @if ($row['trial_balanced'])
                                    <span class="text-xs text-green-700">✓ Seimbang</span>
                                @else
                                    <span class="text-xs text-yellow-700">⚠ Belum seimbang</span>
                                @endif
                            </td>
                            <td class="text-xs text-gray-500 whitespace-nowrap">
                                {{ $fp->closed_at?->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if ($fp->isOpen())
                                    @if ($row['can_close'])
                                        <form method="POST" action="{{ route('accounting.period-closing.close', $fp) }}" class="inline"
                                            onsubmit="return confirm('Tutup periode {{ $fp->name }}? Jurnal pada periode ini tidak dapat diubah.')">
                                            @csrf
                                            <button type="submit" class="odoo-btn-primary text-xs px-2 py-1">Tutup</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400" title="{{ implode(' | ', $row['close_issues']) }}">
                                            Tidak siap
                                        </span>
                                    @endif
                                @elseif ($row['can_reopen'])
                                    <form method="POST" action="{{ route('accounting.period-closing.reopen', $fp) }}" class="inline"
                                        onsubmit="return confirm('Buka kembali periode {{ $fp->name }}?')">
                                        @csrf
                                        <button type="submit" class="odoo-btn-secondary text-xs px-2 py-1">Buka</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @if ($fp->isOpen() && ! $row['can_close'] && ! empty($row['close_issues']))
                            <tr class="bg-yellow-50/50">
                                <td colspan="9" class="text-xs text-yellow-800 py-2 px-4">
                                    <strong>Belum bisa ditutup:</strong>
                                    {{ implode(' · ', $row['close_issues']) }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 bg-gray-50 rounded border border-odoo-border p-4 text-xs text-gray-600 space-y-1">
            <p class="font-semibold text-gray-700">Ketentuan penutupan periode:</p>
            <ul class="list-disc pl-4 space-y-0.5">
                <li>Periode sebelumnya harus sudah ditutup (kecuali Periode 1)</li>
                <li>Tidak boleh ada jurnal berstatus Draft pada periode tersebut</li>
                <li>Trial Balance periode harus seimbang</li>
                <li>Setelah ditutup, jurnal pada periode tersebut tidak dapat dibuat, diubah, atau diposting</li>
                <li>Membuka kembali periode hanya boleh jika periode berikutnya masih terbuka</li>
            </ul>
        </div>
    @endif
@endsection
