<?php

namespace Database\Seeders;

use App\Services\PartnerImportService;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $file = storage_path('app/imports/pihak-kedua-rma.xlsx');

        if (! file_exists($file)) {
            $this->command?->warn("File Pihak Kedua tidak ditemukan: {$file}. Lewati PartnerSeeder.");

            return;
        }

        $stats = app(PartnerImportService::class)->import($file);

        $this->command?->info("Pihak Kedua diimpor: {$stats['imported']} (Piutang: {$stats['piutang']}, Hutang: {$stats['hutang']}).");
    }
}
