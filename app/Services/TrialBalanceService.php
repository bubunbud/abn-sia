<?php

namespace App\Services;

use App\Enums\AccountCategory;
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

    /**
     * Neraca saldo tahunan: Saldo Awal + mutasi Jan–Des + Saldo Akhir (format export Excel).
     *
     * @return array{
     *     year: int,
     *     rows: Collection,
     *     totals: array,
     *     pl_separator_after_index: int|null,
     * }
     */
    public function generateYearly(int $year, bool $hideZero = false): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $accounts = Account::query()
            ->where('is_active', true)
            ->where('is_header', false)
            ->orderBy('code')
            ->get();

        $openingTotals = $this->aggregateBalances($yearStart->toDateString(), null);
        $monthlyTotals = $this->aggregateMonthlyBalances(
            $yearStart->toDateString(),
            $yearEnd->toDateString()
        );

        $rows = collect();
        $totals = [
            'opening_debit' => 0.0,
            'opening_credit' => 0.0,
            'months' => array_fill(1, 12, ['debit' => 0.0, 'credit' => 0.0]),
            'closing_debit' => 0.0,
            'closing_credit' => 0.0,
        ];

        $plSeparatorAfterIndex = null;

        foreach ($accounts as $account) {
            $opening = $openingTotals->get($account->id);
            $openingDebitGross = (float) ($opening?->total_debit ?? 0);
            $openingCreditGross = (float) ($opening?->total_credit ?? 0);
            $openingBalance = $openingDebitGross - $openingCreditGross;

            $months = [];
            $yearDebit = 0.0;
            $yearCredit = 0.0;

            for ($m = 1; $m <= 12; $m++) {
                $key = $account->id.'|'.$m;
                $cell = $monthlyTotals->get($key);
                $debit = (float) ($cell?->period_debit ?? 0);
                $credit = (float) ($cell?->period_credit ?? 0);
                $months[$m] = ['debit' => $debit, 'credit' => $credit];
                $yearDebit += $debit;
                $yearCredit += $credit;
            }

            $closingBalance = $openingBalance + $yearDebit - $yearCredit;

            if ($hideZero
                && abs($openingBalance) < 0.01
                && abs($yearDebit) < 0.01
                && abs($yearCredit) < 0.01
                && abs($closingBalance) < 0.01
            ) {
                continue;
            }

            [$openDebit, $openCredit] = $this->splitBalanceSides($openingBalance);
            [$closeDebit, $closeCredit] = $this->splitBalanceSides($closingBalance);

            $isPl = $this->isProfitAndLoss($account);

            if ($plSeparatorAfterIndex === null && $isPl && $rows->isNotEmpty()) {
                $plSeparatorAfterIndex = $rows->count() - 1;
            }

            $rows->push([
                'account' => $account,
                'is_pl' => $isPl,
                'opening_debit' => $openDebit,
                'opening_credit' => $openCredit,
                'months' => $months,
                'closing_debit' => $closeDebit,
                'closing_credit' => $closeCredit,
            ]);

            $totals['opening_debit'] += $openDebit;
            $totals['opening_credit'] += $openCredit;
            $totals['closing_debit'] += $closeDebit;
            $totals['closing_credit'] += $closeCredit;

            for ($m = 1; $m <= 12; $m++) {
                $totals['months'][$m]['debit'] += $months[$m]['debit'];
                $totals['months'][$m]['credit'] += $months[$m]['credit'];
            }
        }

        return [
            'year' => $year,
            'rows' => $rows->values(),
            'totals' => $totals,
            'pl_separator_after_index' => $plSeparatorAfterIndex,
        ];
    }

    /**
     * @return array{0: float, 1: float} [debit, credit]
     */
    private function splitBalanceSides(float $balance): array
    {
        if (abs($balance) < 0.005) {
            return [0.0, 0.0];
        }

        return $balance > 0
            ? [round($balance, 2), 0.0]
            : [0.0, round(abs($balance), 2)];
    }

    private function isProfitAndLoss(Account $account): bool
    {
        return in_array($account->account_category, [
            AccountCategory::Revenue,
            AccountCategory::Cogs,
            AccountCategory::Expense,
        ], true);
    }

    private function aggregateMonthlyBalances(string $dateFrom, string $dateTo): Collection
    {
        return JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', JournalEntryStatus::Posted)
            ->whereBetween('journal_entries.entry_date', [$dateFrom, $dateTo])
            ->groupByRaw('journal_entry_lines.account_id, MONTH(journal_entries.entry_date)')
            ->selectRaw('journal_entry_lines.account_id as account_id')
            ->selectRaw('MONTH(journal_entries.entry_date) as month')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as period_debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) as period_credit')
            ->get()
            ->keyBy(fn ($row) => $row->account_id.'|'.$row->month);
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
