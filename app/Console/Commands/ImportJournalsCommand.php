<?php

namespace App\Console\Commands;

use App\Services\JournalImportService;
use App\Services\JournalImportSetupService;
use Illuminate\Console\Command;

class ImportJournalsCommand extends Command
{
    protected $signature = 'journals:import
        {file? : Path ke file Excel jurnal historis}
        {--replace : Hapus semua jurnal sebelum impor}
        {--force : Timpa jurnal dengan No Bukti yang sama}
        {--dry-run : Validasi file tanpa menyimpan}
        {--generate-periods : Generate periode fiskal otomatis untuk tahun dalam file}';

    protected $description = 'Import jurnal historis lengkap dari file Excel (sheet Jurnal)';

    public function handle(
        JournalImportService $importService,
        JournalImportSetupService $setupService,
    ): int {
        $file = $this->argument('file') ?? $setupService->resolveDefaultFile();

        if (! $file || ! file_exists($file)) {
            $this->error('File jurnal tidak ditemukan.');
            $this->line('Letakkan file Excel di salah satu lokasi berikut:');
            foreach ($setupService->defaultFileCandidates() as $candidate) {
                $this->line("  - {$candidate}");
            }
            $this->line('Atau berikan path sebagai argumen: php artisan journals:import "C:\\path\\to\\file.xlsx"');

            return self::FAILURE;
        }

        $prerequisites = $setupService->prerequisites();
        $this->info("Mengimpor jurnal dari: {$file}");
        $this->table(['Prasyarat', 'Jumlah'], [
            ['Akun detail aktif', $prerequisites['accounts']],
            ['Pihak kedua aktif', $prerequisites['partners']],
            ['Tipe jurnal', $prerequisites['journal_types']],
            ['Jurnal existing', $prerequisites['existing_journals']],
        ]);

        if ($prerequisites['accounts'] === 0) {
            $this->error('COA belum diimpor. Jalankan: php artisan coa:import');

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            $this->warn('Mode replace: semua jurnal existing akan dihapus.');
        }

        if ($this->option('generate-periods')) {
            $this->info('Periode fiskal akan digenerate otomatis untuk tahun dalam file.');
        }

        try {
            $stats = $importService->import(
                $file,
                replace: $this->option('replace'),
                skipExisting: ! $this->option('force'),
                dryRun: $this->option('dry-run'),
                generatePeriods: $this->option('generate-periods'),
            );

            if (! empty($stats['meta'])) {
                $meta = $stats['meta'];
                $this->newLine();
                $this->line('Ringkasan file:');
                $this->table(['Info', 'Nilai'], [
                    ['Baris sumber', $meta['source_lines'] ?? 0],
                    ['Rentang tanggal', ($meta['date_from'] ?? '—') . ' s/d ' . ($meta['date_to'] ?? '—')],
                    ['Tahun', implode(', ', $meta['years'] ?? [])],
                    ['No Bukti otomatis', $meta['entries_auto_number'] ?? 0],
                    ['Tanpa periode fiskal', $meta['without_fiscal_period'] ?? 0],
                ]);
            }

            if ($this->option('dry-run')) {
                $this->info('Validasi selesai (dry-run, tidak ada data disimpan).');
                $this->table(['Metrik', 'Jumlah'], [
                    ['Siap diimpor', $stats['entries_ready'] ?? 0],
                    ['Baris detail', $stats['lines_ready'] ?? 0],
                    ['Gagal validasi', $stats['entries_failed'] ?? 0],
                ]);
            } else {
                $this->table(['Metrik', 'Jumlah'], [
                    ['Jurnal diimpor', $stats['entries_imported']],
                    ['Baris detail', $stats['lines_imported']],
                    ['Dilewati (duplikat)', $stats['entries_skipped']],
                    ['Periode fiskal baru', $stats['periods_generated'] ?? 0],
                    ['Gagal', $stats['entries_failed']],
                ]);
                $this->info('Import jurnal selesai.');
            }

            if (! empty($stats['errors'])) {
                $this->newLine();
                $this->warn('Error detail:');
                foreach (array_slice($stats['errors'], 0, 20) as $error) {
                    $this->line("  - {$error}");
                }

                if (count($stats['errors']) > 20) {
                    $this->line('  ... dan ' . (count($stats['errors']) - 20) . ' error lainnya.');
                }
            }

            return ($stats['entries_failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Import gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
