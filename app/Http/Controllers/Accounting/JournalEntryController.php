<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreJournalEntryRequest;
use App\Http\Requests\Accounting\UpdateJournalEntryRequest;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalType;
use App\Services\EntryNumberGenerator;
use App\Services\JournalEntryPostingService;
use App\Services\PeriodClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function __construct(
        private EntryNumberGenerator $entryNumberGenerator,
        private PeriodClosingService $periodClosingService,
        private JournalEntryPostingService $postingService,
    ) {
    }

    public function index(Request $request): View
    {
        $entries = JournalEntry::query()
            ->with(['journalType', 'partner'])
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('entry_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('journal_type_id'), fn ($q) => $q->where('journal_type_id', $request->journal_type_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date_to))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('accounting.journal-entries.index', [
            'entries' => $entries,
            'journalTypes' => JournalType::where('is_active', true)->orderBy('name')->get(),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Journal Entries'],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $journalType = $request->filled('journal_type_id')
            ? JournalType::findOrFail($request->journal_type_id)
            : JournalType::where('is_active', true)->orderBy('name')->first();

        return view('accounting.journal-entries.form', [
            'entry' => new JournalEntry([
                'entry_date' => now()->toDateString(),
                'period' => (int) now()->format('n'),
                'exchange_rate' => 1,
                'status' => JournalEntryStatus::Draft,
            ]),
            'suggestedNumber' => $journalType ? $this->entryNumberGenerator->preview($journalType) : '',
            'journalTypes' => JournalType::where('is_active', true)->orderBy('name')->get(),
            'selectedPartner' => null,
            'selectedJournalTypeId' => $journalType?->id,
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Journal Entries', 'url' => route('accounting.journal-entries.index')],
                ['label' => 'Buat Baru'],
            ],
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        try {
            $this->periodClosingService->assertDateIsInOpenPeriod($request->entry_date);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $entry = DB::transaction(function () use ($request) {
            $journalType = JournalType::findOrFail($request->journal_type_id);
            $isManual = $request->filled('entry_number');
            $entryNumber = $isManual
                ? $request->entry_number
                : $this->entryNumberGenerator->generate($journalType);

            $fiscalPeriod = FiscalPeriod::query()
                ->where('start_date', '<=', $request->entry_date)
                ->where('end_date', '>=', $request->entry_date)
                ->where('status', 'open')
                ->first();

            $entry = JournalEntry::create([
                'journal_type_id' => $journalType->id,
                'entry_number' => $entryNumber,
                'entry_date' => $request->entry_date,
                'period' => $request->period,
                'document_number' => $request->document_number,
                'partner_id' => $request->partner_id,
                'description' => null,
                'notes' => $request->notes,
                'status' => JournalEntryStatus::Draft,
                'fiscal_period_id' => $fiscalPeriod?->id,
                'exchange_rate' => $request->exchange_rate ?? 1,
                'is_manual_number' => $isManual,
                'created_by' => Auth::id(),
            ]);

            $this->syncLines($entry, $request->lines, $request->exchange_rate ?? 1);

            return $entry;
        });

        return redirect()
            ->route('accounting.journal-entries.show', $entry)
            ->with('success', 'Jurnal berhasil disimpan.');
    }

    public function show(JournalEntry $journalEntry): View
    {
        $journalEntry->load(['journalType', 'partner', 'lines.account', 'lines.counterAccount', 'lines.partner']);

        $unpostCheck = $this->postingService->canUnpost($journalEntry);

        return view('accounting.journal-entries.show', [
            'entry' => $journalEntry,
            'canUnpost' => $unpostCheck['allowed'],
            'unpostBlockedReason' => $unpostCheck['reason'],
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Journal Entries', 'url' => route('accounting.journal-entries.index')],
                ['label' => $journalEntry->entry_number],
            ],
        ]);
    }

    public function edit(JournalEntry $journalEntry): View|RedirectResponse
    {
        if ($journalEntry->isPosted()) {
            return redirect()
                ->route('accounting.journal-entries.show', $journalEntry)
                ->with('error', 'Jurnal yang sudah diposting tidak dapat diedit.');
        }

        try {
            $this->periodClosingService->assertDateIsInOpenPeriod($journalEntry->entry_date->toDateString());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('accounting.journal-entries.show', $journalEntry)
                ->with('error', $e->getMessage());
        }

        $journalEntry->load(['lines.account', 'lines.counterAccount', 'partner']);

        return view('accounting.journal-entries.form', [
            'entry' => $journalEntry,
            'suggestedNumber' => $journalEntry->entry_number,
            'journalTypes' => JournalType::where('is_active', true)->orderBy('name')->get(),
            'selectedPartner' => $this->selectedPartner($journalEntry),
            'selectedJournalTypeId' => $journalEntry->journal_type_id,
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Journal Entries', 'url' => route('accounting.journal-entries.index')],
                ['label' => $journalEntry->entry_number],
            ],
        ]);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        if ($journalEntry->isPosted()) {
            return back()->with('error', 'Jurnal yang sudah diposting tidak dapat diedit.');
        }

        try {
            $this->periodClosingService->assertDateIsInOpenPeriod($request->entry_date);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        DB::transaction(function () use ($request, $journalEntry) {
            $fiscalPeriod = FiscalPeriod::query()
                ->where('start_date', '<=', $request->entry_date)
                ->where('end_date', '>=', $request->entry_date)
                ->where('status', 'open')
                ->first();

            $journalEntry->update([
                'journal_type_id' => $request->journal_type_id,
                'entry_number' => $request->entry_number,
                'entry_date' => $request->entry_date,
                'period' => $request->period,
                'document_number' => $request->document_number,
                'partner_id' => $request->partner_id,
                'description' => null,
                'notes' => $request->notes,
                'fiscal_period_id' => $fiscalPeriod?->id,
                'exchange_rate' => $request->exchange_rate ?? 1,
                'is_manual_number' => true,
            ]);

            $journalEntry->lines()->delete();
            $this->syncLines($journalEntry, $request->lines, $request->exchange_rate ?? 1);
        });

        return redirect()
            ->route('accounting.journal-entries.show', $journalEntry)
            ->with('success', 'Jurnal berhasil diperbarui.');
    }

    public function post(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $this->postingService->post($journalEntry);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Jurnal berhasil diposting.');
    }

    public function unpost(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $this->postingService->unpost($journalEntry);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('accounting.journal-entries.edit', $journalEntry)
            ->with('success', 'Jurnal dikembalikan ke Draft. Anda dapat memperbaiki data lalu posting ulang.');
    }

    public function previewNumber(Request $request)
    {
        $journalType = JournalType::findOrFail($request->journal_type_id);

        return response()->json([
            'entry_number' => $this->entryNumberGenerator->preview($journalType),
        ]);
    }

    private function selectedPartner(?JournalEntry $entry): ?array
    {
        if (! $entry?->partner) {
            return null;
        }

        return [
            'id' => $entry->partner->id,
            'label' => $entry->partner->displayName(),
        ];
    }

    private function syncLines(JournalEntry $entry, array $lines, float $exchangeRate): void
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $index => $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $entry->lines()->create([
                'line_order' => $index + 1,
                'account_id' => $line['account_id'],
                'counter_account_id' => $line['counter_account_id'] ?? null,
                'partner_id' => $entry->partner_id,
                'description' => $line['description'] ?? null,
                'notes' => $line['notes'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'exchange_rate' => $exchangeRate,
                'amount_idr_debit' => round($debit * $exchangeRate, 2),
                'amount_idr_credit' => round($credit * $exchangeRate, 2),
            ]);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new \RuntimeException('Total debit dan kredit tidak seimbang.');
        }
    }
}
