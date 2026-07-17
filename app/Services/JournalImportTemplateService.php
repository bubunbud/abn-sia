<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalImportTemplateService
{
    public function downloadResponse(): StreamedResponse
    {
        $spreadsheet = $this->build();
        $filename = 'template-import-jurnal-historis.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jurnal');

        $company = config('app.company_name', 'NAMA PERUSAHAAN');

        $sheet->setCellValue('E1', $company);
        $sheet->setCellValue('E2', 'JURNAL');
        $sheet->setCellValue('E3', 'Periode');
        $sheet->setCellValue('F3', 1);

        $sheet->setCellValue('E4', 'Tipe');
        $sheet->setCellValue('F4', 'Tanggal');
        $sheet->setCellValue('G4', 'No Bukti');
        $sheet->setCellValue('H4', 'No Giro');
        $sheet->setCellValue('I4', 'Pihak Kedua');
        $sheet->setCellValue('J4', 'Kode');
        $sheet->setCellValue('K4', 'Kode');
        $sheet->setCellValue('L4', 'Account');
        $sheet->setCellValue('M4', 'Nama Kode Account');
        $sheet->setCellValue('N4', 'Deskripsi');
        $sheet->setCellValue('O4', 'Keterangan');
        $sheet->setCellValue('P4', 'Debet');
        $sheet->setCellValue('Q4', 'Kredit');
        $sheet->setCellValue('R4', 'Kurs');
        $sheet->setCellValue('S4', 'Posted to IDR');
        $sheet->setCellValue('J5', 'P. Kedua');
        $sheet->setCellValue('K5', 'Account');
        $sheet->setCellValue('L5', 'Lawan');
        $sheet->setCellValue('S5', 'Debet');
        $sheet->setCellValue('T5', 'Kredit');
        $sheet->setCellValue('G6', 1);
        $sheet->setCellValue('H6', 2);

        $sampleRows = [
            ['Bank Masuk', '2026-01-02', '1/I', null, 'RS. Immanuel', null, '1.121.001', null, 'BCA IDR A/C 2783000474', 'Inv. 537762, 774 ; RS. Immanuel', null, 184790300, 0, 1, 184790300, 0],
            ['Bank Masuk', null, '1/I', null, 'RS. Immanuel', 'PDL 011', '1.141.001', '1.121.001', 'Piutang Dagang Lokal', 'Inv. 537762 ; RS. Immanuel', null, 0, 86535600, 1, 0, 86535600],
            ['Bank Masuk', null, '1/I', null, 'RS. Immanuel', 'PDL 011', '1.141.001', '1.121.001', 'Piutang Dagang Lokal', 'Inv. 537774 ; RS. Immanuel', null, 0, 98257200, 1, 0, 98257200],
            ['Bank Masuk', null, '1/I', null, 'RS. Immanuel', null, '9.111.002', '1.121.001', 'Beban Transfer', '- By Transfer', null, 2500, 0, 1, 2500, 0],
            ['Bank Keluar', '2026-01-03', 'BCA 001', 'EL 130805', 'Nama Pihak Kedua', 'HDL 115', '2.111.001', '1.121.001', 'Hutang Dagang Lokal', 'Pembayaran hutang', null, 1665000, 0, 1, 1665000, 0],
            ['Bank Keluar', null, 'BCA 001', 'EL 130805', 'Nama Pihak Kedua', null, '1.121.001', null, 'BCA IDR A/C 2783000474', 'Pembayaran hutang', null, 0, 1665000, 1, 0, 1665000],
        ];

        $row = 7;
        foreach ($sampleRows as $data) {
            $sheet->setCellValue("E{$row}", $data[0]);
            $sheet->setCellValue("F{$row}", $data[1]);
            $sheet->setCellValue("G{$row}", $data[2]);
            if ($data[3]) {
                $sheet->setCellValue("H{$row}", $data[3]);
            }
            $sheet->setCellValue("I{$row}", $data[4]);
            if ($data[5]) {
                $sheet->setCellValue("J{$row}", $data[5]);
            }
            $sheet->setCellValue("K{$row}", $data[6]);
            if ($data[7]) {
                $sheet->setCellValue("L{$row}", $data[7]);
            }
            $sheet->setCellValue("M{$row}", $data[8]);
            $sheet->setCellValue("N{$row}", $data[9]);
            if ($data[10]) {
                $sheet->setCellValue("O{$row}", $data[10]);
            }
            $sheet->setCellValue("P{$row}", $data[11]);
            $sheet->setCellValue("Q{$row}", $data[12]);
            $sheet->setCellValue("R{$row}", $data[13]);
            $sheet->setCellValue("S{$row}", $data[14]);
            $sheet->setCellValue("T{$row}", $data[15]);
            $row++;
        }

        $noteRow = $row + 1;
        $sheet->setCellValue("E{$noteRow}", 'Catatan:');
        $sheet->setCellValue("F{$noteRow}", 'Satu baris = satu baris akun. Header (Tipe, Tanggal, No Bukti) boleh diulang atau dikosongkan (carry-forward). Total Debet harus = Total Kredit per No Bukti.');

        $sheet->getStyle('E4:T5')->getFont()->setBold(true);
        $sheet->getStyle('E4:T5')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F3F4F6');
        $sheet->getStyle('E2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("F{$noteRow}:T{$noteRow}")->getAlignment()->setWrapText(true);

        foreach (range('E', 'T') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
