<?php

namespace Database\Seeders;

use App\Models\JournalType;
use Illuminate\Database\Seeder;

class JournalTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'bank_masuk', 'name' => 'Bank Masuk', 'prefix' => 'BCM', 'number_padding' => 3, 'next_number' => 1],
            ['code' => 'bank_keluar', 'name' => 'Bank Keluar', 'prefix' => 'BKK', 'number_padding' => 3, 'next_number' => 1],
            ['code' => 'kas_keluar', 'name' => 'Kas Keluar', 'prefix' => 'KK', 'number_padding' => 2, 'next_number' => 1],
            ['code' => 'penjualan', 'name' => 'Penjualan', 'prefix' => 'INV', 'number_padding' => 6, 'next_number' => 537829],
            ['code' => 'pembelian', 'name' => 'Pembelian', 'prefix' => 'PO', 'number_padding' => 1, 'next_number' => 1],
            ['code' => 'jurnal_umum', 'name' => 'Jurnal Umum', 'prefix' => 'JE', 'number_padding' => 3, 'next_number' => 1],
        ];

        foreach ($types as $type) {
            JournalType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
