<?php

namespace App\Services;

use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalExportService
{
    /**
     * @param  array{date_from: string, date_to: string, search?: string|null, journal_type_id?: string|null, status?: string|null}  $filters
     */
    public function downloadResponse(array $filters): StreamedResponse
    {
        $spreadsheet = $this->build($filters);
        $from = Carbon::parse($filters['date_from'])->format('Ymd');
        $to = Carbon::parse($filters['date_to'])->format('Ymd');
        $filename = "export-jurnal-{$from}-{$to}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @param  array{date_from: string, date_to: string, search?: string|null, journal_type_id?: string|null, status?: string|null}  $filters
     */
    public function build(array $filters): Spreadsheet
    {
        $entries = $this->queryEntries($filters)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jurnal');

        $company = config('app.company_name') ?: config('app.name', 'ABN-SIA');
        $dateFrom = Carbon::parse($filters['date_from']);
        $dateTo = Carbon::parse($filters['date_to']);

        $sheet->setCellValue('A1', $company);
        $sheet->setCellValue('A2', 'JURNAL');
        $sheet->setCellValue('A3', 'Periode');
        $sheet->setCellValue('B3', $dateFrom->format('d M Y').' s/d '.$dateTo->format('d M Y'));

        // Header baris 4–5 (mirip layout SIA / gambar referensi)
        $sheet->setCellValue('A4', 'Tipe Jurnal');
        $sheet->setCellValue('B4', 'Tanggal');
        $sheet->setCellValue('C4', 'No Bukti');
        $sheet->setCellValue('D4', 'No Giro');
        $sheet->setCellValue('E4', 'Pihak Kedua');
        $sheet->setCellValue('F4', 'Kode');
        $sheet->setCellValue('G4', 'Kode');
        $sheet->setCellValue('H4', 'Account');
        $sheet->setCellValue('I4', 'Nama Kode Account');
        $sheet->setCellValue('J4', 'Deskripsi');
        $sheet->setCellValue('K4', 'Keterangan');
        $sheet->setCellValue('L4', 'Debet');
        $sheet->setCellValue('M4', 'Kredit');
        $sheet->setCellValue('N4', 'Kurs');
        $sheet->setCellValue('O4', 'Posted to IDR');
        $sheet->mergeCells('O4:P4');

        // Baris 5: sub-header + label filter (agar AutoFilter berjudul jelas)
        $sheet->setCellValue('A5', 'Tipe Jurnal');
        $sheet->setCellValue('B5', 'Tanggal');
        $sheet->setCellValue('C5', 'No Bukti');
        $sheet->setCellValue('D5', 'No Giro');
        $sheet->setCellValue('E5', 'Pihak Kedua');
        $sheet->setCellValue('F5', 'P. Kedua');
        $sheet->setCellValue('G5', 'Account');
        $sheet->setCellValue('H5', 'Lawan');
        $sheet->setCellValue('I5', 'Nama Kode Account');
        $sheet->setCellValue('J5', 'Deskripsi');
        $sheet->setCellValue('K5', 'Keterangan');
        $sheet->setCellValue('L5', 'Debet');
        $sheet->setCellValue('M5', 'Kredit');
        $sheet->setCellValue('N5', 'Kurs');
        $sheet->setCellValue('O5', 'Debet');
        $sheet->setCellValue('P5', 'Kredit');

        foreach (['A', 'B', 'C', 'D', 'E', 'I', 'J', 'K', 'L', 'M', 'N'] as $col) {
            $sheet->mergeCells("{$col}4:{$col}5");
        }

        $row = 6;
        foreach ($entries as $entry) {
            foreach ($entry->lines->sortBy('line_order') as $line) {
                $partner = $line->partner ?? $entry->partner;
                $rate = (float) ($line->exchange_rate ?? $entry->exchange_rate ?? 1);
                if ($rate <= 0) {
                    $rate = 1;
                }

                $debit = (float) $line->debit;
                $credit = (float) $line->credit;
                $idrDebit = (float) ($line->amount_idr_debit ?? ($debit * $rate));
                $idrCredit = (float) ($line->amount_idr_credit ?? ($credit * $rate));

                $sheet->setCellValue("A{$row}", $entry->journalType?->name);
                $sheet->setCellValue("B{$row}", $entry->entry_date?->format('d-M-y'));
                $sheet->setCellValue("C{$row}", $entry->entry_number);
                $sheet->setCellValue("D{$row}", $entry->document_number);
                $sheet->setCellValue("E{$row}", $partner?->name);
                $sheet->setCellValue("F{$row}", $partner?->code);
                $sheet->setCellValue("G{$row}", $line->account?->code);
                $sheet->setCellValue("H{$row}", $line->counterAccount?->code);
                $sheet->setCellValue("I{$row}", $line->account?->name);
                $sheet->setCellValue("J{$row}", $line->description);
                $sheet->setCellValue("K{$row}", $line->notes);
                $sheet->setCellValue("L{$row}", $debit);
                $sheet->setCellValue("M{$row}", $credit);
                $sheet->setCellValue("N{$row}", $rate);
                $sheet->setCellValue("O{$row}", $idrDebit > 0 ? $idrDebit : null);
                $sheet->setCellValue("P{$row}", $idrCredit > 0 ? $idrCredit : null);

                $row++;
            }
        }

        $lastDataRow = max(5, $row - 1);

        $this->applyStyles($sheet, $lastDataRow);

        return $spreadsheet;
    }

    /**
     * @param  array{date_from: string, date_to: string, search?: string|null, journal_type_id?: string|null, status?: string|null}  $filters
     */
    private function queryEntries(array $filters): Builder
    {
        return JournalEntry::query()
            ->with([
                'journalType',
                'partner',
                'lines' => fn ($q) => $q->orderBy('line_order'),
                'lines.account',
                'lines.counterAccount',
                'lines.partner',
            ])
            ->whereDate('entry_date', '>=', $filters['date_from'])
            ->whereDate('entry_date', '<=', $filters['date_to'])
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('entry_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['journal_type_id']), fn ($q) => $q->where('journal_type_id', $filters['journal_type_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderBy('entry_date')
            ->orderBy('id');
    }

    private function applyStyles($sheet, int $lastDataRow): void
    {
        $headerFill = Fill::FILL_SOLID;
        $gray = 'F3F4F6';
        $amber = 'F5E6C8';

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);

        $sheet->getStyle('A4:P5')->getFont()->setBold(true);
        $sheet->getStyle('A4:N5')->getFill()->setFillType($headerFill)->getStartColor()->setRGB($gray);
        $sheet->getStyle('O4:P5')->getFill()->setFillType($headerFill)->getStartColor()->setRGB($amber);
        $sheet->getStyle('A4:P5')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        if ($lastDataRow >= 6) {
            $sheet->getStyle("L6:N{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $sheet->getStyle("O6:P{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $sheet->getStyle("L6:P{$lastDataRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("O6:P{$lastDataRow}")
                ->getFill()
                ->setFillType($headerFill)
                ->getStartColor()
                ->setRGB('FFF8EE');
            $sheet->getStyle("A4:P{$lastDataRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()
                ->setRGB('D1D5DB');
        }

        $sheet->getRowDimension(4)->setRowHeight(18);
        $sheet->getRowDimension(5)->setRowHeight(18);

        $widths = [
            'A' => 14, 'B' => 12, 'C' => 12, 'D' => 12, 'E' => 22,
            'F' => 12, 'G' => 12, 'H' => 12, 'I' => 28, 'J' => 36,
            'K' => 28, 'L' => 14, 'M' => 14, 'N' => 10, 'O' => 14, 'P' => 14,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        if ($lastDataRow >= 6) {
            $sheet->setAutoFilter("A5:P{$lastDataRow}");
        }
        $sheet->freezePane('A6');
    }
}
