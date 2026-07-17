<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntryLine;
use App\Services\GeneralLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeneralLedgerController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $generalLedgerService,
    ) {}
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
        $account = $accountId ? Account::find($accountId) : null;

        if ($account) {
            $lines = $this->generalLedgerService->accountDetail(
                $account,
                $dateFrom,
                $dateTo,
                $highlightEntryId ?: null,
            );
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

    public function summary(Request $request): View
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());

        $accountLedgers = $this->generalLedgerService->allAccountsDetail($dateFrom, $dateTo);

        $totals = [
            'accounts' => $accountLedgers->count(),
            'lines' => $accountLedgers->sum(fn (array $group) => $group['lines']->count()),
            'debit' => $accountLedgers->sum('total_debit'),
            'credit' => $accountLedgers->sum('total_credit'),
        ];

        return view('accounting.general-ledger.summary', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'accountLedgers' => $accountLedgers,
            'totals' => $totals,
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'General Ledger', 'url' => route('accounting.general-ledger.index')],
                ['label' => 'View All'],
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
