<?php

namespace App\Services;

use App\Enums\AccountCategory;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalanceSheetSummaryService
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

        $currentAssetLines = [
            $this->lineRow('KAS', $this->sumHeader($byCode, $byId, $balances, '1.111.000', AccountCategory::Asset)),
            $this->lineRow('BANK', $this->sumHeaders($byCode, $byId, $balances, ['1.121.000', '1.122.000'], AccountCategory::Asset)),
            $this->lineRow('DEPOSITO', $this->sumPrefixes($byId, $balances, ['1.131', '1.132', '1.133', '1.134'], AccountCategory::Asset)),
            $this->lineRow('INVESTASI AFILIASI', $this->sumHeader($byCode, $byId, $balances, '1.135.000', AccountCategory::Asset)),
            $this->lineRow('PIUTANG DAGANG', $this->sumHeader($byCode, $byId, $balances, '1.141.000', AccountCategory::Asset)),
            $this->lineRow('PIUTANG LAIN-LAIN', $this->sumHeaders($byCode, $byId, $balances, ['1.144.000', '1.146.000', '1.148.000'], AccountCategory::Asset)),
            $this->lineRow('PERSEDIAAN', $this->sumHeaders($byCode, $byId, $balances, ['1.151.000', '1.152.000', '1.153.000', '1.154.000'], AccountCategory::Asset)),
            $this->lineRow('UANG MUKA PEMBELIAN', $this->sumHeader($byCode, $byId, $balances, '1.161.000', AccountCategory::Asset)),
            $this->lineRow('BIAYA DIBAYAR DIMUKA', $this->sumHeader($byCode, $byId, $balances, '1.171.000', AccountCategory::Asset)),
            $this->lineRow('PAJAK DIBAYAR DIMUKA', $this->sumHeader($byCode, $byId, $balances, '1.172.000', AccountCategory::Asset)),
        ];

        $currentAssetTotal = $this->sumRows($currentAssetLines);
        $currentAssetLines = $this->filterRows($currentAssetLines, $hideZero);
        $rows = $rows->merge($currentAssetLines);
        $rows->push($this->subtotalRow('TOTAL AKTIVA LANCAR', $currentAssetTotal));

        $fixedAssetLines = [
            $this->lineRow('TANAH', $this->sumCodes($byCode, $balances, ['1.211.001'], AccountCategory::Asset)),
            $this->lineRow('BANGUNAN', $this->sumCodes($byCode, $balances, ['1.211.002'], AccountCategory::Asset)),
            $this->lineRow('MESIN', $this->sumCodes($byCode, $balances, ['1.211.003'], AccountCategory::Asset)),
            $this->lineRow('PERLENGKAPAN PRODUKSI', $this->sumCodes($byCode, $balances, ['1.211.004'], AccountCategory::Asset)),
            $this->lineRow('ALAT KANTOR ELEKTRONIK', $this->sumCodes($byCode, $balances, ['1.211.005'], AccountCategory::Asset)),
            $this->lineRow('ALAT KANTOR FURNITURE', $this->sumCodes($byCode, $balances, ['1.211.006'], AccountCategory::Asset)),
            $this->lineRow('KENDARAAN', $this->sumCodes($byCode, $balances, ['1.211.007'], AccountCategory::Asset)),
            $this->lineRow('AKTIVA SEWA GUNA USAHA', $this->sumCodes($byCode, $balances, ['1.211.008'], AccountCategory::Asset)),
        ];

        $fixedAssetGross = $this->sumRows($fixedAssetLines);
        $fixedAssetLines = $this->filterRows($fixedAssetLines, $hideZero);
        $rows = $rows->merge($fixedAssetLines);
        $rows->push($this->subtotalRow('TOTAL AKTIVA TETAP', $fixedAssetGross));

        $accumDepreciation = $this->sumHeader($byCode, $byId, $balances, '1.212.000', AccountCategory::Asset);
        if (! $hideZero || ! $this->allZero($accumDepreciation)) {
            $rows->push($this->lineRow('AKUMULASI PENYUSUTAN', $accumDepreciation));
        }

        $netFixedAssets = $this->addAmounts($fixedAssetGross, $accumDepreciation);
        $rows->push($this->subtotalRow('Nilai Buku Aktiva Tetap', $netFixedAssets));

        $otherAssets = $this->sumHeader($byCode, $byId, $balances, '1.221.000', AccountCategory::Asset);
        if (! $hideZero || ! $this->allZero($otherAssets)) {
            $rows->push($this->lineRow('AKTIVA LAIN-LAIN', $otherAssets));
        }

        $assetTotal = $this->addAmounts($this->addAmounts($currentAssetTotal, $netFixedAssets), $otherAssets);
        $rows->push($this->totalRow('TOTAL AKTIVA', $assetTotal));

        $rows->push($this->sectionRow('PASSIVA'));

        $currentLiabilityLines = [
            $this->lineRow('HUTANG DAGANG', $this->sumHeader($byCode, $byId, $balances, '2.111.000', AccountCategory::Liability)),
            $this->lineRow('HUTANG BANK JK PENDEK', $this->sumHeader($byCode, $byId, $balances, '2.121.000', AccountCategory::Liability)),
            $this->lineRow('BIAYA YMH DIBAYAR', $this->sumHeader($byCode, $byId, $balances, '2.131.000', AccountCategory::Liability)),
            $this->lineRow('PAJAK YMH DIBAYAR', $this->sumHeader($byCode, $byId, $balances, '2.132.000', AccountCategory::Liability)),
            $this->lineRow('UANG MUKA PENJUALAN', $this->sumHeader($byCode, $byId, $balances, '2.141.000', AccountCategory::Liability)),
        ];

        $currentLiabilityTotal = $this->sumRows($currentLiabilityLines);
        $currentLiabilityLines = $this->filterRows($currentLiabilityLines, $hideZero);
        $rows = $rows->merge($currentLiabilityLines);
        $rows->push($this->subtotalRow('TOTAL HUTANG LANCAR', $currentLiabilityTotal));

        $longTermLiabilityLines = [
            $this->lineRow('HUTANG DIVISI', $this->sumHeader($byCode, $byId, $balances, '2.151.000', AccountCategory::Liability)),
            $this->lineRow('HUTANG LAIN-LAIN', $this->sumHeader($byCode, $byId, $balances, '2.161.000', AccountCategory::Liability)),
            $this->lineRow('HUTANG BANK JK PANJANG', $this->sumHeader($byCode, $byId, $balances, '2.211.000', AccountCategory::Liability)),
            $this->lineRow('HUTANG SEWA GUNA USAHA', $this->sumHeader($byCode, $byId, $balances, '2.221.000', AccountCategory::Liability)),
            $this->lineRow('HUTANG KEPADA PEMEGANG SAHAM', $this->sumHeader($byCode, $byId, $balances, '2.231.000', AccountCategory::Liability)),
        ];

        $longTermLiabilityTotal = $this->sumRows($longTermLiabilityLines);
        $longTermLiabilityLines = $this->filterRows($longTermLiabilityLines, $hideZero);
        $rows = $rows->merge($longTermLiabilityLines);
        $rows->push($this->subtotalRow('TOTAL HUTANG TIDAK LANCAR', $longTermLiabilityTotal));

        $equityLines = [
            $this->lineRow('MODAL DISETOR', $this->sumCodes($byCode, $balances, ['3.111.001'], AccountCategory::Equity)),
            $this->lineRow('LABA / RUGI DITAHAN', $this->sumCodes($byCode, $balances, ['3.112.001'], AccountCategory::Equity)),
            $this->lineRow('LABA / RUGI TAHUN BERJALAN', $this->sumCodes($byCode, $balances, ['3.112.002'], AccountCategory::Equity)),
            $this->lineRow('PRIVE DAN DIVIDEN', $this->sumCodes($byCode, $balances, ['3.113.001'], AccountCategory::Equity)),
        ];

        $equityTotal = $this->sumRows($equityLines);
        $equityLines = $this->filterRows($equityLines, $hideZero);
        $rows = $rows->merge($equityLines);
        $rows->push($this->subtotalRow('TOTAL MODAL', $equityTotal));

        $liabilityTotal = $this->addAmounts($currentLiabilityTotal, $longTermLiabilityTotal);
        $passivaTotal = $this->addAmounts($liabilityTotal, $equityTotal);
        $rows->push($this->totalRow('TOTAL PASSIVA', $passivaTotal));

        return [
            'year' => $year,
            'columns' => $columns,
            'rows' => $rows->values(),
            'totals' => [
                'assets' => $assetTotal,
                'current_assets' => $currentAssetTotal,
                'net_fixed_assets' => $netFixedAssets,
                'current_liabilities' => $currentLiabilityTotal,
                'long_term_liabilities' => $longTermLiabilityTotal,
                'liabilities' => $liabilityTotal,
                'equity' => $equityTotal,
                'passiva' => $passivaTotal,
            ],
            'is_balanced' => $this->amountsEqual($assetTotal, $passivaTotal),
        ];
    }

    private function sumHeader(
        Collection $byCode,
        Collection $byId,
        Collection $balances,
        string $headerCode,
        AccountCategory $category
    ): array {
        $header = $byCode->get($headerCode);

        if (! $header) {
            return $this->emptyAmounts();
        }

        return $this->sumAccounts(
            $this->detailAccountsForHeader($header, $byId),
            $balances,
            $category
        );
    }

    private function sumHeaders(
        Collection $byCode,
        Collection $byId,
        Collection $balances,
        array $headerCodes,
        AccountCategory $category
    ): array {
        $accounts = collect();

        foreach ($headerCodes as $code) {
            $header = $byCode->get($code);
            if ($header) {
                $accounts = $accounts->merge($this->detailAccountsForHeader($header, $byId));
            }
        }

        return $this->sumAccounts($accounts, $balances, $category);
    }

    private function sumPrefixes(
        Collection $byId,
        Collection $balances,
        array $prefixes,
        AccountCategory $category
    ): array {
        $accounts = $byId->filter(function (Account $account) use ($prefixes, $category) {
            if ($account->is_header || $account->account_category !== $category) {
                return false;
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($account->code, $prefix . '.')) {
                    return true;
                }
            }

            return false;
        });

        return $this->sumAccounts($accounts, $balances, $category);
    }

    private function sumCodes(
        Collection $byCode,
        Collection $balances,
        array $codes,
        AccountCategory $category
    ): array {
        $accounts = collect($codes)
            ->map(fn ($code) => $byCode->get($code))
            ->filter();

        return $this->sumAccounts($accounts, $balances, $category);
    }

    private function detailAccountsForHeader(Account $header, Collection $byId): Collection
    {
        return $byId
            ->filter(fn (Account $account) => $account->parent_id === $header->id && ! $account->is_header)
            ->sortBy('code');
    }

    private function sumAccounts(Collection $accounts, Collection $balances, AccountCategory $category): array
    {
        $sum = $this->emptyAmounts();

        foreach ($accounts as $account) {
            $amounts = $this->presentationAmounts($account, $balances, $category);
            $sum = $this->addAmounts($sum, $amounts);
        }

        return $sum;
    }

    private function sumRows(array $rows): array
    {
        $sum = $this->emptyAmounts();

        foreach ($rows as $row) {
            $sum = $this->addAmounts($sum, $row['amounts']);
        }

        return $sum;
    }

    private function filterRows(array $rows, bool $hideZero): array
    {
        if (! $hideZero) {
            return $rows;
        }

        return array_values(array_filter($rows, fn ($row) => ! $this->allZero($row['amounts'])));
    }

    private function lineRow(string $label, array $amounts): array
    {
        return [
            'type' => 'line',
            'label' => $label,
            'amounts' => $amounts,
        ];
    }

    private function sectionRow(string $label): array
    {
        return [
            'type' => 'section',
            'label' => $label,
            'amounts' => [],
        ];
    }

    private function subtotalRow(string $label, array $amounts): array
    {
        return [
            'type' => 'subtotal',
            'label' => $label,
            'amounts' => $amounts,
        ];
    }

    private function totalRow(string $label, array $amounts): array
    {
        return [
            'type' => 'total',
            'label' => $label,
            'amounts' => $amounts,
        ];
    }

    private function buildColumns(int $year): array
    {
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

    private function presentationAmounts(Account $account, Collection $balances, AccountCategory $category): array
    {
        $raw = $balances->get($account->id);
        $amounts = $this->emptyAmounts();

        if (! $raw) {
            return $amounts;
        }

        foreach ($this->amountKeys() as $key) {
            $signed = (float) ($raw->{$key} ?? 0);
            $amounts[$key] = $category === AccountCategory::Asset ? $signed : -$signed;
        }

        return $amounts;
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
