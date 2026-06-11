<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalPeriod extends Model
{
    protected $fillable = [
        'name',
        'year',
        'period',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public static function findForDate(string|\DateTimeInterface $date): ?self
    {
        $parsed = Carbon::parse($date)->toDateString();

        return static::query()
            ->where('start_date', '<=', $parsed)
            ->where('end_date', '>=', $parsed)
            ->first();
    }

    public function periodLabel(): string
    {
        return 'Periode ' . $this->period . ' / ' . $this->year;
    }
}
