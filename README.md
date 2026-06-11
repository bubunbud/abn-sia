# ABN SIA — Sistem Informasi Akuntansi

Aplikasi akuntansi berbasis **Laravel 10 + MySQL** dengan UI bergaya Odoo, mengacu pada sistem manual PT. PERMATECH MITRA ABADI.

## Fitur Fase 1 (tersedia)

- Chart of Accounts (hierarki H/D)
- Journal Entries (draft/posted, No Bukti otomatis & editable)
- General Ledger dengan navigasi JE ↔ GL
- Sidebar menu Accounting lengkap
- Placeholder: Trial Balance, Laporan, Period Closing, Tax

## Persyaratan

- PHP 8.1+
- Composer
- Node.js & npm
- MySQL (XAMPP)

## Instalasi

1. Salin `.env` dan sesuaikan kredensial database:

```env
DB_DATABASE=abn_sia
DB_USERNAME=root
DB_PASSWORD=your_password
```

2. Buat database:

```sql
CREATE DATABASE abn_sia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Jalankan migrasi & seeder:

```bash
composer install
php artisan migrate --seed
npm install
npm run build
```

4. Jalankan server:

```bash
php artisan serve
```

Buka: http://localhost:8000/accounting

## No Bukti (otomatis & manual)

| Tipe Jurnal   | Prefix | Contoh     |
|---------------|--------|------------|
| Bank Masuk    | BCM    | BCM-001    |
| Bank Keluar   | BKK    | BKK-001    |
| Kas Keluar    | KK     | KK-01      |
| Penjualan     | INV    | INV-537829 |
| Pembelian     | PO     | PO-1       |
| Jurnal Umum   | JE     | JE-001     |

- Kosongkan field **No Bukti** saat buat jurnal → nomor otomatis
- Isi manual → tersimpan apa adanya (`is_manual_number = true`)

## Flow Navigasi

1. **Journal Entries** → klik No Bukti → Detail → klik kode akun → **GL** (filtered + highlight)
2. **General Ledger** → klik No Bukti → kembali ke **Journal Entry Detail**

## Struktur COA

Format kode: `1.111.001` (titik) — kompatibel dengan jurnal manual.

- **H** = Header (kode berakhiran `.000`, tidak bisa diposting)
- **D** = Detail (bisa diposting)

### Import COA dari Excel

File referensi: `storage/app/imports/coa-rma.xlsx` (dari `CAO RMA.xlsx`)

```bash
php artisan coa:import
# atau path custom:
php artisan coa:import "C:\path\to\CAO RMA.xlsx"
```

Kolom yang dibaca (sheet `COA`):
`KODE AKUN` | `NAMA KODE AKUN` | `KELOMPOK` | `POS SALDO` | `GROUP AKUN`

### Import Pihak Kedua dari Excel

File referensi: `storage/app/imports/pihak-kedua-rma.xlsx`

```bash
php artisan partners:import
```

Kolom (sheet `Kode P.Kedua`): `KODE` | `NAMA` | `KETERANGAN` | `STATUS`
- PDL → Piutang (customer)
- HDL → Hutang (vendor)

### Import Jurnal Historis (Data Lengkap)

File referensi: `storage/app/imports/jurnal-historis.xlsx` atau `sample_sia.xlsx` (sheet `Jurnal`)

```bash
# Validasi dulu
php artisan journals:import --generate-periods --dry-run

# Import penuh
php artisan journals:import --generate-periods

# Opsi tambahan
php artisan journals:import file.xlsx --replace --force --generate-periods
```

Via web: **Journal Entries → Import Historis**

Urutan lengkap:
1. `php artisan coa:import`
2. `php artisan partners:import`
3. `php artisan journals:import --generate-periods`

## Langkah Berikutnya

- Tax Codes & e-Faktur Export
