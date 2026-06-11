<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TrialBalanceService
{
    public function generate(int $year, int $period, bool $hideZero = false): array
    {
        $start = Carbon::create($year, $period, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $accounts = Account::query()
            ->where('is_active', true)
            ->where('is_header', false)
            ->orderBy('code')
            ->get();

        $openingTotals = $this->aggregateBalances($start->toDateString(), null);
        $periodTotals = $this->aggregateBalances($start->toDateString(), $end->toDateString());

        $rows = collect();
        $totals = [
            'opening_balance' => 0,
            'period_debit' => 0,
            'period_credit' => 0,
            'closing_balance' => 0,
        ];

        foreach ($accounts as $index => $account) {
            $opening = $openingTotals->get($account->id);
            $movement = $periodTotals->get($account->id);

            $openingDebit = (float) ($opening?->total_debit ?? 0);
            $openingCredit = (float) ($opening?->total_credit ?? 0);
            $periodDebit = (float) ($movement?->period_debit ?? 0);
            $periodCredit = (float) ($movement?->period_credit ?? 0);

            $openingBalance = $openingDebit - $openingCredit;
            $closingBalance = $openingBalance + $periodDebit - $periodCredit;

            if ($hideZero
                && abs($openingBalance) < 0.01
                && abs($periodDebit) < 0.01
                && abs($periodCredit) < 0.01
                && abs($closingBalance) < 0.01
            ) {
                continue;
            }

            $row = [
                'no' => $index + 1,
                'account' => $account,
                'opening_balance' => $openingBalance,
                'period_debit' => $periodDebit,
                'period_credit' => $periodCredit,
                'closing_balance' => $closingBalance,
            ];

            $rows->push($row);

            $totals['opening_balance'] += $openingBalance;
            $totals['period_debit'] += $periodDebit;
            $totals['period_credit'] += $periodCredit;
            $totals['closing_balance'] += $closingBalance;
        }

        $rows = $rows->values()->map(function ($row, $index) {
            $row['no'] = $index + 1;

            return $row;
        });

        return [
            'rows' => $rows,
            'totals' => $totals,
            'is_balanced' => abs($totals['closing_balance']) < 0.01,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'year' => $year,
            'period' => $period,
        ];
    }

    private function aggregateBalances(string $periodStart, ?string $periodEnd): Collection
    {
        if ($periodEnd === null) {
            return JournalEntryLine::query()
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.status', JournalEntryStatus::Posted)
                ->where('journal_entries.entry_date', '<', $periodStart)
                ->groupBy('journal_entry_lines.account_id')
                ->selectRaw('journal_entry_lines.account_id as account_id')
                ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit')
                ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit')
                ->get()
                ->keyBy('account_id');
        }

        return JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', JournalEntryStatus::Posted)
            ->whereBetween('journal_entries.entry_date', [$periodStart, $periodEnd])
            ->groupBy('journal_entry_lines.account_id')
            ->selectRaw('journal_entry_lines.account_id as account_id')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as period_debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) as period_credit')
            ->get()
            ->keyBy('account_id');
    }
}
