<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalType;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class JournalImportService
{
    public function __construct(private JournalImportSetupService $setupService)
    {
    }

    private const COL_TYPE = 5;

    private const COL_DATE = 6;

    private const COL_ENTRY_NUMBER = 7;

    private const COL_DOCUMENT_NUMBER = 8;

    private const COL_PARTNER_NAME = 9;

    private const COL_PARTNER_CODE = 10;

    private const COL_ACCOUNT = 11;

    private const COL_COUNTER_ACCOUNT = 12;

    private const COL_DESCRIPTION = 14;

    private const COL_NOTES = 15;

    private const COL_DEBIT = 16;

    private const COL_CREDIT = 17;

    private const COL_EXCHANGE_RATE = 18;

    private const COL_IDR_DEBIT = 19;

    private const COL_IDR_CREDIT = 20;

    private array $journalTypeMap = [
        'bank masuk' => 'bank_masuk',
        'bank keluar' => 'bank_keluar',
        'kas keluar' => 'kas_keluar',
        'penjualan' => 'penjualan',
        'pembelian' => 'pembelian',
        'jurnal umum' => 'jurnal_umum',
    ];

    public function import(
        string $filePath,
        bool $replace = false,
        bool $skipExisting = true,
        bool $dryRun = false,
        bool $generatePeriods = false,
    ): array {
        if (! file_exists($filePath)) {
            throw new RuntimeException("File tidak ditemukan: {$filePath}");
        }

        $parsed = $this->parseFile($filePath);
        $groups = $this->groupRows($parsed['rows']);

        if (empty($groups)) {
            throw new RuntimeException('Tidak ada jurnal yang dapat diimpor dari file ini.');
        }

        $accounts = Account::query()->where('is_active', true)->get()->keyBy('code');
        $partnersByCode = Partner::query()->where('is_active', true)->get()->keyBy('code');
        $partnersByName = Partner::query()->where('is_active', true)->get()->keyBy(fn (Partner $p) => mb_strtolower(trim($p->name)));
        $journalTypes = JournalType::query()->where('is_active', true)->get()->keyBy('code');

        $stats = [
            'entries_imported' => 0,
            'lines_imported' => 0,
            'entries_skipped' => 0,
            'entries_failed' => 0,
            'periods_generated' => 0,
            'errors' => [],
            'meta' => [],
        ];

        $prepared = [];

        foreach ($groups as $groupKey => $group) {
            try {
                $prepared[] = $this->prepareGroup(
                    $group,
                    $accounts,
                    $partnersByCode,
                    $partnersByName,
                    $journalTypes
                );
            } catch (RuntimeException $e) {
                $stats['entries_failed']++;
                $stats['errors'][] = "[{$groupKey}] {$e->getMessage()}";
            }
        }

        $stats['meta'] = $this->buildMeta($prepared, $parsed['rows']);

        if ($dryRun) {
            $stats['entries_ready'] = count($prepared);
            $stats['lines_ready'] = collect($prepared)->sum(fn (array $entry) => count($entry['lines']));

            return $stats;
        }

        if ($generatePeriods) {
            $stats['periods_generated'] = $this->setupService->ensureFiscalPeriodsForDates(
                collect($prepared)->pluck('entry_date')
            );

            $prepared = array_map(function (array $entryData) {
                $entryData['fiscal_period_id'] = $this->resolveFiscalPeriod(
                    Carbon::parse($entryData['entry_date'])
                );

                return $entryData;
            }, $prepared);
        }

        $userId = Auth::id();

        return DB::transaction(function () use ($prepared, $replace, $skipExisting, $stats, $userId) {
            if ($replace) {
                DB::table('journal_entry_lines')->delete();
                DB::table('journal_entries')->delete();
            }

            $existingNumbers = JournalEntry::query()->pluck('id', 'entry_number');

            foreach ($prepared as $entryData) {
                if ($skipExisting && $existingNumbers->has($entryData['entry_number'])) {
                    $stats['entries_skipped']++;
                    continue;
                }

                if (! $skipExisting && $existingNumbers->has($entryData['entry_number'])) {
                    $existing = JournalEntry::where('entry_number', $entryData['entry_number'])->first();
                    $existing?->lines()->delete();
                    $existing?->delete();
                }

                $entry = JournalEntry::create([
                    'journal_type_id' => $entryData['journal_type_id'],
                    'entry_number' => $entryData['entry_number'],
                    'entry_date' => $entryData['entry_date'],
                    'period' => $entryData['period'],
                    'document_number' => $entryData['document_number'],
                    'partner_id' => $entryData['partner_id'],
                    'description' => $entryData['description'],
                    'notes' => $entryData['notes'],
                    'status' => JournalEntryStatus::Posted,
                    'fiscal_period_id' => $entryData['fiscal_period_id'],
                    'exchange_rate' => $entryData['exchange_rate'],
                    'is_manual_number' => true,
                    'posted_at' => $entryData['entry_date'],
                    'posted_by' => $userId,
                    'created_by' => $userId,
                ]);

                foreach ($entryData['lines'] as $line) {
                    $entry->lines()->create($line);
                    $stats['lines_imported']++;
                }

                $stats['entries_imported']++;
            }

            return $stats;
        });
    }

    private function parseFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('Jurnal') ?? $spreadsheet->getActiveSheet();

        $dataStartRow = $this->findDataStartRow($sheet);
        $rows = [];
        $carry = [
            'type' => null,
            'date' => null,
            'entry_number' => null,
            'document_number' => null,
            'partner_name' => null,
        ];

        foreach ($sheet->getRowIterator($dataStartRow) as $row) {
            $rowIndex = $row->getRowIndex();
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }

            $accountCode = $this->normalizeCode($cells[self::COL_ACCOUNT - 1] ?? null);

            if ($accountCode === '' || $this->isSkippableAccount($accountCode)) {
                continue;
            }

            $type = $this->cellString($cells[self::COL_TYPE - 1] ?? null);
            $dateCell = $sheet->getCell([self::COL_DATE, $rowIndex]);
            $date = $this->parseDateFromCell($dateCell);
            $entryNumber = $this->cellString($cells[self::COL_ENTRY_NUMBER - 1] ?? null);
            $documentNumber = $this->cellString($cells[self::COL_DOCUMENT_NUMBER - 1] ?? null);
            $partnerName = $this->cellString($cells[self::COL_PARTNER_NAME - 1] ?? null);

            if ($entryNumber !== ''
                && $carry['entry_number'] !== null
                && $entryNumber !== $carry['entry_number']) {
                $carry['date'] = null;
            }

            if ($type !== '') {
                $carry['type'] = $type;
            }
            if ($date !== null) {
                $carry['date'] = $date;
            }
            if ($entryNumber !== '') {
                $carry['entry_number'] = $entryNumber;
            }
            if ($documentNumber !== '') {
                $carry['document_number'] = $documentNumber;
            }
            if ($partnerName !== '') {
                $carry['partner_name'] = $partnerName;
            }

            $debit = $this->parseAmount($cells[self::COL_DEBIT - 1] ?? 0);
            $credit = $this->parseAmount($cells[self::COL_CREDIT - 1] ?? 0);

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            if ($carry['type'] === null || $carry['date'] === null || $carry['entry_number'] === '') {
                throw new RuntimeException("Baris akun {$accountCode} tidak memiliki header jurnal lengkap.");
            }

            $rows[] = [
                'type' => $carry['type'],
                'date' => $carry['date'],
                'entry_number' => $carry['entry_number'],
                'document_number' => $carry['document_number'],
                'partner_name' => $carry['partner_name'],
                'partner_code' => $this->normalizePartnerCode($cells[self::COL_PARTNER_CODE - 1] ?? null),
                'account_code' => $accountCode,
                'counter_account_code' => $this->normalizeCode($cells[self::COL_COUNTER_ACCOUNT - 1] ?? null),
                'description' => $this->cellString($cells[self::COL_DESCRIPTION - 1] ?? null),
                'notes' => $this->cellString($cells[self::COL_NOTES - 1] ?? null),
                'debit' => $debit,
                'credit' => $credit,
                'exchange_rate' => $this->parseAmount($cells[self::COL_EXCHANGE_RATE - 1] ?? 1) ?: 1,
                'idr_debit' => $this->parseAmount($cells[self::COL_IDR_DEBIT - 1] ?? null),
                'idr_credit' => $this->parseAmount($cells[self::COL_IDR_CREDIT - 1] ?? null),
            ];
        }

        return ['rows' => $rows];
    }

    private function groupRows(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $key = implode('|', [
                $row['type'],
                $row['date'],
                $row['entry_number'],
                $row['document_number'] ?? '',
            ]);

            $groups[$key][] = $row;
        }

        return $groups;
    }

    private function prepareGroup(
        array $lines,
        $accounts,
        $partnersByCode,
        $partnersByName,
        $journalTypes
    ): array {
        $first = $lines[0];
        $typeCode = $this->mapJournalType($first['type'], $journalTypes);

        if (! $typeCode || ! $journalTypes->has($typeCode)) {
            throw new RuntimeException("Tipe jurnal tidak dikenali: {$first['type']}");
        }

        $headerPartnerId = $this->resolvePartnerId($first['partner_name'], null, $partnersByCode, $partnersByName);

        $totalDebit = 0;
        $totalCredit = 0;
        $preparedLines = [];
        $exchangeRate = 1;

        foreach ($lines as $index => $line) {
            $account = $accounts->get($line['account_code']);

            if (! $account) {
                throw new RuntimeException("Akun tidak ditemukan: {$line['account_code']}");
            }

            if (! $account->isPostable()) {
                throw new RuntimeException("Akun {$line['account_code']} bukan akun detail (header).");
            }

            $counterAccountId = null;
            if ($line['counter_account_code'] !== '') {
                $counter = $accounts->get($line['counter_account_code']);

                if (! $counter) {
                    throw new RuntimeException("Akun lawan tidak ditemukan: {$line['counter_account_code']}");
                }

                $counterAccountId = $counter->id;
            }

            $linePartnerId = $this->resolvePartnerId(
                $line['partner_name'],
                $line['partner_code'],
                $partnersByCode,
                $partnersByName
            ) ?? $headerPartnerId;

            $rate = $line['exchange_rate'] > 0 ? $line['exchange_rate'] : 1;
            if ($index === 0) {
                $exchangeRate = $rate;
            }

            $idrDebit = $line['idr_debit'] > 0
                ? $line['idr_debit']
                : round($line['debit'] * $rate, 2);
            $idrCredit = $line['idr_credit'] > 0
                ? $line['idr_credit']
                : round($line['credit'] * $rate, 2);

            $totalDebit += $line['debit'];
            $totalCredit += $line['credit'];

            $preparedLines[] = [
                'line_order' => $index + 1,
                'account_id' => $account->id,
                'counter_account_id' => $counterAccountId,
                'partner_id' => $linePartnerId,
                'description' => $line['description'] !== '' ? $line['description'] : null,
                'notes' => $line['notes'] !== '' ? $line['notes'] : null,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'exchange_rate' => $rate,
                'amount_idr_debit' => $idrDebit,
                'amount_idr_credit' => $idrCredit,
            ];
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new RuntimeException(
                "Jurnal {$first['entry_number']} tidak seimbang (D: {$totalDebit}, K: {$totalCredit})."
            );
        }

        $entryDate = Carbon::parse($first['date']);

        return [
            'journal_type_id' => $journalTypes->get($typeCode)->id,
            'entry_number' => $first['entry_number'],
            'entry_date' => $entryDate->toDateString(),
            'period' => (int) $entryDate->format('n'),
            'document_number' => $first['document_number'] !== '' ? $first['document_number'] : null,
            'partner_id' => $headerPartnerId,
            'description' => $this->firstNonEmpty($lines, 'description'),
            'notes' => $this->firstNonEmpty($lines, 'notes'),
            'exchange_rate' => $exchangeRate,
            'fiscal_period_id' => $this->resolveFiscalPeriod($entryDate),
            'lines' => $preparedLines,
        ];
    }

    private function findDataStartRow($sheet): int
    {
        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }

            $typeHeader = mb_strtolower(trim((string) ($cells[self::COL_TYPE - 1] ?? '')));
            $dateHeader = mb_strtolower(trim((string) ($cells[self::COL_DATE - 1] ?? '')));

            if (str_contains($typeHeader, 'tipe') && str_contains($dateHeader, 'tanggal')) {
                return $rowIndex + 2;
            }
        }

        return 7;
    }

    private function mapJournalType(string $label, $journalTypes): ?string
    {
        $key = mb_strtolower(trim($label));

        if (isset($this->journalTypeMap[$key])) {
            return $this->journalTypeMap[$key];
        }

        $byName = $journalTypes->first(fn (JournalType $type) => mb_strtolower($type->name) === $key);

        if ($byName) {
            return $byName->code;
        }

        $partial = $journalTypes->first(fn (JournalType $type) => str_contains($key, mb_strtolower($type->name))
            || str_contains(mb_strtolower($type->name), $key));

        return $partial?->code;
    }

    private function resolvePartnerId(
        ?string $name,
        ?string $code,
        $partnersByCode,
        $partnersByName
    ): ?int {
        if ($code !== null && $code !== '' && $partnersByCode->has($code)) {
            return $partnersByCode->get($code)->id;
        }

        if ($name === null || $name === '') {
            return null;
        }

        $key = mb_strtolower(trim($name));

        if ($partnersByName->has($key)) {
            return $partnersByName->get($key)->id;
        }

        $match = $partnersByName->first(fn (Partner $partner) => str_contains(mb_strtolower($partner->name), $key));

        return $match?->id;
    }

    private function resolveFiscalPeriod(Carbon $date): ?int
    {
        return FiscalPeriod::findForDate($date)?->id;
    }

    private function buildMeta(array $prepared, array $rows): array
    {
        $dates = collect($prepared)->pluck('entry_date')->filter();

        return [
            'source_lines' => count($rows),
            'entries_parsed' => count($prepared),
            'lines_parsed' => collect($prepared)->sum(fn (array $entry) => count($entry['lines'])),
            'date_from' => $dates->min(),
            'date_to' => $dates->max(),
            'years' => $dates->map(fn (string $date) => (int) Carbon::parse($date)->format('Y'))->unique()->sort()->values()->all(),
            'without_fiscal_period' => collect($prepared)->filter(fn (array $entry) => $entry['fiscal_period_id'] === null)->count(),
        ];
    }

    private function firstNonEmpty(array $lines, string $field): ?string
    {
        foreach ($lines as $line) {
            if (($line[$field] ?? '') !== '') {
                return $line[$field];
            }
        }

        return null;
    }

    private function normalizeCode(mixed $value): string
    {
        if ($value === null || $value === false || $value === '') {
            return '';
        }

        $code = trim((string) $value);

        if ($code === '' || $code === '0') {
            return '';
        }

        if (preg_match('/^\d{7}$/', preg_replace('/\D/', '', $code))) {
            $digits = preg_replace('/\D/', '', $code);

            return substr($digits, 0, 1) . '.' . substr($digits, 1, 3) . '.' . substr($digits, 4, 3);
        }

        return $code;
    }

    private function isSkippableAccount(string $code): bool
    {
        $lower = mb_strtolower($code);

        return str_contains($lower, 'total') || str_contains($lower, 'jumlah');
    }

    private function normalizePartnerCode(mixed $value): ?string
    {
        if ($value === false || $value === null) {
            return null;
        }

        $code = trim((string) $value);

        if ($code === '' || strcasecmp($code, 'false') === 0 || $code === '0') {
            return null;
        }

        return $code;
    }

    private function cellString(mixed $value): string
    {
        if ($value === false || $value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function parseAmount(mixed $value): float
    {
        if ($value === null || $value === '' || $value === false) {
            return 0.0;
        }

        return (float) $value;
    }

    private function parseDateFromCell(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): ?string
    {
        $value = $cell->getValue();

        if ($value === null || $value === '') {
            return null;
        }

        if (ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return $this->parseDate($value);
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            if ($serial < 1000) {
                return null;
            }

            return ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d');
        }

        $string = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $string)) {
            return $string;
        }

        try {
            return Carbon::parse($string)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
