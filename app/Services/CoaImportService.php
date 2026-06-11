<?php

namespace App\Services;

use App\Enums\AccountCategory;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class CoaImportService
{
    public function import(string $filePath, bool $replace = true): array
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("File tidak ditemukan: {$filePath}");
        }

        $rows = $this->readRows($filePath);

        if (empty($rows)) {
            throw new RuntimeException('Tidak ada data COA yang dapat diimpor.');
        }

        return DB::transaction(function () use ($rows, $replace) {
            if ($replace) {
                DB::table('journal_entry_lines')->delete();
                DB::table('journal_entries')->delete();
                DB::table('tax_codes')->delete();
                Account::query()->delete();
            }

            $codeSet = [];
            foreach ($rows as $row) {
                $codeSet[$row['code']] = true;
            }

            $created = [];
            $stats = ['imported' => 0, 'headers' => 0, 'details' => 0];

            foreach ($rows as $row) {
                $parentCode = $this->resolveParentCode($row['code'], $codeSet);
                $parentId = $parentCode ? ($created[$parentCode] ?? null) : null;

                $account = Account::updateOrCreate(
                    ['code' => $row['code']],
                    [
                        'name' => $row['name'],
                        'group_name' => $row['group_name'],
                        'account_category' => AccountCategory::fromCode($row['code']),
                        'normal_balance' => $row['normal_balance'],
                        'is_header' => $row['is_header'],
                        'parent_id' => $parentId,
                        'level' => $row['level'],
                        'is_active' => true,
                    ]
                );

                $created[$row['code']] = $account->id;
                $stats['imported']++;
                $stats[$row['is_header'] ? 'headers' : 'details']++;
            }

            return $stats;
        });
    }

    private function readRows(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('COA') ?? $spreadsheet->getActiveSheet();

        $headerRow = null;
        $rows = [];

        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }

            $first = trim((string) ($cells[0] ?? ''));

            if ($headerRow === null) {
                if ($this->isHeaderRow($cells)) {
                    $headerRow = $rowIndex;
                }
                continue;
            }

            if ($first === '' || ! $this->looksLikeAccountCode($first)) {
                continue;
            }

            $rows[] = $this->mapRow($cells);
        }

        if ($headerRow === null) {
            throw new RuntimeException('Baris header COA tidak ditemukan (kolom: KODE AKUN, NAMA KODE AKUN).');
        }

        return $rows;
    }

    private function isHeaderRow(array $cells): bool
    {
        $joined = strtolower(implode('|', array_map(fn ($v) => trim((string) $v), $cells)));

        return str_contains($joined, 'kode akun') && str_contains($joined, 'nama');
    }

    private function looksLikeAccountCode(string $value): bool
    {
        return (bool) preg_match('/^\d+\.\d{3}\.\d{3}$/', $value);
    }

    private function mapRow(array $cells): array
    {
        $code = trim((string) $cells[0]);
        $name = trim((string) ($cells[1] ?? ''));
        $kelompok = trim((string) ($cells[2] ?? ''));
        $posSaldo = trim((string) ($cells[3] ?? ''));
        $groupAkun = trim((string) ($cells[4] ?? ''));

        $parts = explode('.', $code);
        $isHeader = ($parts[2] ?? '') === '000';
        $level = $this->resolveLevel($parts);

        return [
            'code' => $code,
            'name' => $name,
            'group_name' => $groupAkun !== '' ? $groupAkun : ($kelompok !== '' ? $kelompok : null),
            'normal_balance' => $this->resolveNormalBalance($posSaldo, $code),
            'is_header' => $isHeader,
            'level' => $level,
        ];
    }

    private function resolveLevel(array $parts): int
    {
        if (($parts[1] ?? '') === '000' && ($parts[2] ?? '') === '000') {
            return 1;
        }

        if (($parts[2] ?? '') === '000') {
            return 2;
        }

        return 3;
    }

    private function resolveNormalBalance(string $posSaldo, string $code): string
    {
        $normalized = strtolower($posSaldo);

        if (in_array($normalized, ['db', 'd', 'debit'], true)) {
            return 'debit';
        }

        if (in_array($normalized, ['cr', 'k', 'kredit', 'credit'], true)) {
            return 'credit';
        }

        $first = (int) substr($code, 0, 1);

        return in_array($first, [2, 3, 4], true) ? 'credit' : 'debit';
    }

    private function resolveParentCode(string $code, array $codeSet): ?string
    {
        $parts = explode('.', $code);

        if (count($parts) !== 3) {
            return null;
        }

        [$major, $middle, $minor] = $parts;

        if ($minor === '000') {
            $candidate = "{$major}.000.000";

            return isset($codeSet[$candidate]) ? $candidate : null;
        }

        $candidate = "{$major}.{$middle}.000";
        if (isset($codeSet[$candidate])) {
            return $candidate;
        }

        $roundedMiddle = str_pad((string) (intdiv((int) $middle, 10) * 10), 3, '0', STR_PAD_LEFT);
        $candidate = "{$major}.{$roundedMiddle}.000";

        return isset($codeSet[$candidate]) ? $candidate : null;
    }
}
