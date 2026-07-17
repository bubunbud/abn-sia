@extends('layouts.odoo')

@section('title', 'Import Jurnal')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold">Import Jurnal Data Lengkap</h1>
        <p class="text-sm text-gray-500">
            Upload file Excel historis dengan sheet <strong>Jurnal</strong> (format flat-line SIA)
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="mb-4 p-3 rounded bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">{{ session('warning') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    @if (session('import_errors'))
        <div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-sm">
            <div class="font-semibold text-red-800 mb-2">Detail error ({{ count(session('import_errors')) }} ditampilkan):</div>
            <ul class="list-disc pl-5 text-red-700 space-y-1 max-h-48 overflow-y-auto text-xs font-mono">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Prasyarat --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Akun Detail</div>
            <div class="font-mono font-semibold {{ $prerequisites['accounts'] > 0 ? 'text-green-700' : 'text-red-600' }}">
                {{ number_format($prerequisites['accounts']) }}
            </div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Pihak Kedua</div>
            <div class="font-mono font-semibold {{ $prerequisites['partners'] > 0 ? 'text-green-700' : 'text-yellow-600' }}">
                {{ number_format($prerequisites['partners']) }}
            </div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Tipe Jurnal</div>
            <div class="font-mono font-semibold text-odoo-purple">{{ $prerequisites['journal_types'] }}</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm">
            <div class="text-xs text-gray-500 uppercase">Periode Fiskal</div>
            <div class="font-mono font-semibold">{{ number_format($prerequisites['fiscal_periods']) }}</div>
        </div>
        <div class="bg-white rounded border border-odoo-border p-3 text-sm col-span-2 md:col-span-1">
            <div class="text-xs text-gray-500 uppercase">Jurnal Existing</div>
            <div class="font-mono font-semibold">{{ number_format($prerequisites['existing_journals']) }}</div>
        </div>
    </div>

    @if (! $readyToImport)
        <div class="mb-4 p-3 rounded bg-amber-50 border border-amber-200 text-amber-900 text-sm">
            COA belum diimpor. Jalankan <code class="bg-amber-100 px-1 rounded">php artisan coa:import</code> terlebih dahulu.
        </div>
    @endif

    @if (session('import_stats'))
        @php $stats = session('import_stats'); $meta = $stats['meta'] ?? []; @endphp
        <div class="mb-4 bg-white rounded border border-odoo-border p-4">
            <h2 class="font-medium text-sm mb-3">Hasil {{ isset($stats['entries_ready']) ? 'Validasi' : 'Import' }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                @if (isset($stats['entries_ready']))
                    <div class="text-sm"><span class="text-gray-500">Siap diimpor</span><div class="font-mono font-semibold text-odoo-purple">{{ $stats['entries_ready'] }}</div></div>
                    <div class="text-sm"><span class="text-gray-500">Baris detail</span><div class="font-mono font-semibold">{{ $stats['lines_ready'] ?? 0 }}</div></div>
                @else
                    <div class="text-sm"><span class="text-gray-500">Jurnal diimpor</span><div class="font-mono font-semibold text-odoo-purple">{{ $stats['entries_imported'] ?? 0 }}</div></div>
                    <div class="text-sm"><span class="text-gray-500">Baris detail</span><div class="font-mono font-semibold">{{ $stats['lines_imported'] ?? 0 }}</div></div>
                    <div class="text-sm"><span class="text-gray-500">Dilewati</span><div class="font-mono font-semibold">{{ $stats['entries_skipped'] ?? 0 }}</div></div>
                    @if (($stats['periods_generated'] ?? 0) > 0)
                        <div class="text-sm"><span class="text-gray-500">Periode baru</span><div class="font-mono font-semibold text-green-700">{{ $stats['periods_generated'] }}</div></div>
                    @endif
                @endif
                <div class="text-sm"><span class="text-gray-500">Gagal</span><div class="font-mono font-semibold text-red-600">{{ $stats['entries_failed'] ?? 0 }}</div></div>
            </div>
            @if (! empty($meta))
                <div class="text-xs text-gray-600 border-t border-odoo-border pt-3 grid grid-cols-1 md:grid-cols-3 gap-2">
                    <div>Baris sumber: <strong>{{ $meta['source_lines'] ?? 0 }}</strong></div>
                    <div>Rentang: <strong>{{ $meta['date_from'] ?? '—' }}</strong> s/d <strong>{{ $meta['date_to'] ?? '—' }}</strong></div>
                    <div>Tahun: <strong>{{ implode(', ', $meta['years'] ?? []) }}</strong></div>
                    @if (($meta['entries_auto_number'] ?? 0) > 0)
                        <div>No Bukti otomatis: <strong>{{ $meta['entries_auto_number'] }}</strong> jurnal</div>
                    @endif
                    @if (($meta['without_fiscal_period'] ?? 0) > 0)
                        <div class="md:col-span-3 text-amber-700">
                            {{ $meta['without_fiscal_period'] }} jurnal belum terhubung periode fiskal — centang "Generate periode fiskal otomatis".
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white rounded border border-odoo-border shadow-sm p-6">
            <form method="POST" action="{{ route('accounting.journal-entries.import.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">File Excel Jurnal</label>
                    <input type="file" name="file" accept=".xlsx,.xls" required
                        class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
                    <p class="text-xs text-gray-500 mt-1">Format: .xlsx / .xls, maks. 50 MB · Sheet: <strong>Jurnal</strong></p>
                    @error('file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="space-y-2 text-sm">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="dry_run" value="1" class="rounded border-odoo-border text-odoo-purple" checked>
                        <span><strong>Validasi dulu</strong> (dry-run, tidak menyimpan)</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="generate_periods" value="1" class="rounded border-odoo-border text-odoo-purple" checked>
                        Generate periode fiskal otomatis untuk tahun dalam file
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="force" value="1" class="rounded border-odoo-border text-odoo-purple">
                        Timpa jurnal dengan No Bukti yang sama
                    </label>
                    <label class="flex items-center gap-2 text-red-700">
                        <input type="checkbox" name="replace" value="1" class="rounded border-red-300 text-red-600">
                        Hapus <strong>semua</strong> jurnal existing sebelum impor
                    </label>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="odoo-btn-primary" @disabled(! $readyToImport)>Mulai Import</button>
                    <a href="{{ route('accounting.journal-entries.index') }}" class="odoo-btn-secondary">Kembali</a>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded border border-odoo-border p-4">
                <h3 class="font-medium text-sm mb-2">Download Template</h3>
                <p class="text-xs text-gray-600 mb-3">Template Excel dengan format kolom dan contoh baris jurnal.</p>
                <a href="{{ route('accounting.journal-entries.import.template') }}" class="odoo-btn-secondary text-sm w-full justify-center">
                    ↓ Template Excel
                </a>
            </div>

            <div class="bg-white rounded border border-odoo-border p-4 text-xs text-gray-600 space-y-2">
                <h3 class="font-medium text-sm text-gray-800">Urutan Import Lengkap</h3>
                <ol class="list-decimal pl-4 space-y-1">
                    <li><code>php artisan coa:import</code></li>
                    <li><code>php artisan partners:import</code></li>
                    <li>Upload jurnal (halaman ini)</li>
                </ol>
                <p class="pt-1 border-t border-odoo-border">
                    File default CLI:
                    <code class="block mt-1 bg-gray-50 p-1 rounded break-all">storage/app/imports/jurnal-historis.xlsx</code>
                </p>
                @if ($defaultFile)
                    <p class="text-green-700">✓ File ditemukan: {{ basename($defaultFile) }}</p>
                @endif
            </div>

            <div class="bg-gray-50 rounded border border-odoo-border p-4 text-xs text-gray-600">
                <p class="font-semibold text-gray-700 mb-1">Kolom yang dibaca (sheet Jurnal):</p>
                <p>Tipe · Tanggal · No Bukti (opsional) · No Giro · Pihak Kedua · Kode Akun · Akun Lawan · Deskripsi · Keterangan · Debet · Kredit · Kurs</p>
                <p class="mt-2">Posted to IDR dihitung otomatis (<code>Debet/Kredit × Kurs</code>). Jika No Bukti kosong, nomor digenerate sesuai tipe jurnal.</p>
                <p class="mt-2">Jurnal historis langsung berstatus <strong>Posted</strong>.</p>
            </div>
        </div>
    </div>
@endsection
