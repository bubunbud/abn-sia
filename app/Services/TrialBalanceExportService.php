<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalanceExportService
{
    private const MONTH_LABELS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public function __construct(private TrialBalanceService $trialBalanceService)
    {
    }

    public function downloadResponse(int $year, bool $hideZero = false): StreamedResponse
    {
        $spreadsheet = $this->build($year, $hideZero);
        $filename = "trial-balance-{$year}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function build(int $year, bool $hideZero = false): Spreadsheet
    {
        $report = $this->trialBalanceService->generateYearly($year, $hideZero);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Neraca Saldo '.$year);

        $company = config('app.company_name') ?: config('app.name', 'ABN-SIA');

        // 14 grup: Saldo Awal + 12 bulan + Saldo Akhir → kolom B..AC (28 kolom angka) + A akun
        $lastCol = Coordinate::stringFromColumnIndex(29); // AC

        $sheet->setCellValue('A1', $company);
        $sheet->setCellValue('A2', 'NERACA SALDO');
        $sheet->setCellValue('A3', 'Tahun '.$year);

        $groups = [
            ['label' => 'Saldo Awal', 'key' => 'opening'],
        ];
        foreach (self::MONTH_LABELS as $month => $label) {
            $groups[] = ['label' => $label, 'key' => 'month_'.$month];
        }
        $groups[] = ['label' => 'Saldo Akhir', 'key' => 'closing'];

        // Header baris 5–6
        $sheet->setCellValue('A5', 'Account');
        $sheet->mergeCells('A5:A6');

        $colIndex = 2; // B
        foreach ($groups as $group) {
            $startCol = Coordinate::stringFromColumnIndex($colIndex);
            $endCol = Coordinate::stringFromColumnIndex($colIndex + 1);

            $sheet->setCellValue("{$startCol}5", $group['label']);
            $sheet->mergeCells("{$startCol}5:{$endCol}5");
            $sheet->setCellValue("{$startCol}6", 'Debet');
            $sheet->setCellValue("{$endCol}6", 'Kredit');

            $colIndex += 2;
        }

        $row = 7;
        $dataStartRow = 7;
        $plSeparatorRow = null;

        foreach ($report['rows'] as $index => $item) {
            if ($report['pl_separator_after_index'] !== null
                && $index === $report['pl_separator_after_index'] + 1
            ) {
                $plSeparatorRow = $row;
            }

            $account = $item['account'];
            $sheet->setCellValue("A{$row}", $account->code.' — '.$account->name);

            $colIndex = 2;
            $this->writeAmountPair($sheet, $colIndex, $row, $item['opening_debit'], $item['opening_credit']);
            $colIndex += 2;

            for ($m = 1; $m <= 12; $m++) {
                $this->writeAmountPair(
                    $sheet,
                    $colIndex,
                    $row,
                    $item['months'][$m]['debit'],
                    $item['months'][$m]['credit']
                );
                $colIndex += 2;
            }

            $this->writeAmountPair($sheet, $colIndex, $row, $item['closing_debit'], $item['closing_credit']);
            $row++;
        }

        $totalRow = $row;
        $dataEndRow = max($dataStartRow, $totalRow - 1);

        $sheet->setCellValue("A{$totalRow}", 'Total');
        $sheet->getStyle("A{$totalRow}")->getFont()->setBold(true);

        $colIndex = 2;
        $totals = $report['totals'];
        $this->writeAmountPair($sheet, $colIndex, $totalRow, $totals['opening_debit'], $totals['opening_credit']);
        $colIndex += 2;

        for ($m = 1; $m <= 12; $m++) {
            $this->writeAmountPair(
                $sheet,
                $colIndex,
                $totalRow,
                $totals['months'][$m]['debit'],
                $totals['months'][$m]['credit']
            );
            $colIndex += 2;
        }

        $this->writeAmountPair($sheet, $colIndex, $totalRow, $totals['closing_debit'], $totals['closing_credit']);

        // Nomor grup 1–14 di baris bawah Total
        $indexRow = $totalRow + 1;
        $colIndex = 2;
        for ($i = 1; $i <= 14; $i++) {
            $startCol = Coordinate::stringFromColumnIndex($colIndex);
            $endCol = Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue("{$startCol}{$indexRow}", $i);
            $sheet->mergeCells("{$startCol}{$indexRow}:{$endCol}{$indexRow}");
            $sheet->getStyle("{$startCol}{$indexRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $colIndex += 2;
        }

        $this->applyStyles($sheet, $lastCol, $dataStartRow, $dataEndRow, $totalRow, $indexRow, $plSeparatorRow);

        return $spreadsheet;
    }

    private function writeAmountPair($sheet, int $colIndex, int $row, float $debit, float $credit): void
    {
        $debitCol = Coordinate::stringFromColumnIndex($colIndex);
        $creditCol = Coordinate::stringFromColumnIndex($colIndex + 1);

        if (abs($debit) >= 0.005) {
            $sheet->setCellValue("{$debitCol}{$row}", round($debit, 2));
        }
        if (abs($credit) >= 0.005) {
            $sheet->setCellValue("{$creditCol}{$row}", round($credit, 2));
        }
    }

    private function applyStyles(
        $sheet,
        string $lastCol,
        int $dataStartRow,
        int $dataEndRow,
        int $totalRow,
        int $indexRow,
        ?int $plSeparatorRow,
    ): void {
        $yellow = 'FFE066';
        $headerBg = 'F3F4F6';

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A3')->getFont()->setItalic(true);

        $sheet->getStyle("A5:{$lastCol}6")->getFont()->setBold(true);
        $sheet->getStyle("A5:{$lastCol}6")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($headerBg);
        $sheet->getStyle("A5:{$lastCol}6")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getColumnDimension('A')->setWidth(42);
        for ($i = 2; $i <= 29; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(12);
        }

        if ($dataEndRow >= $dataStartRow) {
            $sheet->getStyle("B{$dataStartRow}:{$lastCol}{$totalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $sheet->getStyle("B{$dataStartRow}:{$lastCol}{$totalRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EEF2FF');

        // Garis kuning vertikal pemisah antar grup (setiap kolom Kredit = ujung grup)
        for ($group = 0; $group < 14; $group++) {
            $creditColIndex = 3 + ($group * 2); // C, E, G, ...
            $creditCol = Coordinate::stringFromColumnIndex($creditColIndex);
            $sheet->getStyle("{$creditCol}5:{$creditCol}{$indexRow}")
                ->getBorders()
                ->getRight()
                ->setBorderStyle(Border::BORDER_MEDIUM)
                ->getColor()
                ->setRGB($yellow);
        }

        // Border tipis keseluruhan
        $sheet->getStyle("A5:{$lastCol}{$totalRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()
            ->setRGB('D1D5DB');

        // Garis kuning horizontal pemisah BS ↔ Laba/Rugi
        if ($plSeparatorRow !== null && $plSeparatorRow > $dataStartRow) {
            $sepRow = $plSeparatorRow - 1;
            $sheet->getStyle("A{$sepRow}:{$lastCol}{$sepRow}")
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(Border::BORDER_MEDIUM)
                ->getColor()
                ->setRGB($yellow);
        }

        $sheet->freezePane('B7');
        $sheet->getRowDimension(5)->setRowHeight(18);
        $sheet->getRowDimension(6)->setRowHeight(18);
    }
}
