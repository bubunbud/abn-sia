<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'journal_type_id',
        'entry_number',
        'entry_date',
        'period',
        'document_number',
        'partner_id',
        'description',
        'notes',
        'status',
        'fiscal_period_id',
        'exchange_rate',
        'is_manual_number',
        'posted_at',
        'posted_by',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'is_manual_number' => 'boolean',
        'posted_at' => 'datetime',
        'status' => JournalEntryStatus::class,
    ];

    public function journalType(): BelongsTo
    {
        return $this->belongsTo(JournalType::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_order');
    }

    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::Posted;
    }

    public function totalDebit(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function isBalanced(): bool
    {
        return round($this->totalDebit(), 2) === round($this->totalCredit(), 2);
    }
}
