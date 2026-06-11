<?php

namespace App\Console\Commands;

use App\Services\PartnerImportService;
use Illuminate\Console\Command;

class ImportPartnersCommand extends Command
{
    protected $signature = 'partners:import {file? : Path ke file Excel Pihak Kedua}';

    protected $description = 'Import master Pihak Kedua dari file Excel';

    public function handle(PartnerImportService $importService): int
    {
        $file = $this->argument('file')
            ?? storage_path('app/imports/pihak-kedua-rma.xlsx');

        if (! file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $this->info("Mengimpor Pihak Kedua dari: {$file}");

        try {
            $stats = $importService->import($file);

            $this->table(
                ['Metrik', 'Jumlah'],
                [
                    ['Total diimpor', $stats['imported']],
                    ['Piutang (PDL)', $stats['piutang']],
                    ['Hutang (HDL)', $stats['hutang']],
                ]
            );

            $this->info('Import Pihak Kedua berhasil.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Import gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
