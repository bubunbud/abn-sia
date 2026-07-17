<?php

namespace App\Services;

use App\Models\Account;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralLedgerExportService
{
    private const HEADER_ROW = 5;

    private const DATA_START_ROW = 6;

    public function __construct(private GeneralLedgerService $generalLedgerService)
    {
    }

    public function downloadResponse(string $dateFrom, string $dateTo, ?int $accountId = null): StreamedResponse
    {
        $spreadsheet = $this->build($dateFrom, $dateTo, $accountId);
        $from = Carbon::parse($dateFrom)->format('Ymd');
        $to = Carbon::parse($dateTo)->format('Ymd');

        if ($accountId) {
            $account = Account::find($accountId);
            $code = $account ? str_replace('.', '-', $account->code) : 'akun';
            $filename = "general-ledger-{$code}-{$from}-{$to}.xlsx";
        } else {
            $filename = "general-ledger-semua-akun-{$from}-{$to}.xlsx";
        }

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function build(string $dateFrom, string $dateTo, ?int $accountId = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($accountId ? 'GL Akun' : 'GL Semua Akun');

        if ($accountId) {
            $account = Account::findOrFail($accountId);
            $lines = $this->generalLedgerService->accountDetail($account, $dateFrom, $dateTo);
            $groups = collect([[
                'account' => $account,
                'lines' => $lines,
                'total_debit' => (float) $lines->sum(fn (array $row) => (float) $row['line']->debit),
                'total_credit' => (float) $lines->sum(fn (array $row) => (float) $row['line']->credit),
                'ending_balance' => (float) ($lines->last()['balance'] ?? 0),
            ]]);
            $title = 'BUKU BESAR';
            $this->writeSheetHeader($sheet, $title, $dateFrom, $dateTo, false);
            $sheet->setCellValue('A4', $account->code.' — '.$account->name);
            $sheet->getStyle('A4')->getFont()->setBold(true);
        } else {
            $groups = $this->generalLedgerService->allAccountsDetail($dateFrom, $dateTo);
            $title = 'BUKU BESAR — SEMUA AKUN';
            $this->writeSheetHeader($sheet, $title, $dateFrom, $dateTo, true);
        }

        $row = self::DATA_START_ROW;
        $allAccounts = $accountId === null;

        foreach ($groups as $ledger) {
            if ($allAccounts) {
                $row = $this->writeAccountHeader($sheet, $row, $ledger['account']);
            }

            foreach ($ledger['lines'] as $item) {
                $this->writeLineRow($sheet, $row, $item, $allAccounts ? $ledger['account'] : null);
                $row++;
            }

            if ($ledger['lines']->isNotEmpty()) {
                $this->writeSubtotalRow(
                    $sheet,
                    $row,
                    $ledger['total_debit'],
                    $ledger['total_credit'],
                    $ledger['ending_balance'],
                    $allAccounts,
                );
                $row += 2;
            }
        }

        $dataEndRow = max(self::HEADER_ROW, $row - 1);
        $this->applyStyles($sheet, $dataEndRow, $allAccounts);

        return $spreadsheet;
    }

    private function writeSheetHeader(Worksheet $sheet, string $title, string $dateFrom, string $dateTo, bool $allAccounts): void
    {
        $company = config('app.company_name') ?: config('app.name', 'ABN-SIA');

        $sheet->setCellValue('A1', $company);
        $sheet->setCellValue('A2', $title);
        $sheet->setCellValue('A3', 'Periode');
        $sheet->setCellValue('B3', Carbon::parse($dateFrom)->format('d M Y').' s/d '.Carbon::parse($dateTo)->format('d M Y'));

        if ($allAccounts) {
            $headers = [
                'A' => 'Kode Akun',
                'B' => 'Nama Akun',
                'C' => 'Tanggal',
                'D' => 'No Bukti',
                'E' => 'No Doc',
                'F' => 'Pihak Kedua',
                'G' => 'Deskripsi',
                'H' => 'Debet',
                'I' => 'Kredit',
                'J' => 'Saldo',
            ];
        } else {
            $headers = [
                'A' => 'Tanggal',
                'B' => 'No Bukti',
                'C' => 'No Doc',
                'D' => 'Pihak Kedua',
                'E' => 'Deskripsi',
                'F' => 'Debet',
                'G' => 'Kredit',
                'H' => 'Saldo',
            ];
        }

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}".self::HEADER_ROW, $label);
        }
    }

    private function writeAccountHeader(Worksheet $sheet, int $row, Account $account): int
    {
        $sheet->setCellValue("A{$row}", $account->code);
        $sheet->setCellValue("B{$row}", $account->name.' ('.$account->group_name.')');
        $sheet->mergeCells("B{$row}:G{$row}");
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:J{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EDE9FE');

        return $row + 1;
    }

    private function writeLineRow(Worksheet $sheet, int $row, array $item, ?Account $account): void
    {
        $line = $item['line'];
        $description = $line->description ?? $line->journalEntry->notes ?? $line->journalEntry->description;

        if ($account) {
            $sheet->setCellValue("A{$row}", $account->code);
            $sheet->setCellValue("B{$row}", $account->name);
            $sheet->setCellValue("C{$row}", $line->journalEntry->entry_date->format('d-M-y'));
            $sheet->setCellValue("D{$row}", $line->journalEntry->entry_number);
            $sheet->setCellValue("E{$row}", $line->journalEntry->document_number);
            $sheet->setCellValue("F{$row}", $line->journalEntry->partner?->displayName());
            $sheet->setCellValue("G{$row}", $description);
            $debitCol = 'H';
            $creditCol = 'I';
            $balanceCol = 'J';
        } else {
            $sheet->setCellValue("A{$row}", $line->journalEntry->entry_date->format('d-M-y'));
            $sheet->setCellValue("B{$row}", $line->journalEntry->entry_number);
            $sheet->setCellValue("C{$row}", $line->journalEntry->document_number);
            $sheet->setCellValue("D{$row}", $line->journalEntry->partner?->displayName());
            $sheet->setCellValue("E{$row}", $description);
            $debitCol = 'F';
            $creditCol = 'G';
            $balanceCol = 'H';
        }

        if ((float) $line->debit > 0) {
            $sheet->setCellValue("{$debitCol}{$row}", round((float) $line->debit, 2));
        }
        if ((float) $line->credit > 0) {
            $sheet->setCellValue("{$creditCol}{$row}", round((float) $line->credit, 2));
        }

        $sheet->setCellValue("{$balanceCol}{$row}", round((float) $item['balance'], 2));
    }

    private function writeSubtotalRow(
        Worksheet $sheet,
        int $row,
        float $totalDebit,
        float $totalCredit,
        float $endingBalance,
        bool $allAccounts,
    ): void {
        if ($allAccounts) {
            $sheet->setCellValue("G{$row}", 'Subtotal');
            $debitCol = 'H';
            $creditCol = 'I';
            $balanceCol = 'J';
            $rangeEnd = 'J';
        } else {
            $sheet->setCellValue("E{$row}", 'Subtotal');
            $debitCol = 'F';
            $creditCol = 'G';
            $balanceCol = 'H';
            $rangeEnd = 'H';
        }

        $sheet->setCellValue("{$debitCol}{$row}", round($totalDebit, 2));
        $sheet->setCellValue("{$creditCol}{$row}", round($totalCredit, 2));
        $sheet->setCellValue("{$balanceCol}{$row}", round($endingBalance, 2));

        $sheet->getStyle("A{$row}:{$rangeEnd}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$rangeEnd}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F9FAFB');
    }

    private function applyStyles(Worksheet $sheet, int $dataEndRow, bool $allAccounts): void
    {
        $lastCol = $allAccounts ? 'J' : 'H';
        $amountStartCol = $allAccounts ? 'H' : 'F';
        $freezeCol = $allAccounts ? 'C' : 'A';

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);

        $sheet->getStyle('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW)->getFont()->setBold(true);
        $sheet->getStyle('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F3F4F6');
        $sheet->getStyle('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($allAccounts) {
            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(28);
            $sheet->getColumnDimension('C')->setWidth(12);
            $sheet->getColumnDimension('D')->setWidth(14);
            $sheet->getColumnDimension('E')->setWidth(12);
            $sheet->getColumnDimension('F')->setWidth(22);
            $sheet->getColumnDimension('G')->setWidth(32);
            $sheet->getColumnDimension('H')->setWidth(14);
            $sheet->getColumnDimension('I')->setWidth(14);
            $sheet->getColumnDimension('J')->setWidth(14);
        } else {
            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(14);
            $sheet->getColumnDimension('C')->setWidth(12);
            $sheet->getColumnDimension('D')->setWidth(22);
            $sheet->getColumnDimension('E')->setWidth(32);
            $sheet->getColumnDimension('F')->setWidth(14);
            $sheet->getColumnDimension('G')->setWidth(14);
            $sheet->getColumnDimension('H')->setWidth(14);
        }

        if ($dataEndRow >= self::DATA_START_ROW) {
            $sheet->getStyle("{$amountStartCol}".self::DATA_START_ROW.":{$lastCol}{$dataEndRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $sheet->getStyle("{$amountStartCol}".self::DATA_START_ROW.":{$lastCol}{$dataEndRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle('A'.self::HEADER_ROW.":{$lastCol}{$dataEndRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()
                ->setRGB('E5E7EB');

            $sheet->setAutoFilter('A'.self::HEADER_ROW.":{$lastCol}{$dataEndRow}");
        }

        $sheet->freezePane($freezeCol.self::DATA_START_ROW);
    }
}
