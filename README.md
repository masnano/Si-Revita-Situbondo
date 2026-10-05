<div align="center">

# 🏛️ Si Revita Situbondo
### **Sistem Revitalisasi dan Pelaporan Keuangan Sekota Situbondo**
*Platform Terpadu Akuntansi, Penatausahaan Kas, dan Pelaporan Dana Revitalisasi Sekolah*

[![Laravel](https://img.shields.io/badge/Laravel-10.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![Vite](https://img.shields.io/badge/Vite-4.5-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

<p align="center">
  Didesain khusus untuk satuan pendidikan di lingkungan <strong>Dinas Pendidikan dan Kebudayaan Kabupaten Situbondo</strong><br>
  Memenuhi standar pembukuan kas perbendaharaan daerah (DAK Fisik & APBD).
</p>

</div>

---

## 📑 Daftar Isi
- [Tentang Aplikasi](#-tentang-aplikasi)
- [Fitur Utama](#-fitur-utama)
  - [1. Output 4 Buku Utama Keuangan](#1-output-4-buku-utama-keuangan-bku-bpk-bb-bp)
  - [2. Laporan Realisasi Fisik & Anggaran](#2-laporan-realisasi-anggaran-rab-vs-realisasi)
  - [3. Kuitansi & Bukti Pembayaran Resmi](#3-kuitansi-pengeluaran-siap-cetak)
  - [4. Role & Permission Matrix Granular (Hak Akses Root)](#4-role--permission-management-granular)
- [Teknologi yang Digunakan](#-teknologi-yang-digunakan)
- [Prasyarat Sistem](#-prasyarat-sistem)
- [Panduan Instalasi (Git Clone)](#-panduan-instalasi)
- [Akun Pengguna Bawaan (Demo)](#-akun-pengguna-bawaan-demo)
- [Struktur Direktori Proyek](#-struktur-direktori-proyek)
- [Pengujian Otomatis (Testing)](#-pengujian-otomatis-testing)
- [Lisensi](#-lisensi)

---

## 💡 Tentang Aplikasi

**Si Revita Situbondo** adalah aplikasi web penatausahaan keuangan dan monitoring revitalisasi sarana-prasarana sekolah. Sistem ini mencatat setiap arus transaksi kas dan bank secara terintegrasi (*single-entry to multi-book ledger*), sehingga dari satu input transaksi, sistem secara otomatis menghasilkan output pembukuan resmi:
1. **Buku Kas Umum (BKU)**
2. **Buku Pembantu Kas (BPK)**
3. **Buku Pembantu Bank (BB)**
4. **Buku Pembantu Pajak (BP)**

Semua dokumen siap diekspor ke **Excel / CSV** dan dicetak langsung dengan format standar kedinasan Pemerintah Kabupaten Situbondo lengkap dengan Kop Surat resmi dan lembar pengesahan tanda tangan.

---

## ✨ Fitur Utama

### 1. Output 4 Buku Utama Keuangan (BKU, BPK, BB, BP)
* **Buku Kas Umum (BKU):**
  * Pencatatan kronologis seluruh penerimaan (dana masuk DAK/APBD, bunga bank) dan pengeluaran (belanja material, upah tukang, belanja transfer, biaya bank).
  * Perhitungan saldo berjalan kumulatif (*running balance*).
  * Berita acara penutupan kas bulanan dan rekonsiliasi kas:
    $$\text{Total Saldo Kas} = \text{Kas Tunai di Brankas} + \text{Saldo Rekening Bank}$$
  * Lembar tanda tangan resmi Kepala Sekolah dan Bendahara Pengeluaran.
* **Buku Pembantu Kas (BPK / Kas Tunai):**
  * Khusus mencatat mutasi uang fisik tunai di brankas bendahara (penarikan tunai dari bank, belanja tunai lapangan, dan penyetoran kas ke bank).
* **Buku Pembantu Bank (BB / Buku Bank):**
  * Khusus mencatat mutasi rekening koran sekolah pada Bank Penampung (**Bank Jatim Cabang Situbondo**).
  * Mencakup pencairan termin, transfer langsung ke rekanan/toko, bunga giro bank, dan biaya administrasi rekening.
* **Buku Pembantu Pajak (BP / Buku Pajak):**
  * Pencatatan pemotongan pajak belanja: **PPN 11%**, **PPh 21** (Upah/Tenaga Kerja), **PPh 22** (Pengadaan Barang), **PPh 23** (Sewa Alat/Jasa), dan **PPh Final 4(2)** (Jasa Konstruksi).
  * Indikator status pajak: *Dipungut (Belum Setor)* vs *Sudah Disetor ke Kas Negara*.
  * Fitur aksi cepat **"Setor Pajak"** dengan modal interaktif untuk menginput Nomor NTPN (*Nomor Transaksi Penerimaan Negara*) dan tanggal setor.
  * Peringatan visual saldo pajak terutang secara *real-time*.

### 2. Laporan Realisasi Anggaran (RAB vs Realisasi)
* Komparasi Pagu Rencana Anggaran Biaya (RAB) per rincian pos (Material/Bahan, Upah Tenaga Kerja, Peralatan/Sewa, Honor & Operasional) terhadap realisasi riil belanja.
* Perhitungan otomatis sisa pagu anggaran dan persentase (%) serapan dana per item dan total proyek.

### 3. Kuitansi Pengeluaran Siap Cetak
* Lembar kuitansi resmi pemerintah siap cetak (A4 Portrait).
* **Terbilang Rupiah Otomatis** dalam Bahasa Indonesia (contoh: *"Delapan Belas Juta Lima Ratus Ribu Rupiah"*).
* Rincian potongan pajak yang dipungut dan jumlah pembayaran bersih (*net amount*).
* Kolom tanda tangan 3 pihak: **Yang Menerima Uang (Toko/Rekanan)**, **Bendahara Pengeluaran**, dan **Kepala Sekolah (Setuju Dibayar)**.

### 4. Role & Permission Management Granular
Sistem hak akses bertingkat dengan kontrol matriks permission fleksibel yang dikendalikan oleh role **Root**:

| Role | Deskripsi & Hak Akses |
|---|---|
| 👑 **Root** | **Super Administrator**. Memiliki akses penuh ke seluruh fitur dan sistem. Dapat mengelola data pengguna (*User Management*), menambah/mengubah role, dan **mengatur matriks izin permission per modul** (`sekolah`, `proyek`, `rab`, `transaksi`, `bku`, `bpk`, `bb`, `bp`, `laporan`, `kuitansi`, `users`, `roles`) untuk setiap aksi (`view`, `create`, `update`, `delete`, `export`, `import`, `print`). Root juga dapat menambahkan permission baru secara dinamis. |
| ⚡ **Admin** | **Operator / Bendahara Revitalisasi**. Mengelola data sekolah, paket proyek revitalisasi, rincian RAB, pencatatan transaksi keuangan, kuitansi, dan mencetak 4 buku laporan. |
| 👁️ **User** | **Viewer / Pengawas / Kepala Sekolah**. Hak akses *read-only* untuk memantau dashboard eksekutif, melihat hasil output laporan keuangan BKU/BPK/BB/BP, mengekspor Excel, dan mencetak laporan. |

---

## 🛠️ Teknologi yang Digunakan

* **Backend Framework:** [Laravel 10](https://laravel.com/) (PHP 8.1+)
* **Database:** [MySQL 8.0+](https://www.mysql.com/)
* **CSS Framework:** [Tailwind CSS 3.4](https://tailwindcss.com/)
* **Interaktivitas & UI:** [Alpine.js](https://alpinejs.dev/)
* **Visualisasi Data:** [Chart.js](https://www.chartjs.org/) (Grafik Serapan Keuangan vs Progres Fisik Lapangan & Komposisi Belanja RAB)
* **Ikonografi:** [Lucide Icons](https://lucide.dev/)
* **Asset Bundler:** [Vite 4.5](https://vitejs.dev/)
* **Export Engine:** Native CSV/Excel UTF-8 BOM Streamer & Custom Print CSS

---

## 💻 Prasyarat Sistem

Sebelum memasang aplikasi, pastikan komputer Anda telah terinstal:
* **PHP** versi 8.1 atau lebih baru (dengan ekstensi: `pdo_mysql`, `mbstring`, `bcmath`, `curl`, `gd`, `xml`, `zip`)
* **Composer** versi 2.x
* **Node.js** versi 18.x atau lebih baru & **NPM**
* **MySQL Database Server** 8.0+ atau MariaDB 10.4+
* Lingkungan lokal yang disarankan: **Laragon** (Windows) atau Docker/Valet/XAMPP.

---

## 🚀 Panduan Instalasi

### 1. Clone Repositori Git
```bash
git clone https://github.com/username/sirevita.git sirevita
cd sirevita
```

### 2. Pasang Dependensi Backend (Composer)
```bash
composer install
```

### 3. Pasang Dependensi Frontend (NPM)
```bash
npm install
```

### 4. Konfigurasi Lingkungan (.env)
Salin berkas `.env.example` ke `.env`:
```bash
cp .env.example .env
```
Buka berkas `.env` dan sesuaikan pengaturan database Anda:
```env
APP_NAME="Si Revita Situbondo"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sirevita_db
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Generate Application Key
```bash
php artisan key:generate
```

### 6. Migrasi Database & Seeding Data Awal
Jalankan migrasi untuk membuat tabel dan mengisi data awal sekolah di Situbondo, akun demo, proyek revitalisasi, RAB, serta transaksi sampel:
```bash
php artisan migrate:fresh --seed
```

### 7. Buat Symbolic Link Storage
```bash
php artisan storage:link
```

### 8. Kompilasi Aset Frontend (Production Build)
```bash
npm run build
```
*(Atau gunakan `npm run dev` untuk pengembangan aktif)*.

### 9. Jalankan Server Aplikasi
```bash
php artisan serve
```
Akses aplikasi melalui browser di alamat:
👉 **`http://127.0.0.1:8000`** *(atau `http://sirevita.test` jika menggunakan Laragon)*.

---

## 🔑 Akun Pengguna Bawaan (Demo)

Sistem sudah dilengkapi 3 akun demo bawaan untuk kemudahan demonstrasi dan pengujian hak akses:

| Peran (Role) | Username / Email | Password | Cakupan Wewenang |
|---|---|---|---|
| 👑 **Root** | `root` / `root@sirevita.situbondokab.go.id` | `password123` | Akses semua fitur, kelola user & matriks permission |
| ⚡ **Admin** | `admin` / `admin@sirevita.situbondokab.go.id` | `password123` | Bendahara Revitalisasi SMPN 1 Situbondo (Input Transaksi & Laporan) |
| 👁️ **User** | `user` / `pengawas@sirevita.situbondokab.go.id` | `password123` | Pengawas Dinas / Viewer Laporan |

> ⚡ **Fitur 1-Click Demo Login:** Di halaman login dan bilah navigasi atas (*header*) aplikasi telah disediakan tombol sakelar cepat (*Quick Role Switcher*) untuk berpindah antara akun **Root**, **Admin**, dan **User** hanya dengan 1 klik tanpa perlu mengetik ulang!

---

## 📁 Struktur Direktori Proyek

```
sirevita/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php              # Autentikasi & Quick Demo Switcher
│   │   │   ├── DashboardController.php         # Monitoring Eksekutif & Chart.js
│   │   │   ├── ReportController.php            # Output BKU, BPK, BB, BP, Realisasi & Kuitansi
│   │   │   ├── TransactionController.php       # Pencatatan Transaksi & Kalkulator Pajak
│   │   │   ├── SchoolController.php            # Master Data Sekolah di Situbondo
│   │   │   ├── ProjectController.php           # Paket Proyek Revitalisasi
│   │   │   ├── BudgetItemController.php        # Pos Belanja RAB
│   │   │   ├── UserController.php              # Kelola User (Khusus Root)
│   │   │   └── RolePermissionController.php    # Matriks Hak Akses Granular (Khusus Root)
│   │   └── Middleware/
│   │       ├── CheckRole.php                   # Proteksi Rute Berbasis Role
│   │       └── CheckPermission.php             # Proteksi Rute Berbasis Permission
│   └── Models/
│       ├── Role.php, Permission.php
│       ├── School.php, RevitalizationProject.php
│       ├── BudgetItem.php, Transaction.php, User.php
├── database/
│   ├── migrations/                             # Skema Tabel & Foreign Keys
│   └── seeders/DatabaseSeeder.php              # Seeder Realistis Situbondo
├── resources/
│   ├── css/app.css                             # Tailwind Directives & Print Styles
│   ├── js/app.js                               # Alpine.js & Chart.js Registration
│   └── views/
│       ├── layouts/app.blade.php               # Master Layout Modern + Role Switcher
│       ├── auth/login.blade.php                # Halaman Login Elegan + Demo Cards
│       ├── dashboard/index.blade.php           # Visualisasi Dashboard Eksekutif
│       ├── reports/
│       │   ├── bku.blade.php                   # Output Buku Kas Umum (BKU)
│       │   ├── bpk.blade.php                   # Output Buku Pembantu Kas (BPK)
│       │   ├── bb.blade.php                    # Output Buku Pembantu Bank (BB)
│       │   ├── bp.blade.php                    # Output Buku Pembantu Pajak (BP)
│       │   ├── realisasi.blade.php             # Laporan Realisasi Fisik & Anggaran
│       │   ├── kuitansi_index.blade.php        # Daftar Kuitansi Pengeluaran
│       │   └── print_kuitansi.blade.php        # Format Cetak Kuitansi Standar Kedinasan
│       ├── transactions/                       # Formulir & Tabel Transaksi Kas/Bank
│       ├── schools/                            # Profil Satuan Pendidikan Situbondo
│       ├── projects/                           # Manajemen Paket Revitalisasi
│       ├── rab/                                # Pos Rencana Anggaran Biaya
│       ├── users/                              # Manajemen Pengguna Sistem
│       └── roles/                              # Matriks Permission Granular Root
├── routes/web.php                              # Definisi 57 Rute Aplikasi
└── tests/Feature/SiRevitaTest.php              # Automated Feature Test Suite
```

---

## 🧪 Pengujian Otomatis (Testing)

Aplikasi telah dilengkapi rangkaian *Automated Feature Tests* untuk memverifikasi keamanan autentikasi, proteksi hak akses role, serta keakuratan penerbitan laporan:

```bash
php artisan test --filter=SiRevitaTest
```

**Hasil Pengujian:**
```text
   PASS  Tests\Feature\SiRevitaTest
  ✓ guest is redirected to login
  ✓ login page renders successfully
  ✓ root can access all modules
  ✓ admin can access revitalization reports but not user management
  ✓ user can view reports only
  ✓ csv export works

  Tests:    6 passed (44 assertions)
  Duration: 2.97s
```

---

## 🤝 Panduan Kontribusi

1. Fork repositori ini
2. Buat branch fitur baru (`git checkout -b feature/FiturKeren`)
3. Commit perubahan Anda (`git commit -m 'Menambahkan fitur keren'`)
4. Push ke branch (`git push origin feature/FiturKeren`)
5. Buat **Pull Request** baru

---

## 📄 Lisensi

Aplikasi **Si Revita Situbondo** dirilis di bawah lisensi terbuka [MIT License](LICENSE).

<div align="center">
  <sub>Dikembangkan dengan ❤️ untuk kemajuan pendidikan di Kabupaten Situbondo.</sub>
</div>
