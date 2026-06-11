<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private TrialBalanceService $trialBalanceService)
    {
    }

    public function build(): array
    {
        $now = Carbon::now();
        $year = (int) $now->format('Y');
        $period = (int) $now->format('n');

        $fiscalPeriod = FiscalPeriod::findForDate($now);
        $periodStats = $this->periodJournalStats($fiscalPeriod, $now);
        $trialBalance = $this->trialBalanceForPeriod($year, $period, $fiscalPeriod);
        $fiscalOverview = $this->fiscalYearOverview($year);

        return [
            'stats' => $this->globalStats(),
            'current' => [
                'date' => $now,
                'year' => $year,
                'period' => $period,
                'fiscal_period' => $fiscalPeriod,
                'journal_stats' => $periodStats,
                'trial_balance' => $trialBalance,
            ],
            'fiscal_overview' => $fiscalOverview,
            'alerts' => $this->buildAlerts($fiscalPeriod, $periodStats, $trialBalance),
            'recent_entries' => $this->recentEntries(),
        ];
    }

    private function globalStats(): array
    {
        return [
            'total_entries' => JournalEntry::count(),
            'draft_entries' => JournalEntry::where('status', JournalEntryStatus::Draft)->count(),
            'posted_entries' => JournalEntry::where('status', JournalEntryStatus::Posted)->count(),
            'accounts' => Account::where('is_active', true)->where('is_header', false)->count(),
            'partners' => Partner::where('is_active', true)->count(),
        ];
    }

    private function periodJournalStats(?FiscalPeriod $fiscalPeriod, Carbon $now): array
    {
        $query = JournalEntry::query()
            ->whereYear('entry_date', $now->year)
            ->whereMonth('entry_date', $now->month);

        if ($fiscalPeriod) {
            $query = JournalEntry::query()
                ->whereBetween('entry_date', [
                    $fiscalPeriod->start_date->toDateString(),
                    $fiscalPeriod->end_date->toDateString(),
                ]);
        }

        return [
            'total' => (clone $query)->count(),
            'posted' => (clone $query)->where('status', JournalEntryStatus::Posted)->count(),
            'draft' => (clone $query)->where('status', JournalEntryStatus::Draft)->count(),
        ];
    }

    private function trialBalanceForPeriod(int $year, int $period, ?FiscalPeriod $fiscalPeriod): ?array
    {
        if (! $fiscalPeriod) {
            return null;
        }

        $result = $this->trialBalanceService->generate($year, $period, true);

        return [
            'is_balanced' => $result['is_balanced'],
            'period_debit' => $result['totals']['period_debit'],
            'period_credit' => $result['totals']['period_credit'],
            'difference' => round(
                $result['totals']['period_debit'] - $result['totals']['period_credit'],
                2
            ),
        ];
    }

    private function fiscalYearOverview(int $year): array
    {
        $periods = FiscalPeriod::query()
            ->where('year', $year)
            ->orderBy('period')
            ->get();

        return [
            'year' => $year,
            'total' => $periods->count(),
            'open' => $periods->where('status', 'open')->count(),
            'closed' => $periods->where('status', 'closed')->count(),
            'periods' => $periods->map(fn (FiscalPeriod $fp) => [
                'id' => $fp->id,
                'period' => $fp->period,
                'name' => $fp->name,
                'status' => $fp->status,
                'is_current' => Carbon::now()->between($fp->start_date, $fp->end_date),
            ]),
        ];
    }

    private function buildAlerts(?FiscalPeriod $fiscalPeriod, array $periodStats, ?array $trialBalance): Collection
    {
        $alerts = collect();

        if (! $fiscalPeriod) {
            $alerts->push([
                'type' => 'warning',
                'message' => 'Periode fiskal bulan ini belum digenerate. Buka Period Closing untuk membuat periode.',
                'url' => route('accounting.period-closing.index'),
            ]);

            return $alerts;
        }

        if ($fiscalPeriod->isClosed()) {
            $alerts->push([
                'type' => 'info',
                'message' => "Periode {$fiscalPeriod->name} sudah ditutup. Jurnal tidak dapat dibuat atau diubah.",
                'url' => route('accounting.period-closing.index', ['year' => $fiscalPeriod->year]),
            ]);
        }

        if ($periodStats['draft'] > 0) {
            $alerts->push([
                'type' => 'warning',
                'message' => "Ada {$periodStats['draft']} jurnal Draft pada periode ini yang belum diposting.",
                'url' => route('accounting.journal-entries.index', ['status' => 'draft']),
            ]);
        }

        if ($trialBalance && ! $trialBalance['is_balanced']) {
            $alerts->push([
                'type' => 'danger',
                'message' => 'Trial Balance periode ini tidak seimbang. Selisih mutasi: '
                    . number_format(abs($trialBalance['difference']), 2, ',', '.'),
                'url' => route('accounting.trial-balance.index', [
                    'year' => $fiscalPeriod->year,
                    'period' => $fiscalPeriod->period,
                ]),
            ]);
        }

        return $alerts;
    }

    private function recentEntries(): Collection
    {
        return JournalEntry::query()
            ->with(['journalType', 'partner'])
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }
}
