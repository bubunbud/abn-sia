<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeneralLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = Account::where('is_active', true)
            ->where('is_header', false)
            ->orderBy('code')
            ->get();

        $accountId = $request->integer('account_id') ?: $accounts->first()?->id;
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());
        $highlightEntryId = $request->integer('highlight_entry');

        $lines = collect();
        $runningBalance = 0;
        $account = null;

        if ($accountId) {
            $account = Account::find($accountId);

            $rawLines = JournalEntryLine::query()
                ->with(['journalEntry.partner', 'account'])
                ->where('account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                    $q->where('status', JournalEntryStatus::Posted)
                        ->whereBetween('entry_date', [$dateFrom, $dateTo]);
                })
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->orderBy('journal_entries.entry_date')
                ->orderBy('journal_entry_lines.id')
                ->select('journal_entry_lines.*')
                ->get();

            foreach ($rawLines as $line) {
                if ($account->normal_balance === 'debit') {
                    $runningBalance += (float) $line->debit - (float) $line->credit;
                } else {
                    $runningBalance += (float) $line->credit - (float) $line->debit;
                }

                $lines->push([
                    'line' => $line,
                    'balance' => $runningBalance,
                    'highlight' => $highlightEntryId && $line->journal_entry_id == $highlightEntryId,
                ]);
            }
        }

        return view('accounting.general-ledger.index', [
            'accounts' => $accounts,
            'selectedAccount' => $account,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'ledgerLines' => $lines,
            'highlightEntryId' => $highlightEntryId,
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'General Ledger'],
            ],
        ]);
    }

    public function fromJournalLine(Request $request, JournalEntryLine $line): View
    {
        $entry = $line->journalEntry;
        $date = Carbon::parse($entry->entry_date);

        return $this->index(new Request([
            'account_id' => $line->account_id,
            'date_from' => $date->copy()->startOfMonth()->toDateString(),
            'date_to' => $date->copy()->endOfMonth()->toDateString(),
            'highlight_entry' => $entry->id,
        ]));
    }
}
