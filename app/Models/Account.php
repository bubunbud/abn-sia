<?php

namespace App\Models;

use App\Enums\AccountCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'code',
        'name',
        'group_name',
        'account_category',
        'normal_balance',
        'is_header',
        'parent_id',
        'level',
        'is_active',
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'is_active' => 'boolean',
        'account_category' => AccountCategory::class,
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('code');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function isPostable(): bool
    {
        return ! $this->is_header && $this->is_active;
    }

    public function displayName(): string
    {
        return $this->code . ' — ' . $this->name;
    }
}
