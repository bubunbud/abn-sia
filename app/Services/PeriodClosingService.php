<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PeriodClosingService
{
    private const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public function __construct(private TrialBalanceService $trialBalanceService)
    {
    }

    public function listForYear(int $year): Collection
    {
        return FiscalPeriod::query()
            ->where('year', $year)
            ->orderBy('period')
            ->get()
            ->map(fn (FiscalPeriod $fp) => $this->enrichPeriod($fp));
    }

    public function generateYear(int $year): int
    {
        $created = 0;

        for ($period = 1; $period <= 12; $period++) {
            $start = Carbon::create($year, $period, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $fiscalPeriod = FiscalPeriod::updateOrCreate(
                ['year' => $year, 'period' => $period],
                [
                    'name' => self::MONTH_NAMES[$period] . ' ' . $year,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ]
            );

            if ($fiscalPeriod->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    public function validateForClosing(FiscalPeriod $fiscalPeriod): array
    {
        $issues = [];

        if ($fiscalPeriod->isClosed()) {
            $issues[] = 'Periode sudah ditutup.';
        }

        if ($fiscalPeriod->period > 1) {
            $previous = FiscalPeriod::query()
                ->where('year', $fiscalPeriod->year)
                ->where('period', $fiscalPeriod->period - 1)
                ->first();

            if ($previous && $previous->isOpen()) {
                $issues[] = 'Periode sebelumnya (' . $previous->name . ') belum ditutup.';
            }
        }

        $stats = $this->journalStats($fiscalPeriod);
        if ($stats['draft'] > 0) {
            $issues[] = "Masih ada {$stats['draft']} jurnal berstatus Draft.";
        }

        $trialBalance = $this->trialBalanceService->generate(
            $fiscalPeriod->year,
            $fiscalPeriod->period
        );

        if (! $trialBalance['is_balanced']) {
            $issues[] = 'Trial Balance tidak seimbang (selisih saldo akhir: '
                . number_format($trialBalance['totals']['closing_balance'], 2, ',', '.') . ').';
        }

        if (abs($trialBalance['totals']['period_debit'] - $trialBalance['totals']['period_credit']) >= 0.01) {
            $issues[] = 'Total mutasi debet dan kredit periode tidak seimbang.';
        }

        return [
            'can_close' => empty($issues),
            'issues' => $issues,
            'journal_stats' => $stats,
            'trial_balance' => $trialBalance,
        ];
    }

    public function close(FiscalPeriod $fiscalPeriod, ?string $notes = null): FiscalPeriod
    {
        $validation = $this->validateForClosing($fiscalPeriod);

        if (! $validation['can_close']) {
            throw new RuntimeException(implode(' ', $validation['issues']));
        }

        $fiscalPeriod->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => Auth::id(),
            'notes' => $notes,
        ]);

        return $fiscalPeriod->fresh();
    }

    public function validateForReopen(FiscalPeriod $fiscalPeriod): array
    {
        $issues = [];

        if ($fiscalPeriod->isOpen()) {
            $issues[] = 'Periode masih terbuka.';
        }

        $laterClosed = FiscalPeriod::query()
            ->where('year', $fiscalPeriod->year)
            ->where('period', '>', $fiscalPeriod->period)
            ->where('status', 'closed')
            ->exists();

        if ($laterClosed) {
            $issues[] = 'Tutup periode berikutnya terlebih dahulu sebelum membuka periode ini.';
        }

        return [
            'can_reopen' => empty($issues),
            'issues' => $issues,
        ];
    }

    public function reopen(FiscalPeriod $fiscalPeriod): FiscalPeriod
    {
        $validation = $this->validateForReopen($fiscalPeriod);

        if (! $validation['can_reopen']) {
            throw new RuntimeException(implode(' ', $validation['issues']));
        }

        $fiscalPeriod->update([
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $fiscalPeriod->fresh();
    }

    public function assertDateIsInOpenPeriod(string $date): void
    {
        $fiscalPeriod = FiscalPeriod::findForDate($date);

        if ($fiscalPeriod && $fiscalPeriod->isClosed()) {
            throw new RuntimeException(
                "Periode {$fiscalPeriod->name} sudah ditutup. Jurnal tidak dapat dibuat atau diubah."
            );
        }
    }

    private function enrichPeriod(FiscalPeriod $fiscalPeriod): array
    {
        $stats = $this->journalStats($fiscalPeriod);
        $validation = $this->validateForClosing($fiscalPeriod);
        $reopenValidation = $this->validateForReopen($fiscalPeriod);

        return [
            'period' => $fiscalPeriod,
            'journal_stats' => $stats,
            'can_close' => $validation['can_close'],
            'close_issues' => $validation['issues'],
            'can_reopen' => $reopenValidation['can_reopen'],
            'reopen_issues' => $reopenValidation['issues'],
            'trial_balanced' => $validation['trial_balance']['is_balanced'] ?? false,
        ];
    }

    private function journalStats(FiscalPeriod $fiscalPeriod): array
    {
        $query = JournalEntry::query()
            ->whereBetween('entry_date', [
                $fiscalPeriod->start_date->toDateString(),
                $fiscalPeriod->end_date->toDateString(),
            ]);

        return [
            'total' => (clone $query)->count(),
            'posted' => (clone $query)->where('status', JournalEntryStatus::Posted)->count(),
            'draft' => (clone $query)->where('status', JournalEntryStatus::Draft)->count(),
        ];
    }
}
