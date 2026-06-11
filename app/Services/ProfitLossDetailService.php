<?php

namespace App\Services;

use App\Enums\AccountCategory;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitLossDetailService
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

        $rows->push($this->sectionRow('PENJUALAN'));
        $salesResult = $this->renderHeaderBlock($byCode->get('4.111.000'), $byId, $movements, $hideZero);
        $rows = $rows->merge($salesResult['rows']);
        $salesTotal = $salesResult['detail_sum'];

        $rows->push($this->sectionRow('RETUR & POTONGAN PENJUALAN'));
        $returnsResult = $this->renderCodesBlock($byCode, $movements, ['4.211.001', '4.221.001'], $hideZero);
        $rows = $rows->merge($returnsResult['rows']);
        $returnsTotal = $returnsResult['detail_sum'];

        $netSales = $this->subtractAmounts($salesTotal, $returnsTotal);
        $rows->push($this->computedRow('PENJUALAN BERSIH', $netSales));

        $cogsTotal = $this->sumByCategory($byId, $movements, AccountCategory::Cogs);
        $rows->push($this->computedRow('(-) HARGA POKOK PENJUALAN', $cogsTotal));

        $grossProfit = $this->subtractAmounts($netSales, $cogsTotal);
        $rows->push($this->computedRow('LABA KOTOR', $grossProfit));

        $rows->push($this->sectionRow('( - ) BIAYA OPERASIONAL'));

        $rows->push($this->sectionRow('BEBAN AKTIVITAS PENJUALAN :'));
        $sellingResult = $this->renderPrefixBlock($byId, $movements, ['6.111'], $hideZero, 'TOTAL BEBAN AKTIVITAS PENJUALAN');
        $rows = $rows->merge($sellingResult['rows']);
        $sellingTotal = $sellingResult['detail_sum'];

        $rows->push($this->sectionRow('BEBAN UMUM DAN ADMINISTRASI :'));

        $rows->push($this->sectionRow('BEBAN GAJI, UPAH, THR dan BONUS :'));
        $salaryResult = $this->renderPrefixBlock($byId, $movements, ['7.111'], $hideZero, 'TOTAL BEBAN GAJI, UPAH, THR dan BONUS');
        $rows = $rows->merge($salaryResult['rows']);
        $salaryTotal = $salaryResult['detail_sum'];

        $rows->push($this->sectionRow('BEBAN PAJAK :'));
        $taxExpenseResult = $this->renderPrefixBlock($byId, $movements, ['7.112'], $hideZero, 'TOTAL BEBAN PAJAK');
        $rows = $rows->merge($taxExpenseResult['rows']);
        $taxExpenseTotal = $taxExpenseResult['detail_sum'];

        $rows->push($this->sectionRow('BEBAN PEMELIHARAAN '));
        $maintResult = $this->renderPrefixBlock($byId, $movements, ['7.113'], $hideZero, 'TOTAL BEBAN PEMELIHARAAN ');
        $rows = $rows->merge($maintResult['rows']);
        $maintTotal = $maintResult['detail_sum'];

        $rows->push($this->sectionRow('BEBAN PENYUSUTAN'));
        $depResult = $this->renderHeaderBlock($byCode->get('7.114.000'), $byId, $movements, $hideZero, 'TOTAL BEBAN PENYUSUTAN');
        $rows = $rows->merge($depResult['rows']);
        $depTotal = $depResult['detail_sum'];

        $rows->push($this->sectionRow('BEBAN UMUM DAN ADMINISTRASI LAINNYA'));
        $gaOtherResult = $this->renderPrefixBlock($byId, $movements, ['7.115'], $hideZero, 'TOTAL BEBAN UMUM DAN ADMINISTRASI LAINNYA');
        $rows = $rows->merge($gaOtherResult['rows']);
        $gaOtherTotal = $gaOtherResult['detail_sum'];

        $gaTotal = $this->addAmounts($salaryTotal, $taxExpenseTotal, $maintTotal, $depTotal, $gaOtherTotal);
        $rows->push($this->subtotalRow('TOTAL BEBAN UMUM DAN ADMINISTRASI', $gaTotal));

        $operatingExpenseTotal = $this->addAmounts($sellingTotal, $gaTotal);
        $rows->push($this->subtotalRow('TOTAL BIAYA OPERASIONAL', $operatingExpenseTotal));

        $operatingProfit = $this->subtractAmounts($grossProfit, $operatingExpenseTotal);
        $rows->push($this->computedRow('LABA OPERASIONAL', $operatingProfit));

        $rows->push($this->sectionRow('(+) PENGHASILAN / BEBAN LAIN LAIN :'));
        $otherIncomeResult = $this->renderPrefixesBlock($byId, $movements, ['8.111', '8.112'], $hideZero, 'TOTAL PENGHASILAN / BEBAN LAIN LAIN :');
        $rows = $rows->merge($otherIncomeResult['rows']);
        $otherIncomeTotal = $otherIncomeResult['detail_sum'];

        $rows->push($this->sectionRow('(-) BEBAN LAIN-LAIN :'));
        $otherExpenseResult = $this->renderPrefixBlock($byId, $movements, ['9.111'], $hideZero, 'TOTAL BEBAN LAIN LAIN');
        $rows = $rows->merge($otherExpenseResult['rows']);
        $otherExpenseTotal = $otherExpenseResult['detail_sum'];

        $preTaxIncome = $this->subtractAmounts($this->addAmounts($operatingProfit, $otherIncomeTotal), $otherExpenseTotal);
        $rows->push($this->computedRow('LABA BERSIH', $preTaxIncome));

        $taxResult = $this->renderCodesBlock($byCode, $movements, ['10.111.001'], $hideZero);
        $rows = $rows->merge($taxResult['rows']);
        $taxTotal = $taxResult['detail_sum'];

        $netIncome = $this->subtractAmounts($preTaxIncome, $taxTotal);
        $rows->push($this->computedRow('LABA BERSIH', $netIncome));

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

    private function renderHeaderBlock(
        ?Account $header,
        Collection $byId,
        Collection $movements,
        bool $hideZero,
        ?string $subtotalLabel = null
    ): array {
        $rows = collect();
        $detailSum = $this->emptyAmounts();

        if (! $header) {
            return ['rows' => $rows, 'detail_sum' => $detailSum];
        }

        foreach ($this->detailAccountsForHeader($header, $byId) as $account) {
            $amounts = $this->presentationAmounts($account, $movements);

            if ($hideZero && $this->allZero($amounts)) {
                continue;
            }

            $rows->push($this->detailRow($account, $amounts));
            $detailSum = $this->addAmounts($detailSum, $amounts);
        }

        if ($subtotalLabel && (! $hideZero || ! $this->allZero($detailSum))) {
            $rows->push($this->subtotalRow($subtotalLabel, $detailSum));
        }

        return ['rows' => $rows, 'detail_sum' => $detailSum];
    }

    private function renderPrefixBlock(
        Collection $byId,
        Collection $movements,
        array $prefixes,
        bool $hideZero,
        string $subtotalLabel
    ): array {
        return $this->renderPrefixesBlock($byId, $movements, $prefixes, $hideZero, $subtotalLabel);
    }

    private function renderPrefixesBlock(
        Collection $byId,
        Collection $movements,
        array $prefixes,
        bool $hideZero,
        ?string $subtotalLabel = null
    ): array {
        $rows = collect();
        $detailSum = $this->emptyAmounts();

        $details = $this->accountsByPrefixes($byId, $prefixes);

        foreach ($details as $account) {
            $amounts = $this->presentationAmounts($account, $movements);

            if ($hideZero && $this->allZero($amounts)) {
                continue;
            }

            $rows->push($this->detailRow($account, $amounts));
            $detailSum = $this->addAmounts($detailSum, $amounts);
        }

        if ($subtotalLabel && (! $hideZero || ! $this->allZero($detailSum))) {
            $rows->push($this->subtotalRow($subtotalLabel, $detailSum));
        }

        return ['rows' => $rows, 'detail_sum' => $detailSum];
    }

    private function renderCodesBlock(
        Collection $byCode,
        Collection $movements,
        array $codes,
        bool $hideZero
    ): array {
        $rows = collect();
        $detailSum = $this->emptyAmounts();

        foreach ($codes as $code) {
            $account = $byCode->get($code);
            if (! $account) {
                continue;
            }

            $amounts = $this->presentationAmounts($account, $movements);

            if ($hideZero && $this->allZero($amounts)) {
                continue;
            }

            $rows->push($this->detailRow($account, $amounts));
            $detailSum = $this->addAmounts($detailSum, $amounts);
        }

        return ['rows' => $rows, 'detail_sum' => $detailSum];
    }

    private function sumByCategory(Collection $byId, Collection $movements, AccountCategory $category): array
    {
        $sum = $this->emptyAmounts();

        foreach ($byId as $account) {
            if ($account->is_header || $account->account_category !== $category) {
                continue;
            }

            $sum = $this->addAmounts($sum, $this->presentationAmounts($account, $movements));
        }

        return $sum;
    }

    private function accountsByPrefixes(Collection $byId, array $prefixes): Collection
    {
        return $byId
            ->filter(function (Account $account) use ($prefixes) {
                if ($account->is_header) {
                    return false;
                }

                foreach ($prefixes as $prefix) {
                    if (str_starts_with($account->code, $prefix . '.')) {
                        return true;
                    }
                }

                return false;
            })
            ->sortBy('code');
    }

    private function detailAccountsForHeader(Account $header, Collection $byId): Collection
    {
        return $byId
            ->filter(fn (Account $account) => $account->parent_id === $header->id && ! $account->is_header)
            ->sortBy('code');
    }

    private function detailRow(Account $account, array $amounts): array
    {
        return [
            'type' => 'detail',
            'code' => $account->code,
            'label' => $account->name,
            'account_id' => $account->id,
            'amounts' => $amounts,
        ];
    }

    private function sectionRow(string $label): array
    {
        return ['type' => 'section', 'label' => $label, 'amounts' => []];
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
