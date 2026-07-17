<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

class GeneralLedgerService
{
    /**
     * Buku besar detail semua akun (COA) yang punya transaksi posted dalam periode.
     *
     * @return Collection<int, array{
     *     account: Account,
     *     lines: Collection,
     *     total_debit: float,
     *     total_credit: float,
     *     ending_balance: float,
     * }>
     */
    public function allAccountsDetail(string $dateFrom, string $dateTo): Collection
    {
        $rawLines = JournalEntryLine::query()
            ->with(['journalEntry.partner', 'account'])
            ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                $q->where('status', JournalEntryStatus::Posted)
                    ->whereBetween('entry_date', [$dateFrom, $dateTo]);
            })
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->where('accounts.is_header', false)
            ->orderBy('accounts.code')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entry_lines.id')
            ->select('journal_entry_lines.*')
            ->get();

        return $rawLines
            ->groupBy('account_id')
            ->map(function (Collection $lines) {
                $account = $lines->first()->account;
                $runningBalance = 0;

                $ledgerLines = $lines->map(function (JournalEntryLine $line) use ($account, &$runningBalance) {
                    if ($account->normal_balance === 'debit') {
                        $runningBalance += (float) $line->debit - (float) $line->credit;
                    } else {
                        $runningBalance += (float) $line->credit - (float) $line->debit;
                    }

                    return [
                        'line' => $line,
                        'balance' => $runningBalance,
                    ];
                });

                return [
                    'account' => $account,
                    'lines' => $ledgerLines,
                    'total_debit' => (float) $lines->sum('debit'),
                    'total_credit' => (float) $lines->sum('credit'),
                    'ending_balance' => $runningBalance,
                ];
            })
            ->sortBy(fn (array $group) => $group['account']->code)
            ->values();
    }

    /**
     * Buku besar detail satu akun dalam periode.
     *
     * @return Collection<int, array{line: JournalEntryLine, balance: float, highlight: bool}>
     */
    public function accountDetail(Account $account, string $dateFrom, string $dateTo, ?int $highlightEntryId = null): Collection
    {
        $rawLines = JournalEntryLine::query()
            ->with(['journalEntry.partner', 'account'])
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                $q->where('status', JournalEntryStatus::Posted)
                    ->whereBetween('entry_date', [$dateFrom, $dateTo]);
            })
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entry_lines.id')
            ->select('journal_entry_lines.*')
            ->get();

        $runningBalance = 0;

        return $rawLines->map(function (JournalEntryLine $line) use ($account, &$runningBalance, $highlightEntryId) {
            if ($account->normal_balance === 'debit') {
                $runningBalance += (float) $line->debit - (float) $line->credit;
            } else {
                $runningBalance += (float) $line->credit - (float) $line->debit;
            }

            return [
                'line' => $line,
                'balance' => $runningBalance,
                'highlight' => $highlightEntryId && $line->journal_entry_id == $highlightEntryId,
            ];
        });
    }
}
