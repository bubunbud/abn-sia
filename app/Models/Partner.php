<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'region',
        'status_label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function displayName(): string
    {
        return $this->code . ' — ' . $this->name;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'customer' => 'Piutang',
            'vendor' => 'Hutang',
            'financial' => 'Keuangan',
            'employee' => 'Karyawan',
            default => 'Lain-lain',
        };
    }

    public static function typeOptions(): array
    {
        return [
            'customer' => 'Piutang',
            'vendor' => 'Hutang',
            'financial' => 'Keuangan',
            'employee' => 'Karyawan',
            'other' => 'Lain-lain',
        ];
    }
}
