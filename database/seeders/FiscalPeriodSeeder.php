<?php

namespace Database\Seeders;

use App\Services\PeriodClosingService;
use Illuminate\Database\Seeder;

class FiscalPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PeriodClosingService::class);

        foreach ([2024, 2025, 2026] as $year) {
            $service->generateYear($year);
        }
    }
}
