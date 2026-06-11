<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class JournalEntryPostingService
{
    public function __construct(private PeriodClosingService $periodClosingService)
    {
    }

    public function canUnpost(JournalEntry $journalEntry): array
    {
        if (! $journalEntry->isPosted()) {
            return [
                'allowed' => false,
                'reason' => 'Hanya jurnal berstatus Posted yang dapat dikembalikan ke Draft.',
            ];
        }

        try {
            $this->periodClosingService->assertDateIsInOpenPeriod(
                $journalEntry->entry_date->toDateString()
            );
        } catch (RuntimeException $e) {
            return [
                'allowed' => false,
                'reason' => $e->getMessage(),
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function post(JournalEntry $journalEntry): void
    {
        if ($journalEntry->isPosted()) {
            throw new RuntimeException('Jurnal sudah diposting.');
        }

        $this->periodClosingService->assertDateIsInOpenPeriod(
            $journalEntry->entry_date->toDateString()
        );

        $journalEntry->loadMissing('lines');

        if (! $journalEntry->isBalanced()) {
            throw new RuntimeException('Total debit dan kredit harus seimbang sebelum posting.');
        }

        $journalEntry->update([
            'status' => JournalEntryStatus::Posted,
            'posted_at' => now(),
            'posted_by' => Auth::id(),
        ]);
    }

    public function unpost(JournalEntry $journalEntry): void
    {
        $check = $this->canUnpost($journalEntry);

        if (! $check['allowed']) {
            throw new RuntimeException($check['reason']);
        }

        $journalEntry->update([
            'status' => JournalEntryStatus::Draft,
            'posted_at' => null,
            'posted_by' => null,
        ]);
    }
}
