<?php

namespace App\Services;

use App\Enums\AccountCategory;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalanceSheetService
{
    public function generate(int $year, bool $hideZero = false): array
    {
        $columns = $this->buildColumns($year);
        $balances = $this->loadBalances($columns);

        $accounts = Account::query()
            ->where('is_active', true)
            ->whereIn('account_category', [
                AccountCategory::Asset,
                AccountCategory::Liability,
                AccountCategory::Equity,
            ])
            ->orderBy('code')
            ->get();

        $byCode = $accounts->keyBy('code');
        $byId = $accounts->keyBy('id');

        $rows = collect();
        $rows->push($this->sectionRow('AKTIVA'));

        $assetResult = $this->renderLayout(
            $this->assetLayout(),
            $byCode,
            $byId,
            $balances,
            AccountCategory::Asset,
            $hideZero
        );
        $rows = $rows->merge($assetResult['rows']);
        $assetTotal = $assetResult['detail_sum'];
        $rows->push($this->totalRow('TOTAL AKTIVA', $assetTotal));

        $rows->push($this->sectionRow('PASSIVA'));

        $liabilityResult = $this->renderLayout(
            $this->liabilityLayout(),
            $byCode,
            $byId,
            $balances,
            AccountCategory::Liability,
            $hideZero
        );
        $rows = $rows->merge($liabilityResult['rows']);
        $liabilityTotal = $liabilityResult['detail_sum'];
        $rows->push($this->totalRow('Total Kewajiban', $liabilityTotal));

        $equityResult = $this->renderEquityDetails($byCode, $balances, $hideZero);
        $rows = $rows->merge($equityResult['rows']);
        $equityTotal = $equityResult['detail_sum'];
        $rows->push($this->totalRow('Total Modal', $equityTotal));

        $passivaTotal = $this->addAmounts($liabilityTotal, $equityTotal);
        $rows->push($this->totalRow('TOTAL PASSIVA', $passivaTotal));

        return [
            'year' => $year,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => [
                'assets' => $assetTotal,
                'liabilities' => $liabilityTotal,
                'equity' => $equityTotal,
                'passiva' => $passivaTotal,
            ],
            'is_balanced' => $this->amountsEqual($assetTotal, $passivaTotal),
        ];
    }

    private function assetLayout(): array
    {
        return [
            ['header', '1.111.000', 'TOTAL KAS'],
            ['headers', ['1.121.000', '1.122.000'], 'TOTAL BANK'],
            ['prefixes', ['1.131', '1.132', '1.133', '1.134'], 'TOTAL DEPOSITO'],
            ['header', '1.135.000', 'TOTAL INVESTASI AFILIASI'],
            ['rollup', 'TOTAL KAS DAN SETARA KAS'],
            ['header', '1.141.000', 'Total Piutang Dagang'],
            ['headers', ['1.144.000', '1.146.000', '1.148.000'], 'Total Piutang Lain-Lain'],
            ['headers', ['1.151.000', '1.152.000', '1.153.000', '1.154.000'], 'Total Persediaan'],
            ['header', '1.161.000', 'Total Uang Muka Pembelian'],
            ['header', '1.171.000', 'Total Biaya Dibayar Dimuka'],
            ['header', '1.172.000', 'Total Pajak Dibayar Dimuka'],
            ['header', '1.211.000', 'Total Aktiva Tetap'],
            ['header', '1.212.000', 'Total Akumulasi Penyusutan'],
            ['header', '1.221.000', 'Total Aktiva Lain-Lain'],
        ];
    }

    private function liabilityLayout(): array
    {
        return [
            ['header', '2.111.000', 'Total Hutang Dagang'],
            ['header', '2.121.000', 'Total Hutang Bank Jk Pendek'],
            ['header', '2.131.000', 'Total Biaya YMH Dibayar'],
            ['header', '2.132.000', 'Total Pajak YMH Dibayar'],
            ['header', '2.141.000', 'Total Uang Muka Penjualan'],
            ['header', '2.151.000', 'Total Hutang Divisi'],
            ['header', '2.161.000', 'Total Hutang Lain-Lain'],
            ['header', '2.211.000', 'Total Hutang Bank Jk Panjang'],
            ['header', '2.221.000', null],
            ['header', '2.231.000', null],
        ];
    }

    private function renderLayout(
        array $layout,
        Collection $byCode,
        Collection $byId,
        Collection $balances,
        AccountCategory $category,
        bool $hideZero
    ): array {
        $rows = collect();
        $sectionDetailSum = $this->emptyAmounts();
        $rollupBuffer = collect();

        foreach ($layout as $item) {
            $type = $item[0];

            if ($type === 'rollup') {
                $subtotal = $this->sumDetailAmounts($rollupBuffer);
                if (! $hideZero || ! $this->allZero($subtotal)) {
                    $rows->push([
                        'type' => 'subtotal',
                        'code' => null,
                        'label' => $item[1],
                        'indent' => 0,
                        'amounts' => $subtotal,
                    ]);
                }
                $rollupBuffer = collect();
                continue;
            }

            if ($type === 'header') {
                $result = $this->renderHeaderBlock(
                    $byCode->get($item[1]),
                    $byId,
                    $balances,
                    $category,
                    $hideZero,
                    $item[2] ?? null
                );
            } elseif ($type === 'headers') {
                $result = $this->renderMultiHeaderBlock(
                    collect($item[1])->map(fn ($code) => $byCode->get($code))->filter(),
                    $byId,
                    $balances,
                    $category,
                    $hideZero,
                    $item[2]
                );
            } elseif ($type === 'prefixes') {
                $result = $this->renderPrefixBlock(
                    $byId,
                    $balances,
                    $category,
                    $item[1],
                    $hideZero,
                    $item[2]
                );
            } else {
                continue;
            }

            $rows = $rows->merge($result['rows']);
            $rollupBuffer = $rollupBuffer->merge(
                $result['rows']->filter(fn ($row) => $row['type'] === 'detail')
            );
            $sectionDetailSum = $this->addAmounts($sectionDetailSum, $result['detail_sum']);
        }

        return [
            'rows' => $rows,
            'detail_sum' => $sectionDetailSum,
        ];
    }

    private function renderEquityDetails(
        Collection $byCode,
        Collection $balances,
        bool $hideZero
    ): array {
        $rows = collect();
        $detailSum = $this->emptyAmounts();

        $equityAccounts = $byCode
            ->filter(fn (Account $account) => $account->account_category === AccountCategory::Equity && ! $account->is_header)
            ->sortBy('code');

        foreach ($equityAccounts as $account) {
            $amounts = $this->presentationAmounts($account, $balances);

            if ($hideZero && $this->allZero($amounts)) {
                continue;
            }

            $rows->push($this->detailRow($account, $amounts));
            $detailSum = $this->addAmounts($detailSum, $amounts);
        }

        return [
            'rows' => $rows,
            'detail_sum' => $detailSum,
        ];
    }

    private function renderHeaderBlock(
        ?Account $header,
        Collection $byId,
        Collection $balances,
        AccountCategory $category,
        bool $hideZero,
        ?string $subtotalLabel
    ): array {
        $rows = collect();
        $detailSum = $this->emptyAmounts();

        if (! $header) {
            return ['rows' => $rows, 'detail_sum' => $detailSum];
        }

        $details = $this->detailAccountsForHeader($header, $byId);

        foreach ($details as $account) {
            $amounts = $this->presentationAmounts($account, $balances);

            if ($hideZero && $this->allZero($amounts)) {
                continue;
            }

            $rows->push($this->detailRow($account, $amounts));
            $detailSum = $this->addAmounts($detailSum, $amounts);
        }

        if ($subtotalLabel && (! $hideZero || ! $this->allZero($detailSum))) {
            $rows->push([
                'type' => 'subtotal',
                'code' => null,
                'label' => $subtotalLabel,
                'indent' => 0,
                'amounts' => $detailSum,
            ]);
        }

        return [
            'rows' => $rows,
            'detail_sum' => $detailSum,
        ];
    }

    private function renderMultiHeaderBlock(
        Collection $headers,
        Collection $byId,
        Collection $balances,
        AccountCategory $category,
        bool $hideZero,
        string $subtotalLabel
    ): array {
        $rows = collect();
        $detailSum = $this->emptyAmounts();

        foreach ($headers as $header) {
            $block = $this->renderHeaderBlock($header, $byId, $balances, $category, $hideZero, null);
            $rows = $rows->merge($block['rows']);
            $detailSum = $this->addAmounts($detailSum, $block['detail_sum']);
        }

        if (! $hideZero || ! $this->allZero($detailSum)) {
            $rows->push([
                'type' => 'subtotal',
                'code' => null,
                'label' => $subtotalLabel,
                'indent' => 0,
                'amounts' => $detailSum,
            ]);
        }

        return [
            'rows' => $rows,
            'detail_sum' => $detailSum,
        ];
    }

    private function renderPrefixBlock(
        Collection $byId,
        Collection $balances,
        AccountCategory $category,
        array $prefixes,
        bool $hideZero,
        string $subtotalLabel
    ): array {
        $rows = collect();
        $detailSum = $this->emptyAmounts();

        $details = $byId
            ->filter(function (Account $account) use ($prefixes, $category) {
                if ($account->is_header || $account->account_category !== $category) {
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

        foreach ($details as $account) {
            $amounts = $this->presentationAmounts($account, $balances);

            if ($hideZero && $this->allZero($amounts)) {
                continue;
            }

            $rows->push($this->detailRow($account, $amounts));
            $detailSum = $this->addAmounts($detailSum, $amounts);
        }

        if (! $hideZero || ! $this->allZero($detailSum)) {
            $rows->push([
                'type' => 'subtotal',
                'code' => null,
                'label' => $subtotalLabel,
                'indent' => 0,
                'amounts' => $detailSum,
            ]);
        }

        return [
            'rows' => $rows,
            'detail_sum' => $detailSum,
        ];
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
            'indent' => 0,
            'amounts' => $amounts,
        ];
    }

    private function buildColumns(int $year): array
    {
        $amountKeys = $this->amountKeys();

        $columns = [
            [
                'key' => 'prior_year',
                'label' => (string) ($year - 1),
                'end_date' => Carbon::create($year - 1, 12, 31)->toDateString(),
            ],
        ];

        for ($period = 1; $period <= 12; $period++) {
            $end = Carbon::create($year, $period, 1)->endOfMonth();
            $columns[] = [
                'key' => "p{$period}",
                'label' => (string) $period,
                'period' => $period,
                'end_date' => $end->toDateString(),
                'start_date' => $end->copy()->startOfMonth()->toDateString(),
            ];
        }

        $columns[] = [
            'key' => 'year_end',
            'label' => (string) $year,
            'end_date' => Carbon::create($year, 12, 31)->toDateString(),
            'start_date' => Carbon::create($year, 1, 1)->toDateString(),
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

    private function loadBalances(array $columns): Collection
    {
        $selects = ['journal_entry_lines.account_id'];

        foreach ($columns as $column) {
            $date = $column['end_date'];
            $key = $column['key'];
            $selects[] = "COALESCE(SUM(CASE WHEN journal_entries.entry_date <= '{$date}' THEN journal_entry_lines.debit - journal_entry_lines.credit ELSE 0 END), 0) as {$key}";
        }

        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->groupBy('journal_entry_lines.account_id')
            ->selectRaw(implode(', ', $selects))
            ->get()
            ->keyBy('account_id');
    }

    private function presentationAmounts(Account $account, Collection $balances): array
    {
        $raw = $balances->get($account->id);
        $amounts = $this->emptyAmounts();

        if (! $raw) {
            return $amounts;
        }

        foreach ($this->amountKeys() as $key) {
            $signed = (float) ($raw->{$key} ?? 0);
            $amounts[$key] = $account->account_category === AccountCategory::Asset
                ? $signed
                : -$signed;
        }

        return $amounts;
    }

    private function sectionRow(string $label): array
    {
        return [
            'type' => 'section',
            'code' => null,
            'label' => $label,
            'indent' => 0,
            'amounts' => [],
        ];
    }

    private function totalRow(string $label, array $amounts): array
    {
        return [
            'type' => 'total',
            'code' => null,
            'label' => $label,
            'indent' => 0,
            'amounts' => $amounts,
        ];
    }

    private function sumDetailAmounts(Collection $rows): array
    {
        $sum = $this->emptyAmounts();

        foreach ($rows as $row) {
            if (($row['type'] ?? '') !== 'detail') {
                continue;
            }

            foreach ($row['amounts'] as $key => $value) {
                $sum[$key] = ($sum[$key] ?? 0) + (float) $value;
            }
        }

        return $sum;
    }

    private function addAmounts(array $a, array $b): array
    {
        $result = $this->emptyAmounts();

        foreach ($this->amountKeys() as $key) {
            $result[$key] = ($a[$key] ?? 0) + ($b[$key] ?? 0);
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

    private function amountsEqual(array $a, array $b): bool
    {
        foreach ($this->amountKeys() as $key) {
            if (abs(($a[$key] ?? 0) - ($b[$key] ?? 0)) >= 0.01) {
                return false;
            }
        }

        return true;
    }
}
