<?php

namespace App\Enums;

enum AccountCategory: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Cogs = 'cogs';
    case Expense = 'expense';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Aset',
            self::Liability => 'Kewajiban',
            self::Equity => 'Modal',
            self::Revenue => 'Pendapatan',
            self::Cogs => 'HPP',
            self::Expense => 'Beban',
            self::Other => 'Lain-lain',
        };
    }

    public static function fromCode(string $code): self
    {
        $first = (int) substr(preg_replace('/[^0-9]/', '', $code), 0, 1);

        return match ($first) {
            1 => self::Asset,
            2 => self::Liability,
            3 => self::Equity,
            4, 8 => self::Revenue,
            5 => self::Cogs,
            6, 7, 9, 10 => self::Expense,
            default => self::Other,
        };
    }
}
