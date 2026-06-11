<?php

namespace App\Console\Commands;

use App\Services\CoaImportService;
use Illuminate\Console\Command;

class ImportCoaCommand extends Command
{
    protected $signature = 'coa:import {file? : Path ke file Excel COA} {--keep : Jangan hapus COA lama, hanya update/insert}';

    protected $description = 'Import Chart of Accounts dari file Excel';

    public function handle(CoaImportService $importService): int
    {
        $file = $this->argument('file')
            ?? storage_path('app/imports/coa-rma.xlsx');

        if (! file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $this->info("Mengimpor COA dari: {$file}");

        try {
            $stats = $importService->import($file, replace: ! $this->option('keep'));

            $this->table(
                ['Metrik', 'Jumlah'],
                [
                    ['Total diimpor', $stats['imported']],
                    ['Header (H)', $stats['headers']],
                    ['Detail (D)', $stats['details']],
                ]
            );

            $this->info('Import COA berhasil.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Import gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
