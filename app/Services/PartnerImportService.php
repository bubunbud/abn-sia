<?php

namespace App\Services;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class PartnerImportService
{
    public function import(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("File tidak ditemukan: {$filePath}");
        }

        $rows = $this->readRows($filePath);

        if (empty($rows)) {
            throw new RuntimeException('Tidak ada data Pihak Kedua yang dapat diimpor.');
        }

        return DB::transaction(function () use ($rows) {
            $stats = ['imported' => 0, 'piutang' => 0, 'hutang' => 0];

            foreach ($rows as $row) {
                Partner::updateOrCreate(
                    ['code' => $row['code']],
                    [
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'region' => $row['region'],
                        'status_label' => $row['status_label'],
                        'is_active' => true,
                    ]
                );

                $stats['imported']++;
                $stats[$row['type'] === 'customer' ? 'piutang' : 'hutang']++;
            }

            return $stats;
        });
    }

    private function readRows(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('Kode P.Kedua') ?? $spreadsheet->getActiveSheet();

        $headerRow = null;
        $rows = [];

        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }

            if ($headerRow === null) {
                if ($this->isHeaderRow($cells)) {
                    $headerRow = $rowIndex;
                }
                continue;
            }

            $code = trim((string) ($cells[0] ?? ''));

            if ($code === '') {
                continue;
            }

            $rows[] = $this->mapRow($cells);
        }

        if ($headerRow === null) {
            throw new RuntimeException('Baris header tidak ditemukan (kolom: KODE, NAMA, KETERANGAN, STATUS).');
        }

        return $rows;
    }

    private function isHeaderRow(array $cells): bool
    {
        $joined = strtolower(implode('|', array_map(fn ($v) => trim((string) $v), $cells)));

        return str_contains($joined, 'kode') && str_contains($joined, 'nama');
    }

    private function mapRow(array $cells): array
    {
        $code = trim((string) ($cells[0] ?? ''));
        $name = trim((string) ($cells[1] ?? ''));
        $region = trim((string) ($cells[3] ?? ''));
        $statusLabel = trim((string) ($cells[4] ?? ''));

        return [
            'code' => $code,
            'name' => $name,
            'region' => $region !== '' ? $region : null,
            'status_label' => $statusLabel !== '' ? $statusLabel : null,
            'type' => $this->mapType($statusLabel, $code),
        ];
    }

    private function mapType(string $statusLabel, string $code): string
    {
        $status = strtolower($statusLabel);

        if ($status === 'piutang' || str_starts_with($code, 'PDL')) {
            return 'customer';
        }

        if ($status === 'hutang' || str_starts_with($code, 'HDL')) {
            return 'vendor';
        }

        if (str_starts_with($code, 'FIN')) {
            return 'financial';
        }

        return 'other';
    }
}
