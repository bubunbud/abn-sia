@extends('layouts.odoo')

@section('title', 'Dashboard')

@section('content')
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-semibold">Dashboard Accounting</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                @if (auth()->user())
                    Selamat datang, <span class="font-medium text-gray-700">{{ auth()->user()->name }}</span>
                @endif
                @if (config('app.company_name'))
                    · {{ config('app.company_name') }}
                @endif
            </p>
        </div>
        <div class="text-right text-sm">
            <div class="text-gray-500">{{ $current['date']->translatedFormat('l, d F Y') }}</div>
            @if ($current['fiscal_period'])
                <div class="mt-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                        {{ $current['fiscal_period']->isOpen() ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                        {{ $current['fiscal_period']->name }}
                        · {{ $current['fiscal_period']->isOpen() ? 'Terbuka' : 'Tertutup' }}
                    </span>
                </div>
            @else
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">
                    Periode belum digenerate
                </span>
            @endif
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('accounting.journal-entries.create') }}" class="odoo-btn-primary text-sm">+ Buat Jurnal</a>
        <a href="{{ route('accounting.trial-balance.index', ['year' => $current['year'], 'period' => $current['period']]) }}" class="odoo-btn-secondary text-sm">Trial Balance</a>
        <a href="{{ route('accounting.reports.balance-sheet', ['year' => $current['year']]) }}" class="odoo-btn-secondary text-sm">Balance Sheet</a>
        <a href="{{ route('accounting.reports.profit-loss', ['year' => $current['year']]) }}" class="odoo-btn-secondary text-sm">Profit & Loss</a>
        <a href="{{ route('accounting.period-closing.index', ['year' => $current['year']]) }}" class="odoo-btn-secondary text-sm">Period Closing</a>
    </div>

    {{-- Alerts --}}
    @foreach ($alerts as $alert)
        <div class="mb-3 px-4 py-3 rounded text-sm border flex items-start justify-between gap-3
            {{ $alert['type'] === 'danger' ? 'bg-red-50 border-red-200 text-red-800' : '' }}
            {{ $alert['type'] === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-900' : '' }}
            {{ $alert['type'] === 'info' ? 'bg-blue-50 border-blue-200 text-blue-800' : '' }}">
            <span>{{ $alert['message'] }}</span>
            @if (! empty($alert['url']))
                <a href="{{ $alert['url'] }}" class="shrink-0 font-medium underline whitespace-nowrap">Lihat →</a>
            @endif
        </div>
    @endforeach

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded border border-odoo-border p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Total Jurnal</div>
            <div class="text-2xl font-bold text-odoo-purple mt-1">{{ number_format($stats['total_entries']) }}</div>
            <div class="text-xs text-gray-400 mt-1">Semua periode</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Draft</div>
            <div class="text-2xl font-bold text-yellow-600 mt-1">{{ number_format($stats['draft_entries']) }}</div>
            <div class="text-xs text-gray-400 mt-1">Belum diposting</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Posted</div>
            <div class="text-2xl font-bold text-green-600 mt-1">{{ number_format($stats['posted_entries']) }}</div>
            <div class="text-xs text-gray-400 mt-1">Sudah diposting</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Akun Aktif</div>
            <div class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($stats['accounts']) }}</div>
            <div class="text-xs text-gray-400 mt-1">Chart of Accounts</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-4 col-span-2 md:col-span-1">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Pihak Kedua</div>
            <div class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($stats['partners']) }}</div>
            <div class="text-xs text-gray-400 mt-1">Partner aktif</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        {{-- Periode berjalan --}}
        <div class="bg-white rounded border border-odoo-border shadow-sm p-4">
            <h2 class="font-medium text-sm mb-3">Periode Berjalan</h2>
            @if ($current['fiscal_period'])
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Periode</dt>
                        <dd class="font-medium">{{ $current['fiscal_period']->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Jurnal</dt>
                        <dd class="font-mono">{{ $current['journal_stats']['posted'] }} posted / {{ $current['journal_stats']['total'] }} total</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Draft</dt>
                        <dd class="font-mono {{ $current['journal_stats']['draft'] > 0 ? 'text-yellow-600 font-semibold' : '' }}">
                            {{ $current['journal_stats']['draft'] }}
                        </dd>
                    </div>
                    @if ($current['trial_balance'])
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Trial Balance</dt>
                            <dd>
                                @if ($current['trial_balance']['is_balanced'])
                                    <span class="text-green-700 font-medium">Seimbang</span>
                                @else
                                    <span class="text-red-600 font-medium">Tidak seimbang</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Mutasi Debet</dt>
                            <dd class="font-mono text-sm">{{ number_format($current['trial_balance']['period_debit'], 2, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Mutasi Kredit</dt>
                            <dd class="font-mono text-sm">{{ number_format($current['trial_balance']['period_credit'], 2, ',', '.') }}</dd>
                        </div>
                    @endif
                </dl>
            @else
                <p class="text-sm text-gray-500">Periode fiskal untuk bulan ini belum tersedia.</p>
                <a href="{{ route('accounting.period-closing.index') }}" class="odoo-link text-sm mt-2 inline-block">Generate periode →</a>
            @endif
        </div>

        {{-- Ringkasan tahun fiskal --}}
        <div class="bg-white rounded border border-odoo-border shadow-sm p-4 lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-medium text-sm">Periode Fiskal {{ $fiscalOverview['year'] }}</h2>
                <a href="{{ route('accounting.period-closing.index', ['year' => $fiscalOverview['year']]) }}" class="odoo-link text-xs">Kelola →</a>
            </div>
            @if ($fiscalOverview['total'] > 0)
                <div class="flex flex-wrap gap-3 mb-3 text-xs text-gray-500">
                    <span>{{ $fiscalOverview['open'] }} terbuka</span>
                    <span>·</span>
                    <span>{{ $fiscalOverview['closed'] }} ditutup</span>
                </div>
                <div class="grid grid-cols-6 sm:grid-cols-12 gap-1.5">
                    @foreach ($fiscalOverview['periods'] as $fp)
                        <a href="{{ route('accounting.period-closing.index', ['year' => $fiscalOverview['year']]) }}"
                            title="{{ $fp['name'] }} — {{ $fp['status'] === 'open' ? 'Terbuka' : 'Tertutup' }}"
                            class="flex flex-col items-center p-1.5 rounded border text-center text-xs transition
                                {{ $fp['is_current'] ? 'border-odoo-purple bg-purple-50 ring-1 ring-odoo-purple/30' : 'border-odoo-border hover:bg-gray-50' }}">
                            <span class="font-mono font-semibold {{ $fp['is_current'] ? 'text-odoo-purple' : 'text-gray-700' }}">
                                {{ str_pad($fp['period'], 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <span class="w-2 h-2 rounded-full mt-1 {{ $fp['status'] === 'open' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500">Belum ada periode fiskal untuk tahun {{ $fiscalOverview['year'] }}.</p>
                <form method="POST" action="{{ route('accounting.period-closing.generate') }}" class="mt-3">
                    @csrf
                    <input type="hidden" name="year" value="{{ $fiscalOverview['year'] }}">
                    <button type="submit" class="odoo-btn-secondary text-xs">Generate 12 Periode {{ $fiscalOverview['year'] }}</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Jurnal terbaru --}}
    <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
        <div class="flex items-center justify-between px-4 py-3 border-b border-odoo-border">
            <span class="font-medium">Jurnal Terbaru</span>
            <a href="{{ route('accounting.journal-entries.index') }}" class="odoo-link text-sm">Lihat semua →</a>
        </div>
        <table class="odoo-table w-full">
            <thead>
                <tr>
                    <th>No Bukti</th>
                    <th>Tanggal</th>
                    <th>Tipe</th>
                    <th>Deskripsi</th>
                    <th class="text-right">Jumlah</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentEntries as $entry)
                    <tr onclick="window.location='{{ route('accounting.journal-entries.show', $entry) }}'">
                        <td>
                            <a href="{{ route('accounting.journal-entries.show', $entry) }}" class="odoo-link font-mono text-sm"
                                onclick="event.stopPropagation()">{{ $entry->entry_number }}</a>
                        </td>
                        <td class="whitespace-nowrap">{{ $entry->entry_date->format('d M Y') }}</td>
                        <td>{{ $entry->journalType->name }}</td>
                        <td class="max-w-[200px] truncate text-gray-600">{{ $entry->description ?? '—' }}</td>
                        <td class="text-right font-mono text-sm whitespace-nowrap">
                            {{ number_format($entry->total_debit ?? 0, 2, ',', '.') }}
                        </td>
                        <td>
                            <span class="{{ $entry->isPosted() ? 'odoo-badge-posted' : 'odoo-badge-draft' }}">
                                {{ $entry->status->label() }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-gray-500 py-8">
                            Belum ada jurnal.
                            <a href="{{ route('accounting.journal-entries.create') }}" class="odoo-link">Buat jurnal pertama →</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
