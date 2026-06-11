<?php

namespace Database\Seeders;

use App\Services\CoaImportService;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $file = storage_path('app/imports/coa-rma.xlsx');

        if (! file_exists($file)) {
            $this->command?->warn("File COA tidak ditemukan: {$file}. Lewati AccountSeeder.");

            return;
        }

        $stats = app(CoaImportService::class)->import($file, replace: true);

        $this->command?->info("COA diimpor: {$stats['imported']} akun ({$stats['headers']} header, {$stats['details']} detail).");
    }
}
