<?php

namespace App\Services;

use App\Enums\AccountCategory;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitLossSummaryService
{
    public function generate(int $year, bool $hideZero = false): array
    {
        $columns = $this->buildColumns($year);
        $movements = $this->loadMovements($columns);

        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $byCode = $accounts->keyBy('code');
        $byId = $accounts->keyBy('id');

        $rows = collect();

        $salesTotal = $this->sumHeader($byCode, $byId, $movements, '4.111.000');
        $returnsTotal = $this->sumCodes($byCode, $movements, ['4.211.001', '4.221.001']);
        $netSales = $this->subtractAmounts($salesTotal, $returnsTotal);

        $cogsTotal = $this->sumByCategory($byId, $movements, AccountCategory::Cogs);
        $grossProfit = $this->subtractAmounts($netSales, $cogsTotal);

        $sellingTotal = $this->sumPrefixes($byId, $movements, ['6.111']);
        $gaTotal = $this->sumPrefixes($byId, $movements, ['7.111', '7.112', '7.113', '7.114', '7.115']);
        $operatingExpenseTotal = $this->addAmounts($sellingTotal, $gaTotal);
        $operatingProfit = $this->subtractAmounts($grossProfit, $operatingExpenseTotal);

        $otherIncomeTotal = $this->sumPrefixes($byId, $movements, ['8.111', '8.112']);
        $otherExpenseTotal = $this->sumPrefixes($byId, $movements, ['9.111']);
        $preTaxIncome = $this->subtractAmounts($this->addAmounts($operatingProfit, $otherIncomeTotal), $otherExpenseTotal);

        $taxTotal = $this->sumCodes($byCode, $movements, ['10.111.001']);
        $netIncome = $this->subtractAmounts($preTaxIncome, $taxTotal);

        $lineItems = [
            $this->lineRow('PENJUALAN', $salesTotal),
            $this->lineRow('RETUR & POTONGAN PENJUALAN', $returnsTotal),
            $this->computedRow('PENJUALAN BERSIH', $netSales),
            $this->lineRow('(-) HARGA POKOK PENJUALAN', $cogsTotal),
            $this->computedRow('LABA KOTOR', $grossProfit),
            $this->lineRow('BEBAN AKTIVITAS PENJUALAN', $sellingTotal),
            $this->lineRow('BEBAN UMUM DAN ADMINISTRASI', $gaTotal),
            $this->subtotalRow('TOTAL BIAYA OPERASIONAL', $operatingExpenseTotal),
            $this->computedRow('LABA OPERASIONAL', $operatingProfit),
            $this->lineRow('PENGHASILAN / BEBAN LAIN LAIN', $otherIncomeTotal),
            $this->lineRow('BEBAN LAIN-LAIN', $otherExpenseTotal),
            $this->computedRow('LABA BERSIH', $preTaxIncome),
            $this->lineRow('PAJAK PENGHASILAN BADAN', $taxTotal),
            $this->computedRow('LABA BERSIH', $netIncome),
        ];

        foreach ($this->filterRows($lineItems, $hideZero) as $row) {
            $rows->push($row);
        }

        return [
            'year' => $year,
            'columns' => $columns,
            'rows' => $rows->values(),
            'totals' => [
                'net_sales' => $netSales,
                'cogs' => $cogsTotal,
                'gross_profit' => $grossProfit,
                'operating_expenses' => $operatingExpenseTotal,
                'operating_profit' => $operatingProfit,
                'net_income' => $netIncome,
            ],
        ];
    }

    private function sumHeader(Collection $byCode, Collection $byId, Collection $movements, string $headerCode): array
    {
        $header = $byCode->get($headerCode);

        if (! $header) {
            return $this->emptyAmounts();
        }

        return $this->sumAccounts($this->detailAccountsForHeader($header, $byId), $movements);
    }

    private function sumCodes(Collection $byCode, Collection $movements, array $codes): array
    {
        $accounts = collect($codes)
            ->map(fn ($code) => $byCode->get($code))
            ->filter();

        return $this->sumAccounts($accounts, $movements);
    }

    private function sumPrefixes(Collection $byId, Collection $movements, array $prefixes): array
    {
        $accounts = $byId->filter(function (Account $account) use ($prefixes) {
            if ($account->is_header) {
                return false;
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($account->code, $prefix . '.')) {
                    return true;
                }
            }

            return false;
        });

        return $this->sumAccounts($accounts, $movements);
    }

    private function sumByCategory(Collection $byId, Collection $movements, AccountCategory $category): array
    {
        $accounts = $byId->filter(
            fn (Account $account) => ! $account->is_header && $account->account_category === $category
        );

        return $this->sumAccounts($accounts, $movements);
    }

    private function detailAccountsForHeader(Account $header, Collection $byId): Collection
    {
        return $byId
            ->filter(fn (Account $account) => $account->parent_id === $header->id && ! $account->is_header)
            ->sortBy('code');
    }

    private function sumAccounts(Collection $accounts, Collection $movements): array
    {
        $sum = $this->emptyAmounts();

        foreach ($accounts as $account) {
            $sum = $this->addAmounts($sum, $this->presentationAmounts($account, $movements));
        }

        return $sum;
    }

    private function filterRows(array $rows, bool $hideZero): array
    {
        if (! $hideZero) {
            return $rows;
        }

        return array_values(array_filter($rows, function ($row) {
            if (in_array($row['type'], ['computed', 'subtotal'], true)) {
                return true;
            }

            return ! $this->allZero($row['amounts']);
        }));
    }

    private function lineRow(string $label, array $amounts): array
    {
        return ['type' => 'line', 'label' => $label, 'amounts' => $amounts];
    }

    private function subtotalRow(string $label, array $amounts): array
    {
        return ['type' => 'subtotal', 'label' => $label, 'amounts' => $amounts];
    }

    private function computedRow(string $label, array $amounts): array
    {
        return ['type' => 'computed', 'label' => $label, 'amounts' => $amounts];
    }

    private function buildColumns(int $year): array
    {
        $columns = [
            [
                'key' => 'prior_year',
                'label' => (string) ($year - 1),
                'start_date' => Carbon::create($year - 1, 1, 1)->toDateString(),
                'end_date' => Carbon::create($year - 1, 12, 31)->toDateString(),
            ],
        ];

        for ($period = 1; $period <= 12; $period++) {
            $start = Carbon::create($year, $period, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $columns[] = [
                'key' => "p{$period}",
                'label' => (string) $period,
                'period' => $period,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ];
        }

        $columns[] = [
            'key' => 'year_end',
            'label' => (string) $year,
            'start_date' => Carbon::create($year, 1, 1)->toDateString(),
            'end_date' => Carbon::create($year, 12, 31)->toDateString(),
        ];

        return $columns;
    }

    private function amountKeys(): array
    {
        return array_merge(
            ['prior_year'],
            array_map(fn ($p) => "p{$p}", range(1, 12)),
            ['year_end']
        );
    }

    private function emptyAmounts(): array
    {
        return array_fill_keys($this->amountKeys(), 0.0);
    }

    private function loadMovements(array $columns): Collection
    {
        $selects = ['journal_entry_lines.account_id'];

        foreach ($columns as $column) {
            $start = $column['start_date'];
            $end = $column['end_date'];
            $key = $column['key'];
            $selects[] = "COALESCE(SUM(CASE WHEN journal_entries.entry_date BETWEEN '{$start}' AND '{$end}' THEN journal_entry_lines.debit ELSE 0 END), 0) as {$key}_debit";
            $selects[] = "COALESCE(SUM(CASE WHEN journal_entries.entry_date BETWEEN '{$start}' AND '{$end}' THEN journal_entry_lines.credit ELSE 0 END), 0) as {$key}_credit";
        }

        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->groupBy('journal_entry_lines.account_id')
            ->selectRaw(implode(', ', $selects))
            ->get()
            ->keyBy('account_id');
    }

    private function presentationAmounts(Account $account, Collection $movements): array
    {
        $raw = $movements->get($account->id);
        $amounts = $this->emptyAmounts();

        if (! $raw) {
            return $amounts;
        }

        foreach ($this->amountKeys() as $key) {
            $debit = (float) ($raw->{"{$key}_debit"} ?? 0);
            $credit = (float) ($raw->{"{$key}_credit"} ?? 0);

            $amounts[$key] = $this->isIncomeAccount($account) && ! $this->isContraRevenue($account)
                ? $credit - $debit
                : $debit - $credit;
        }

        return $amounts;
    }

    private function isIncomeAccount(Account $account): bool
    {
        if ($account->account_category === AccountCategory::Revenue) {
            return true;
        }

        return str_starts_with($account->code, '8.');
    }

    private function isContraRevenue(Account $account): bool
    {
        return str_starts_with($account->code, '4.211')
            || str_starts_with($account->code, '4.221');
    }

    private function addAmounts(array ...$sets): array
    {
        $result = $this->emptyAmounts();

        foreach ($sets as $amounts) {
            foreach ($this->amountKeys() as $key) {
                $result[$key] += ($amounts[$key] ?? 0);
            }
        }

        return $result;
    }

    private function subtractAmounts(array $a, array $b): array
    {
        $result = $this->emptyAmounts();

        foreach ($this->amountKeys() as $key) {
            $result[$key] = ($a[$key] ?? 0) - ($b[$key] ?? 0);
        }

        return $result;
    }

    private function allZero(array $amounts): bool
    {
        foreach ($amounts as $value) {
            if (abs((float) $value) >= 0.01) {
                return false;
            }
        }

        return true;
    }
}
