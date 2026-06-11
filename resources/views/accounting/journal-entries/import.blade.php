@extends('layouts.odoo')

@section('title', 'Import Jurnal Historis')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold">Import Jurnal Historis</h1>
        <p class="text-sm text-gray-500">
            Upload file Excel dengan sheet <strong>Jurnal</strong> (format SIA: flat-line per baris akun)
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="mb-4 p-3 rounded bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">
            {{ session('warning') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
            {{ session('error') }}
        </div>
    @endif

    @if (session('import_errors'))
        <div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-sm">
            <div class="font-semibold text-red-800 mb-2">Detail error:</div>
            <ul class="list-disc pl-5 text-red-700 space-y-1 max-h-48 overflow-y-auto">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('import_stats'))
        @php $stats = session('import_stats'); @endphp
        <div class="mb-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            @if (isset($stats['entries_ready']))
                <div class="bg-white rounded border border-odoo-border p-3 text-sm">
                    <div class="text-xs text-gray-500">Siap diimpor</div>
                    <div class="font-mono font-semibold text-odoo-purple">{{ $stats['entries_ready'] }}</div>
                </div>
            @else
                <div class="bg-white rounded border border-odoo-border p-3 text-sm">
                    <div class="text-xs text-gray-500">Jurnal diimpor</div>
                    <div class="font-mono font-semibold text-odoo-purple">{{ $stats['entries_imported'] ?? 0 }}</div>
                </div>
                <div class="bg-white rounded border border-odoo-border p-3 text-sm">
                    <div class="text-xs text-gray-500">Baris detail</div>
                    <div class="font-mono font-semibold">{{ $stats['lines_imported'] ?? 0 }}</div>
                </div>
                <div class="bg-white rounded border border-odoo-border p-3 text-sm">
                    <div class="text-xs text-gray-500">Dilewati</div>
                    <div class="font-mono font-semibold">{{ $stats['entries_skipped'] ?? 0 }}</div>
                </div>
            @endif
            <div class="bg-white rounded border border-odoo-border p-3 text-sm">
                <div class="text-xs text-gray-500">Gagal</div>
                <div class="font-mono font-semibold text-red-600">{{ $stats['entries_failed'] ?? 0 }}</div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded border border-odoo-border shadow-sm p-6 max-w-2xl">
        <div class="mb-4 p-3 rounded border border-odoo-border bg-odoo-purple/5 flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-700">
                <span class="font-medium">Belum punya file?</span>
                Unduh template Excel dengan format kolom dan contoh data jurnal.
            </div>
            <a href="{{ route('accounting.journal-entries.import.template') }}" class="odoo-btn-secondary whitespace-nowrap">
                ↓ Download Template Excel
            </a>
        </div>

        <form method="POST" action="{{ route('accounting.journal-entries.import.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">File Excel</label>
                <input type="file" name="file" accept=".xlsx,.xls" required
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
                <p class="text-xs text-gray-500 mt-1">Format: .xlsx / .xls, maks. 20 MB</p>
                @error('file')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="dry_run" value="1" class="rounded border-odoo-border text-odoo-purple">
                    Validasi saja (dry-run, tidak menyimpan)
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="replace" value="1" class="rounded border-odoo-border text-odoo-purple">
                    Hapus semua jurnal existing sebelum impor
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="force" value="1" class="rounded border-odoo-border text-odoo-purple">
                    Timpa jurnal dengan No Bukti yang sama
                </label>
            </div>

            <div class="bg-gray-50 rounded border border-odoo-border p-3 text-xs text-gray-600 space-y-1">
                <p class="font-semibold text-gray-700">Prasyarat:</p>
                <ul class="list-disc pl-4 space-y-0.5">
                    <li>COA sudah diimpor (<code>php artisan coa:import</code>)</li>
                    <li>Pihak Kedua sudah diimpor (<code>php artisan partners:import</code>)</li>
                    <li>Sheet bernama <strong>Jurnal</strong> dengan kolom Tipe, Tanggal, No Bukti, Akun, Debet, Kredit</li>
                    <li>Jurnal historis langsung berstatus <strong>Posted</strong></li>
                </ul>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="odoo-btn-primary">Import</button>
                <a href="{{ route('accounting.journal-entries.index') }}" class="odoo-btn-secondary">Kembali</a>
            </div>
        </form>
    </div>
@endsection
