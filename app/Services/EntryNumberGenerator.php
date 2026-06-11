<?php

namespace App\Services;

use App\Models\JournalType;
use Illuminate\Support\Facades\DB;

class EntryNumberGenerator
{
    public function generate(JournalType $journalType): string
    {
        return DB::transaction(function () use ($journalType) {
            $locked = JournalType::lockForUpdate()->findOrFail($journalType->id);
            $number = str_pad((string) $locked->next_number, $locked->number_padding, '0', STR_PAD_LEFT);
            $entryNumber = $locked->prefix . '-' . $number;

            $locked->increment('next_number');

            return $entryNumber;
        });
    }

    public function preview(JournalType $journalType): string
    {
        $number = str_pad((string) $journalType->next_number, $journalType->number_padding, '0', STR_PAD_LEFT);

        return $journalType->prefix . '-' . $number;
    }
}
