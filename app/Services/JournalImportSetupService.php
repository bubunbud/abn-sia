<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalType;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class JournalImportSetupService
{
    public function __construct(private PeriodClosingService $periodClosingService)
    {
    }

    public function prerequisites(): array
    {
        return [
            'accounts' => Account::query()->where('is_active', true)->where('is_header', false)->count(),
            'partners' => Partner::query()->where('is_active', true)->count(),
            'journal_types' => JournalType::query()->where('is_active', true)->count(),
            'fiscal_periods' => FiscalPeriod::query()->count(),
            'existing_journals' => JournalEntry::count(),
        ];
    }

    public function defaultFileCandidates(): array
    {
        return [
            storage_path('app/imports/jurnal-historis.xlsx'),
            storage_path('app/imports/sample_sia.xlsx'),
            base_path('sample_sia.xlsx'),
            base_path('storage/app/imports/jurnal-historis.xlsx'),
        ];
    }

    public function resolveDefaultFile(): ?string
    {
        foreach ($this->defaultFileCandidates() as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function ensureFiscalPeriodsForDates(Collection $dates): int
    {
        $years = $dates
            ->map(fn (string $date) => (int) Carbon::parse($date)->format('Y'))
            ->unique()
            ->sort()
            ->values();

        $created = 0;

        foreach ($years as $year) {
            $created += $this->periodClosingService->generateYear($year);
        }

        return $created;
    }
}
