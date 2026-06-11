<?php

namespace App\Console\Commands;

use App\Services\JournalImportService;
use Illuminate\Console\Command;

class ImportJournalsCommand extends Command
{
    protected $signature = 'journals:import
        {file? : Path ke file Excel jurnal historis}
        {--replace : Hapus semua jurnal sebelum impor}
        {--force : Timpa jurnal dengan No Bukti yang sama}
        {--dry-run : Validasi file tanpa menyimpan}';

    protected $description = 'Import jurnal historis dari file Excel (sheet Jurnal)';

    public function handle(JournalImportService $importService): int
    {
        $file = $this->argument('file')
            ?? storage_path('app/imports/jurnal-historis.xlsx');

        if (! file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");
            $this->line('Letakkan file Excel di storage/app/imports/jurnal-historis.xlsx atau berikan path sebagai argumen.');

            return self::FAILURE;
        }

        $this->info("Mengimpor jurnal dari: {$file}");

        if ($this->option('replace')) {
            $this->warn('Mode replace: semua jurnal existing akan dihapus.');
        }

        try {
            $stats = $importService->import(
                $file,
                replace: $this->option('replace'),
                skipExisting: ! $this->option('force'),
                dryRun: $this->option('dry-run'),
            );

            if ($this->option('dry-run')) {
                $this->info('Validasi selesai (dry-run, tidak ada data disimpan).');
                $this->table(['Metrik', 'Jumlah'], [
                    ['Siap diimpor', $stats['entries_ready'] ?? 0],
                    ['Gagal validasi', $stats['entries_failed'] ?? 0],
                ]);
            } else {
                $this->table(['Metrik', 'Jumlah'], [
                    ['Jurnal diimpor', $stats['entries_imported']],
                    ['Baris detail', $stats['lines_imported']],
                    ['Dilewati (duplikat)', $stats['entries_skipped']],
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
