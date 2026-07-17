<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitLossExportService
{
    private const DATA_START_ROW = 7;

    private const HEADER_ROW = 5;

    private const FIRST_AMOUNT_COL = 4; // D

    public function __construct(
        private ProfitLossSummaryService $profitLossSummaryService,
        private ProfitLossDetailService $profitLossDetailService,
    ) {
    }

    public function downloadResponse(int $year, bool $hideZero = false): StreamedResponse
    {
        $spreadsheet = $this->build($year, $hideZero);
        $filename = "profit-loss-{$year}.xlsx";

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
        $summaryReport = $this->profitLossSummaryService->generate($year, $hideZero);
        $detailReport = $this->profitLossDetailService->generate($year, $hideZero);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $summarySheet = new Worksheet($spreadsheet, 'P&L');
        $detailSheet = new Worksheet($spreadsheet, 'P&LDetail');

        $spreadsheet->addSheet($summarySheet, 0);
        $spreadsheet->addSheet($detailSheet, 1);
        $spreadsheet->setActiveSheetIndex(0);

        $this->writeSheet($summarySheet, $summaryReport, $year, false);
        $this->writeSheet($detailSheet, $detailReport, $year, true);

        return $spreadsheet;
    }

    private function writeSheet(Worksheet $sheet, array $report, int $year, bool $isDetail): void
    {
        $company = config('app.company_name') ?: config('app.name', 'ABN-SIA');
        $columns = $report['columns'];
        $lastAmountColIndex = self::FIRST_AMOUNT_COL + count($columns) - 1;
        $lastCol = Coordinate::stringFromColumnIndex($lastAmountColIndex);

        $sheet->setCellValue('B1', $company);
        $sheet->setCellValue('B2', $isDetail ? 'LAPORAN LABA RUGI - DETAIL' : 'LAPORAN LABA RUGI');
        $sheet->setCellValue('B3', 'TAHUN '.$year);

        for ($month = 1; $month <= 12; $month++) {
            $col = Coordinate::stringFromColumnIndex(self::FIRST_AMOUNT_COL + $month);
            $sheet->setCellValue("{$col}1", $month);
        }

        if ($isDetail) {
            $sheet->setCellValue('A'.self::HEADER_ROW, 'KODE');
            $sheet->setCellValue('B'.self::HEADER_ROW, 'U R A I A N');
            $sheet->mergeCells('B'.self::HEADER_ROW.':C'.self::HEADER_ROW);
        } else {
            $sheet->setCellValue('B'.self::HEADER_ROW, 'U R A I A N');
            $sheet->mergeCells('B'.self::HEADER_ROW.':C'.self::HEADER_ROW);
        }

        $colIndex = self::FIRST_AMOUNT_COL;
        foreach ($columns as $column) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue("{$col}".self::HEADER_ROW, $column['label']);
            $colIndex++;
        }

        $row = self::DATA_START_ROW;
        foreach ($report['rows'] as $item) {
            $this->writeRow($sheet, $row, $item, $columns, $isDetail, $lastCol);
            $row++;
        }

        $dataEndRow = max(self::DATA_START_ROW, $row - 1);
        $this->applyBaseStyles($sheet, $lastCol, $dataEndRow, $isDetail);
    }

    private function writeRow(
        Worksheet $sheet,
        int $row,
        array $item,
        array $columns,
        bool $isDetail,
        string $lastCol
    ): void {
        $type = $item['type'] ?? 'line';

        if ($isDetail) {
            if ($type === 'detail') {
                $sheet->setCellValue("A{$row}", $item['code'] ?? '');
                $sheet->setCellValue("C{$row}", $item['label'] ?? '');
            } else {
                $sheet->setCellValue("C{$row}", $item['label'] ?? '');
            }
        } else {
            if (in_array($type, ['computed', 'subtotal'], true)) {
                $sheet->setCellValue("B{$row}", $item['label'] ?? '');
            } else {
                $sheet->setCellValue("C{$row}", $item['label'] ?? '');
            }
        }

        if ($type !== 'section') {
            $colIndex = self::FIRST_AMOUNT_COL;
            foreach ($columns as $column) {
                $amount = $item['amounts'][$column['key']] ?? null;
                if ($amount !== null && abs((float) $amount) >= 0.005) {
                    $col = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue("{$col}{$row}", round((float) $amount, 2));
                }
                $colIndex++;
            }
        }

        if ($type === 'section') {
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('EDE9FE');
        } elseif (in_array($type, ['computed', 'subtotal', 'total'], true)) {
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F3F4F6');
        }
    }

    private function applyBaseStyles(Worksheet $sheet, string $lastCol, int $dataEndRow, bool $isDetail): void
    {
        $headerRow = self::HEADER_ROW;
        $dataStart = self::DATA_START_ROW;
        $firstAmountCol = Coordinate::stringFromColumnIndex(self::FIRST_AMOUNT_COL);

        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('B3')->getFont()->setBold(true);

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F3F4F6');
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension(1)->setVisible(false);

        $sheet->getColumnDimension('A')->setWidth($isDetail ? 14 : 8);
        $sheet->getColumnDimension('B')->setWidth($isDetail ? 10 : 28);
        $sheet->getColumnDimension('C')->setWidth($isDetail ? 40 : 32);

        for ($i = self::FIRST_AMOUNT_COL; $i <= Coordinate::columnIndexFromString($lastCol); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(14);
        }

        if ($dataEndRow >= $dataStart) {
            $sheet->getStyle("{$firstAmountCol}{$dataStart}:{$lastCol}{$dataEndRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $sheet->getStyle("{$firstAmountCol}{$dataStart}:{$lastCol}{$dataEndRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("A{$dataStart}:{$lastCol}{$dataEndRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()
                ->setRGB('E5E7EB');
        }

        $sheet->freezePane('D'.self::DATA_START_ROW);
    }
}
