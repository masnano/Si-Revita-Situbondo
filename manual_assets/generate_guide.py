# -*- coding: utf-8 -*-
"""
Script to generate the comprehensive HTML user guide for Si Revita Situbondo
and compile it to PDF using Microsoft Edge headless print-to-pdf.
"""

import os
import subprocess

html_content = """<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Panduan Penggunaan - Si Revita Situbondo</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap');

        @page {
            size: A4 portrait;
            margin: 20mm 15mm 20mm 15mm;
            @bottom-right {
                content: "Halaman " counter(page);
                font-family: 'Plus Jakarta Sans', sans-serif;
                font-size: 8pt;
                color: #64748b;
            }
            @bottom-left {
                content: "Si Revita Situbondo — Buku Panduan Pengguna";
                font-family: 'Plus Jakarta Sans', sans-serif;
                font-size: 8pt;
                color: #94a3b8;
            }
        }

        @page :first {
            margin: 0;
            @bottom-right { content: normal; }
            @bottom-left { content: normal; }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1e293b;
            background: #ffffff;
            font-size: 10pt;
            line-height: 1.6;
        }

        .page-break {
            page-break-before: always;
        }

        .avoid-break {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* COVER STYLES */
        .cover-page {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(145deg, #022c22 0%, #064e3b 40%, #0f172a 100%);
            color: #ffffff;
            padding: 50mm 20mm 30mm 25mm;
            position: relative;
            overflow: hidden;
        }

        .cover-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
            font-size: 10pt;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            width: fit-content;
        }

        .cover-title-group {
            margin-top: 25px;
        }

        .cover-title {
            font-size: 38pt;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.5px;
            color: #ffffff;
        }

        .cover-title span {
            color: #34d399;
        }

        .cover-subtitle {
            font-size: 14pt;
            font-weight: 500;
            color: #94a3b8;
            margin-top: 15px;
            line-height: 1.5;
            max-width: 650px;
        }

        .cover-divider {
            width: 120px;
            height: 5px;
            background: #10b981;
            margin: 25px 0;
            border-radius: 3px;
        }

        .cover-meta {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .cover-meta-left h3 {
            font-size: 13pt;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .cover-meta-left p {
            font-size: 9.5pt;
            color: #cbd5e1;
            margin-top: 4px;
        }

        .cover-meta-right {
            text-align: right;
            font-size: 9pt;
            color: #94a3b8;
        }

        .cover-meta-right strong {
            color: #34d399;
            display: block;
            font-size: 11pt;
            margin-bottom: 2px;
        }

        /* CONTENT STYLES */
        .content-container {
            padding: 0;
        }

        h1.chapter-title {
            font-size: 20pt;
            font-weight: 900;
            color: #065f46;
            border-bottom: 3px solid #10b981;
            padding-bottom: 8px;
            margin-bottom: 20px;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        h2.section-title {
            font-size: 13pt;
            font-weight: 800;
            color: #0f172a;
            margin-top: 22px;
            margin-bottom: 10px;
            border-left: 4px solid #10b981;
            padding-left: 10px;
        }

        h3.sub-section-title {
            font-size: 11pt;
            font-weight: 700;
            color: #334155;
            margin-top: 15px;
            margin-bottom: 8px;
        }

        p {
            margin-bottom: 10px;
            color: #334155;
            text-align: justify;
        }

        /* CALLOUT BOXES */
        .callout {
            border-radius: 10px;
            padding: 14px 18px;
            margin: 16px 0;
            font-size: 9.5pt;
            line-height: 1.5;
        }

        .callout-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .callout-info strong {
            color: #14532d;
            display: block;
            margin-bottom: 4px;
            font-size: 10pt;
        }

        .callout-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .callout-warning strong {
            color: #78350f;
            display: block;
            margin-bottom: 4px;
            font-size: 10pt;
        }

        /* TABLES */
        table.doc-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0 20px 0;
            font-size: 8.5pt;
        }

        table.doc-table th {
            background: #0f172a;
            color: #ffffff;
            font-weight: 700;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #1e293b;
        }

        table.doc-table td {
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: top;
        }

        table.doc-table tr:nth-child(even) {
            background: #f8fafc;
        }

        /* SCREENSHOT FIGURE */
        .figure-box {
            margin: 12px 0 16px 0;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .figure-box img {
            width: 100%;
            max-height: 440px;
            object-fit: cover;
            object-position: top;
            display: block;
            border-bottom: 1px solid #e2e8f0;
        }

        .figure-caption {
            padding: 8px 14px;
            background: #f8fafc;
            font-size: 8.5pt;
            font-weight: 600;
            color: #475569;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .figure-caption span.fig-num {
            color: #059669;
            font-weight: 700;
        }

        /* STEP LIST */
        ol.step-list {
            margin-left: 20px;
            margin-bottom: 16px;
        }

        ol.step-list li {
            margin-bottom: 8px;
            color: #334155;
            padding-left: 6px;
        }

        ol.step-list li strong {
            color: #0f172a;
        }

        ul.bullet-list {
            margin-left: 20px;
            margin-bottom: 14px;
        }

        ul.bullet-list li {
            margin-bottom: 6px;
            color: #334155;
        }

        .badge-role {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-root { background: #f3e8ff; color: #7e22ce; border: 1px solid #d8b4fe; }
        .badge-admin { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-user { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

        .key-metric {
            background: #f1f5f9;
            border-left: 3px solid #0284c7;
            padding: 8px 12px;
            margin: 8px 0;
            font-size: 9pt;
            border-radius: 0 6px 6px 0;
        }

        .toc-list {
            list-style: none;
            margin: 20px 0;
        }

        .toc-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dotted #cbd5e1;
            font-size: 9.5pt;
        }

        .toc-item a {
            text-decoration: none;
            color: #1e293b;
            font-weight: 600;
        }

        .toc-item .dots {
            flex-grow: 1;
            border-bottom: 1px dotted #94a3b8;
            margin: 0 8px 5px 8px;
        }

        .toc-item .page-num {
            font-weight: 700;
            color: #059669;
        }
    </style>
</head>
<body>

    <!-- HALAMAN SAMPUL (COVER) -->
    <div class="cover-page">
        <div>
            <div class="cover-badge">Buku Panduan Penggunaan Resmi (User Manual)</div>
            <div class="cover-title-group">
                <div class="cover-title">SI REVITA<br><span>SITUBONDO</span></div>
                <div class="cover-subtitle">
                    Sistem Informasi Terpadu Pengelolaan Rencana Anggaran Biaya (RAB), Pembukuan Arus Kas, dan Penerbitan Output Baku Pertanggungjawaban Revitalisasi Sekolah
                </div>
            </div>
            <div class="cover-divider"></div>
        </div>

        <div class="cover-meta">
            <div class="cover-meta-left">
                <h3>PEMERINTAH KABUPATEN SITUBONDO</h3>
                <p>DINAS PENDIDIKAN DAN KEBUDAYAAN</p>
                <p>Bidang Sarana dan Prasarana Pendidikan</p>
            </div>
            <div class="cover-meta-right">
                <strong>Tahun Anggaran 2026</strong>
                Edisi 1.0 (Panduan Komprehensif)
            </div>
        </div>
    </div>

    <!-- KATA PENGANTAR & DAFTAR ISI -->
    <div class="page-break content-container">
        <h1 class="chapter-title">Kata Pengantar & Ringkasan Eksekutif</h1>
        
        <p>Puji syukur kita panjatkan ke hadirat Tuhan Yang Maha Esa atas terbitnya <strong>Buku Panduan Penggunaan Aplikasi Si Revita Situbondo</strong>. Buku panduan ini disusun secara komprehensif sebagai acuan teknis dan operasional bagi seluruh satuan pendidikan penerima dana bantuan revitalisasi sekolah (SD, SMP, SMK), bendahara pelaksana swakelola, tim pengawas, dan jajaran dinas di lingkungan Pemerintah Kabupaten Situbondo.</p>

        <p>Revitalisasi sarana dan prasarana sekolah merupakan program strategis Pemerintah Kabupaten Situbondo dalam meningkatkan mutu layanan pendidikan. Akuntabilitas, transparansi, dan ketepatan waktu pelaporan keuangan menjadi pilar utama keberhasilan program. <strong>Si Revita (Sistem Revitalisasi Situbondo)</strong> hadir untuk mengotomatisasi seluruh siklus perbendaharaan mulai dari perumusan Rencana Anggaran Biaya (RAB), pencatatan transaksi kas & bank harian, kalkulasi pajak otomatis (PPN & PPh), hingga penerbitan 4 buku perbendaharaan baku (BKU, BPK, BB, BP) dan kuitansi resmi bertanda tangan sah.</p>

        <div class="callout callout-info">
            <strong>Landasan Regulasi & Kepatuhan Perbendaharaan</strong>
            Sistem informasi ini didesain dan diselaraskan dengan ketentuan perundang-undangan perbendaharaan negara, antara lain:
            <ul style="margin-left: 18px; margin-top: 6px;">
                <li>Peraturan Menteri Pendidikan, Kebudayaan, Riset, dan Teknologi tentang Petunjuk Teknis DAK Fisik Bidang Pendidikan.</li>
                <li>Peraturan Menteri Dalam Negeri Nomor 77 Tahun 2020 tentang Pedoman Teknis Pengelolaan Keuangan Daerah.</li>
                <li>Peraturan Menteri Keuangan Republik Indonesia terkait Tata Cara Pemotongan dan Penyetoran Pajak bagi Instansi Pemerintah (PPN 11%, PPh 21, PPh 22, dan PPh 23).</li>
            </ul>
        </div>

        <h2 class="section-title">Daftar Isi Buku Panduan</h2>
        <ul class="toc-list">
            <li class="toc-item"><span><strong>BAB 1:</strong> Gambaran Umum Aplikasi & Hak Akses Pengguna</span><span class="dots"></span><span class="page-num">Hal. 3</span></li>
            <li class="toc-item"><span><strong>BAB 2:</strong> Akses Masuk, Autentikasi, & Fitur Ganti Role</span><span class="dots"></span><span class="page-num">Hal. 4</span></li>
            <li class="toc-item"><span><strong>BAB 3:</strong> Panduan Pengelolaan Data Master Sekolah & Proyek</span><span class="dots"></span><span class="page-num">Hal. 6</span></li>
            <li class="toc-item"><span><strong>BAB 4:</strong> Penyusunan Rencana Anggaran Biaya (RAB)</span><span class="dots"></span><span class="page-num">Hal. 8</span></li>
            <li class="toc-item"><span><strong>BAB 5:</strong> Pencatatan Transaksi & Kalkulator Pajak Terpadu</span><span class="dots"></span><span class="page-num">Hal. 10</span></li>
            <li class="toc-item"><span><strong>BAB 6:</strong> Penatausahaan Pajak & Input Bukti NTPN</span><span class="dots"></span><span class="page-num">Hal. 12</span></li>
            <li class="toc-item"><span><strong>BAB 7:</strong> Penerbitan Output 4 Buku Laporan Pertanggungjawaban (SPJ)</span><span class="dots"></span><span class="page-num">Hal. 13</span></li>
            <li class="toc-item"><span><strong>BAB 8:</strong> Cetak Kuitansi Resmi & Ekspor Laporan</span><span class="dots"></span><span class="page-num">Hal. 18</span></li>
            <li class="toc-item"><span><strong>BAB 9:</strong> Manajemen Pengguna & Hak Akses Granular (Khusus Root)</span><span class="dots"></span><span class="page-num">Hal. 20</span></li>
            <li class="toc-item"><span><strong>BAB 10:</strong> Tanya Jawab & Troubleshooting (Solusi Kendala)</span><span class="dots"></span><span class="page-num">Hal. 21</span></li>
        </ul>
    </div>

    <!-- BAB 1 -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 1: Gambaran Umum & Hierarki Akses</h1>

        <h2 class="section-title">1.1 Latar Belakang & Tujuan Si Revita</h2>
        <p>Aplikasi <strong>Si Revita Situbondo</strong> dirancang khusus untuk memecahkan kendala klasik pelaporan swakelola fisik di tingkat sekolah, seperti kesalahan hitung manual, keterlambatan penyusunan Buku Kas Umum, ketidaksesuaian pungutan pajak dengan setoran ke kas negara, serta kesulitan rekapitulasi realisasi anggaran terhadap pagu kontrak.</p>

        <h2 class="section-title">1.2 Matriks Peran Pengguna (User Roles)</h2>
        <p>Aplikasi menerapkan prinsip <em>Role-Based Access Control (RBAC)</em> dengan matriks izin berjenjang untuk menjaga integritas data:</p>

        <table class="doc-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Peran (Role)</th>
                    <th style="width: 25%;">Sasaran Pemangku Kepentingan</th>
                    <th style="width: 35%;">Cakupan Wewenang Utama</th>
                    <th style="width: 20%;">Hak Spesial</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge-role badge-root">Root (Superadmin)</span></td>
                    <td>Administrator IT Dinas Pendidikan & Kebudayaan Kab. Situbondo</td>
                    <td>Akses menyeluruh ke semua data sekolah, manajemen user, pengaturan hak akses matrix granular per-fitur.</td>
                    <td>Kelola akun, reset izin, akses matriks permission</td>
                </tr>
                <tr>
                    <td><span class="badge-role badge-admin">Admin Revitalisasi</span></td>
                    <td>Bendahara Sekolah & Tim Teknis Swakelola Satuan Pendidikan</td>
                    <td>Input data RAB proyek, input transaksi harian (debit/kredit), lampirkan kuitansi, input NTPN penyetoran pajak, cetak laporan sekolah terkait.</td>
                    <td>CRUD data transaksi & RAB sekolahnya</td>
                </tr>
                <tr>
                    <td><span class="badge-role badge-user">User (Viewer)</span></td>
                    <td>Kepala Sekolah, Tim Pengawas/Auditor, Pimpinan Dinas</td>
                    <td>Melihat rekap dashboard eksekutif, memonitor serapan pagu anggaran vs realisasi fisik, mencetak 4 buku laporan perbendaharaan dan kuitansi.</td>
                    <td>Read-only & Cetak Laporan Sah</td>
                </tr>
            </tbody>
        </table>

        <h2 class="section-title">1.3 Alur Kerja Utama Aplikasi (Workflow Ringkas)</h2>
        <p>Untuk mencapai output laporan yang sah, pengguna melewati alur terpadu sebagai berikut:</p>
        <ol class="step-list">
            <li><strong>Inisiasi Master Data:</strong> Input profil sekolah dan buat paket proyek revitalisasi dengan nilai pagu kontrak.</li>
            <li><strong>Perumusan RAB:</strong> Uraikan pagu kontrak ke dalam pos-pos belanja (Material, Upah, Sewa, Operasional).</li>
            <li><strong>Pencatatan Penerimaan:</strong> Catat pencairan termin dana dari Kasda ke rekening bank sekolah.</li>
            <li><strong>Tarik Tunai Kas:</strong> Bukukan penarikan dari rekening bank ke kas tunai bendahara (jika ada belanja tunai).</li>
            <li><strong>Pencatatan Belanja & Pajak:</strong> Catat setiap transaksi belanja fisik. Sistem otomatis menghitung potongan pajak PPN/PPh.</li>
            <li><strong>Setor Pajak & NTPN:</strong> Setelah pajak disetorkan ke bank persepsi, input nomor NTPN agar saldo kewajiban pajak menjadi nihil.</li>
            <li><strong>Penerbitan Output:</strong> Otomatis menghasilkan Buku Kas Umum (BKU), Buku Kas (BPK), Buku Bank (BB), Buku Pajak (BP), LRA, dan Kuitansi Resmi.</li>
        </ol>
    </div>

    <!-- BAB 2 -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 2: Akses Masuk & Navigasi Antarmuka</h1>

        <h2 class="section-title">2.1 Halaman Login & Akses Cepat Demo</h2>
        <p>Aplikasi dapat diakses melalui browser modern (Google Chrome, Microsoft Edge, Firefox, Safari) pada alamat web yang telah disediakan. Untuk mempermudah pengetesan dan supervisi antar peran, Si Revita dilengkapi fitur <strong>1-Click Demo Login</strong> langsung dari halaman muka.</p>

        <div class="figure-box">
            <img src="01_login.png" alt="Halaman Login Si Revita Situbondo">
            <div class="figure-caption">
                <span class="fig-num">Gambar 2.1:</span> Halaman Login Si Revita Situbondo dengan Fitur Quick Login untuk Akun Root, Admin, dan User
            </div>
        </div>

        <ol class="step-list">
            <li><strong>Login Standar:</strong> Masukkan <em>Username</em> (misal: <code>root</code> / <code>admin</code> / <code>user</code>) atau alamat email terdaftar, serta kata sandi, lalu klik tombol <strong>"Masuk Aplikasi"</strong>.</li>
            <li><strong>1-Click Quick Demo Login:</strong> Klik salah satu kartu pada kotak demo di bagian bawah kartu login:
                <ul class="bullet-list" style="margin-top: 5px;">
                    <li><strong>👑 Root:</strong> Masuk langsung dengan otoritas Super Administrator.</li>
                    <li><strong>⚡ Admin:</strong> Masuk langsung sebagai Bendahara SMPN 1 Situbondo.</li>
                    <li><strong>👁️ User:</strong> Masuk langsung sebagai Tim Pengawas / Pengguna Laporan.</li>
                </ul>
            </li>
        </ol>

        <h2 class="section-title">2.2 Dashboard Eksekutif & Fitur Switcher "Ubah Role"</h2>
        <p>Setelah autentikasi berhasil, pengguna disambut oleh Dashboard Eksekutif yang menyajikan ringkasan metrik kesehatan anggaran revitalisasi:</p>

        <div class="figure-box">
            <img src="02_dashboard.png" alt="Dashboard Eksekutif Si Revita Situbondo">
            <div class="figure-caption">
                <span class="fig-num">Gambar 2.2:</span> Dashboard Eksekutif dengan Kartu Ringkasan Pagu, Realisasi, Posisi Kas/Bank, dan Role Switcher di Top Bar
            </div>
        </div>

        <p><strong>Komponen Utama Dashboard:</strong></p>
        <ul class="bullet-list">
            <li><strong>Kartu Ringkasan Finansial:</strong> Menampilkan <em>Total Pagu Revitalisasi</em>, <em>Realisasi Belanja</em>, <em>Sisa Pagu</em>, serta rincian <em>Posisi Kas Tunai di Brankas vs Saldo di Bank Jatim</em>.</li>
            <li><strong>Shortcut 4 Buku Utama:</strong> Tombol akses kilat menuju BKU, BPK, BB, dan BP.</li>
            <li><strong>Dropdown "Ubah Role" (Top Bar):</strong> Terletak di header kanan atas. Digunakan untuk berpindah konteks akun seketika tanpa harus logout terlebih dahulu.</li>
        </ul>
    </div>

    <!-- BAB 3 -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 3: Pengelolaan Data Master Sekolah & Proyek</h1>

        <h2 class="section-title">3.1 Master Data Sekolah</h2>
        <p>Data profil sekolah menjadi dasar kop resmi, penomoran rekening bank penampung dana, serta identitas kepala sekolah dan bendahara pada lembar pengesahan kuitansi dan SPJ.</p>

        <div class="figure-box">
            <img src="03_sekolah.png" alt="Manajemen Data Sekolah">
            <div class="figure-caption">
                <span class="fig-num">Gambar 3.1:</span> Halaman Master Data Sekolah Menampilkan NPSN, Kontak, Rekening Bank Jatim, serta KS & Bendahara
            </div>
        </div>

        <ol class="step-list">
            <li>Buka menu <strong>"Data Sekolah"</strong> di sidebar kiri.</li>
            <li>Klik tombol <strong>"Tambah Sekolah"</strong> untuk mendaftarkan satuan pendidikan baru.</li>
            <li>Pastikan mengisi <strong>NPSN</strong>, <strong>Nama Kepala Sekolah & NIP</strong>, <strong>Nama Bendahara & NIP</strong>, serta <strong>Nomor Rekening Bank Jatim</strong> yang sah. Data ini akan otomatis tertulis pada kop laporan dan tanda tangan kuitansi.</li>
        </ol>

        <h2 class="section-title">3.2 Master Paket Proyek Revitalisasi</h2>
        <p>Setiap bantuan revitalisasi dikelola dalam bentuk paket proyek dengan kepemilikan nilai pagu kontrak, nomor Surat Perintah Kerja (SPK/SPMK), serta jadwal pelaksanaan.</p>

        <div class="figure-box">
            <img src="04_proyek.png" alt="Manajemen Proyek Revitalisasi">
            <div class="figure-caption">
                <span class="fig-num">Gambar 3.2:</span> Halaman Master Proyek Revitalisasi Menampilkan Pagu Kontrak, Realisasi Belanja, dan Progress Fisik
            </div>
        </div>

        <div class="callout callout-warning">
            <strong>Penting: Pagu Kontrak sebagai Batas Tertinggi Anggaran</strong>
            Nilai <em>Pagu Kontrak Proyek</em> adalah batas pagu maksimal yang tidak boleh dilewati oleh akumulasi alokasi RAB maupun realisasi transaksi belanja.
        </div>
    </div>

    <!-- BAB 4 -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 4: Penyusunan Rencana Anggaran Biaya (RAB)</h1>

        <h2 class="section-title">4.1 Struktur Belanja Baku RAB</h2>
        <p>Sesuai juknis revitalisasi sarana fisik pendidikan, setiap paket pekerjaan swakelola harus dipecah ke dalam 4 kategori belanja baku:</p>
        <ul class="bullet-list">
            <li><strong>1. Material/Bahan:</strong> Belanja semen, pasir, bata, keramik, atap baja ringan, cat tembok, pintu/jendela, instalasi listrik, sanitasi.</li>
            <li><strong>2. Upah Tenaga Kerja:</strong> Pembayaran honor tukang batu, tukang kayu, mandor, dan pekerja harian lepas konstruksi.</li>
            <li><strong>3. Peralatan/Sewa:</strong> Sewa alat pengaduk beton (molen mixer), perancah (scaffolding), genset, dan alat angkut material.</li>
            <li><strong>4. Honor & Operasional:</strong> Honor tim pelaksana swakelola sekolah, konsultan perencana teknis, ATK pelaporan, dan dokumentasi foto progress.</li>
        </ul>

        <div class="figure-box">
            <img src="05_rab.png" alt="Penyusunan Rencana Anggaran Biaya">
            <div class="figure-caption">
                <span class="fig-num">Gambar 4.1:</span> Antarmuka Rencana Anggaran Biaya (RAB) Lengkap dengan Status Alokasi dan Kontrol Selisih Pagu
            </div>
        </div>

        <h2 class="section-title">4.2 Langkah Pengisian Item Belanja RAB</h2>
        <ol class="step-list">
            <li>Pilih sekolah dan proyek revitalisasi pada dropdown pemilih proyek di pojok kanan atas.</li>
            <li>Perhatikan kotak ringkasan <strong>Pagu Kontrak Proyek</strong>, <strong>Total Alokasi Rincian RAB</strong>, dan <strong>Selisih Pagu vs RAB</strong>.</li>
            <li>Klik tombol hijau <strong>"+ Tambah Item RAB"</strong> untuk membuka modal formulir.</li>
            <li>Isi <strong>Kode Rekening</strong> (misal: <code>5.2.2.01.01</code>), pilih <strong>Kategori Belanja</strong>, ketik <strong>Nama Barang/Pekerjaan</strong>, masukkan <strong>Volume</strong>, <strong>Satuan</strong>, dan <strong>Harga Satuan (Rp)</strong>.</li>
            <li>Sistem akan mengalikan volume & harga satuan secara otomatis. Klik <strong>"Simpan Item"</strong>.</li>
        </ol>
    </div>

    <!-- BAB 5 -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 5: Pencatatan Transaksi & Kalkulator Pajak Terpadu</h1>

        <h2 class="section-title">5.1 Daftar Transaksi Kas & Bank</h2>
        <p>Menu <strong>"Transaksi Kas & Bank"</strong> mencatat seluruh mutasi dana baik debit (penerimaan kas) maupun kredit (pengeluaran belanja). Setiap transaksi memiliki nomor bukti kas berurutan (misal: <code>BKU-2026/001</code>).</p>

        <div class="figure-box">
            <img src="06_transaksi.png" alt="Daftar Transaksi Keuangan">
            <div class="figure-caption">
                <span class="fig-num">Gambar 5.1:</span> Daftar Seluruh Transaksi Keuangan Menampilkan Tanggal, No. Bukti, Metode Pembayaran, Potongan Pajak, dan Nominal Bersih
            </div>
        </div>

        <h2 class="section-title">5.2 Formulir Input Transaksi & Kalkulator Pajak</h2>
        <p>Saat bendahara melakukan pembelanjaan, sistem menyediakan kalkulator pajak terpadu yang menghitung pemotongan pajak secara akurat sesuai regulasi perpajakan yang berlaku.</p>

        <div class="figure-box">
            <img src="07_tambah_transaksi.png" alt="Formulir Tambah Transaksi dan Kalkulator Pajak">
            <div class="figure-caption">
                <span class="fig-num">Gambar 5.2:</span> Formulir Tambah Transaksi Baru dengan Pemilihan Pos RAB dan Kalkulator Pemotongan Pajak Terpadu
            </div>
        </div>

        <div class="callout callout-info">
            <strong>Aturan Pemotongan Pajak Instansi Pemerintah:</strong>
            <ul style="margin-left: 18px; margin-top: 5px;">
                <li><strong>PPN (11%):</strong> Dikenakan pada belanja material/barang kena pajak dengan nilai bruto di atas Rp 2.000.000. Rumus dasar DPP: <code>(100 / 111) * Nilai Bruto</code>, PPN = <code>11% * DPP</code>.</li>
                <li><strong>PPh Pasal 22 (1.5%):</strong> Dikenakan pada pembelian barang oleh instansi pemerintah di atas batas minimum. DPP = <code>(100 / 111) * Nilai Bruto</code>, PPh 22 = <code>1.5% * DPP</code>.</li>
                <li><strong>PPh Pasal 21:</strong> Dikenakan pada pembayaran upah harian/mingguan tukang dan honor tenaga kerja swakelola.</li>
                <li><strong>PPh Pasal 23 (2%):</strong> Dikenakan pada sewa peralatan (molen mixer, scaffolding, alat berat).</li>
            </ul>
        </div>
    </div>

    <!-- BAB 6 -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 6: Penatausahaan Pajak & Input NTPN</h1>

        <h2 class="section-title">6.1 Siklus Hidup Pajak: "Dipungut" Menjadi "Disetor"</h2>
        <p>Dalam akuntansi perbendaharaan daerah, saat belanja dilakukan, bendahara bertindak sebagai pemungut pajak. Pajak yang dipungut berstatus <strong>Hutang Pajak (Dipungut)</strong> dan belum menjadi hak kas negara sampai uang tersebut disetorkan ke kas negara melalui bank persepsi.</p>

        <h2 class="section-title">6.2 Alur Input Nomor Transaksi Penerimaan Negara (NTPN)</h2>
        <p>Setelah bendahara menyetorkan potongan pajak via teller Bank Jatim atau e-Billing DJP, bendahara menerima bukti setor dengan nomor <strong>NTPN</strong> (16 karakter alfanumerik).</p>

        <ol class="step-list">
            <li>Buka menu <strong>"Laporan" &rarr; "Buku Pembantu Pajak (BP)"</strong> atau melalui tabel transaksi.</li>
            <li>Cari baris transaksi belanja yang memiliki potongan pajak berstatus kuning <em>"Dipungut / Belum Setor"</em>.</li>
            <li>Klik tombol merah <strong>"Input Setor NTPN"</strong> pada kolom aksi.</li>
            <li>Pada modal pop-up yang muncul:
                <ul class="bullet-list" style="margin-top: 4px;">
                    <li>Ketik nomor <strong>NTPN</strong> yang tertera pada resi bank (misal: <code>NTPN-893184918231</code>).</li>
                    <li>Pilih <strong>Tanggal Penyetoran</strong> ke bank persepsi.</li>
                </ul>
            </li>
            <li>Klik <strong>"Simpan Setoran Pajak"</strong>. Status transaksi berubah menjadi hijau <em>"Disetor"</em>, dan saldo hutang pajak pada Buku Pajak otomatis menjadi nihil (Rp 0).</li>
        </ol>

        <div class="callout callout-warning">
            <strong>Peringatan Auditor:</strong>
            Sebelum dokumen Laporan Pertanggungjawaban (SPJ) ditandatangani dan diserahkan ke Dinas Pendidikan, <strong>seluruh saldo pajak pada Buku Pajak wajib bersaldo Rp 0</strong> dengan bukti NTPN yang valid.
        </div>
    </div>

    <!-- BAB 7: 4 BUKU LAPORAN PERBENDAHARAAN -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 7: Penerbitan Output 4 Buku Laporan (SPJ)</h1>

        <h2 class="section-title">7.1 Buku Kas Umum (BKU)</h2>
        <p>Buku Kas Umum adalah buku induk yang mencatat seluruh mutasi penerimaan dan pengeluaran secara kronologis. BKU menyajikan posisi saldo kumulatif kas pada akhir periode.</p>

        <div class="figure-box">
            <img src="08_bku.png" alt="Laporan Buku Kas Umum (BKU)">
            <div class="figure-caption">
                <span class="fig-num">Gambar 7.1:</span> Buku Kas Umum (BKU) Lengkap dengan Kop Resmi, Saldo Awal, Penerimaan, Pengeluaran, dan Saldo Akhir Kumulatif
            </div>
        </div>

        <h2 class="section-title">7.2 Buku Pembantu Kas (BPK)</h2>
        <p>Buku Pembantu Kas hanya mencatat pergerakan uang yang ada di <strong>Kas Tunai (brankas/tangan bendahara)</strong>. Transaksi transfer antar rekening bank tidak masuk ke BPK.</p>

        <div class="figure-box">
            <img src="09_bpk.png" alt="Laporan Buku Pembantu Kas (BPK)">
            <div class="figure-caption">
                <span class="fig-num">Gambar 7.2:</span> Buku Pembantu Kas (BPK) Menampilkan Pengisian Kas Tunai dari Bank dan Pengeluaran Tunai Belanja
            </div>
        </div>
    </div>

    <!-- BAB 7 CONTINUED -->
    <div class="page-break content-container">
        <h2 class="section-title">7.3 Buku Pembantu Bank (BB)</h2>
        <p>Buku Pembantu Bank merekam mutasi saldo rekening giro/tabungan sekolah di Bank Jatim, meliputi penyaluran dana termin DAK, penarikan tunai, belanja transfer, dan penerimaan jasa giro/bunga bank.</p>

        <div class="figure-box">
            <img src="10_bb.png" alt="Laporan Buku Pembantu Bank (BB)">
            <div class="figure-caption">
                <span class="fig-num">Gambar 7.3:</span> Buku Pembantu Bank (BB) Menampilkan Rekonsiliasi Rekening Bank Jatim Cabang Situbondo
            </div>
        </div>

        <h2 class="section-title">7.4 Buku Pembantu Pajak (BP)</h2>
        <p>Buku Pembantu Pajak menyajikan kronologi pemotongan pajak dari belanja pihak ketiga dan bukti penyetorannya ke Kas Negara dengan nomor NTPN.</p>

        <div class="figure-box">
            <img src="11_bp.png" alt="Laporan Buku Pembantu Pajak (BP)">
            <div class="figure-caption">
                <span class="fig-num">Gambar 7.4:</span> Buku Pembantu Pajak (BP) Menampilkan Pemotongan PPN/PPh, Tanggal Setor, dan Nomor NTPN
            </div>
        </div>
    </div>

    <!-- BAB 7 CONTINUED: REALISASI -->
    <div class="page-break content-container">
        <h2 class="section-title">7.5 Laporan Realisasi Anggaran (LRA)</h2>
        <p>Laporan Realisasi Anggaran menyandingkan nilai pagu RAB yang direncanakan dengan realisasi pengeluaran riil per kategori belanja, serta persentase penyerapan dana.</p>

        <div class="figure-box">
            <img src="12_realisasi.png" alt="Laporan Realisasi Anggaran">
            <div class="figure-caption">
                <span class="fig-num">Gambar 7.5:</span> Laporan Realisasi Anggaran (LRA) Menampilkan Perbandingan Pagu vs Realisasi per Kategori Belanja dan Sisa Anggaran
            </div>
        </div>

        <div class="callout callout-info">
            <strong>Formula Kunci Rekonsiliasi Kas Perbendaharaan:</strong>
            <p style="margin-top: 5px; font-family: 'JetBrains Mono', monospace; font-size: 8.5pt;">
                Saldo Kas BKU = Saldo Kas Tunai (BPK) + Saldo Rekening Bank (BB)
            </p>
            <p style="margin-top: 5px;">Jika angka Saldo Akhir BKU tidak sama persis dengan penjumlahan saldo BPK + BB, maka telah terjadi kesalahan pencatatan klasifikasi kas/bank.</p>
        </div>
    </div>

    <!-- BAB 8: KUITANSI & EKSPOR -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 8: Kuitansi Resmi & Ekspor Laporan</h1>

        <h2 class="section-title">8.1 Daftar Kuitansi Pengeluaran</h2>
        <p>Setiap transaksi pengeluaran belanja otomatis menghasilkan kuitansi pembayaran resmi standar Pemerintah Kabupaten Situbondo.</p>

        <div class="figure-box">
            <img src="13_kuitansi_list.png" alt="Daftar Kuitansi Pengeluaran">
            <div class="figure-caption">
                <span class="fig-num">Gambar 8.1:</span> Daftar Seluruh Kuitansi Belanja Lengkap dengan Tombol Cetak Langsung
            </div>
        </div>

        <h2 class="section-title">8.2 Format Cetak Kuitansi Resmi Si Revita</h2>
        <p>Kuitansi yang dihasilkan memenuhi format SPJ baku: dilengkapi kop dinas dan nama sekolah, terbilang huruf otomatis dalam bahasa Indonesia, rincian potongan pajak, dan kotak tanda tangan 3 pihak (Penerima Uang, Bendahara Revitalisasi, dan Mengetahui Kepala Sekolah).</p>

        <div class="figure-box">
            <img src="14_cetak_kuitansi.png" alt="Format Cetak Kuitansi Resmi">
            <div class="figure-caption">
                <span class="fig-num">Gambar 8.2:</span> Format Cetak Kuitansi Resmi Standar Pemkab Situbondo dengan Terbilang Otomatis dan Tanda Tangan 3 Pihak
            </div>
        </div>

        <h2 class="section-title">8.3 Fitur Ekspor ke Excel / CSV</h2>
        <p>Di setiap halaman laporan (BKU, BPK, BB, BP, Realisasi), tersedia tombol hijau <strong>"Excel / CSV"</strong>. Klik tombol ini untuk mengunduh rekapitulasi data dalam format spreadsheet untuk keperluan arsip digital atau lampiran audit BPK.</p>
    </div>

    <!-- BAB 9: ROLE MATRIX -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 9: Manajemen Role & Hak Akses Granular</h1>

        <h2 class="section-title">9.1 Pengaturan Matriks Izin (Role & Permission Matrix)</h2>
        <p>Fitur ini hanya dapat diakses oleh pengguna dengan peran <span class="badge-role badge-root">Root</span>. Administrator dinas dapat mengaktifkan atau menonaktifkan izin spesifik (Create, View, Update, Delete, Export, Print) untuk setiap role.</p>

        <div class="figure-box">
            <img src="15_roles.png" alt="Matriks Role dan Hak Akses">
            <div class="figure-caption">
                <span class="fig-num">Gambar 9.1:</span> Antarmuka Pengaturan Role & Permission Matrix Granular untuk Otoritas Root Superadmin
            </div>
        </div>

        <h2 class="section-title">9.2 Langkah Mengubah Izin Role</h2>
        <ol class="step-list">
            <li>Masuk sebagai akun <strong>Root</strong>, lalu buka menu <strong>"Role & Permission Matrix"</strong> di sidebar kiri.</li>
            <li>Klik tombol <strong>"Detail & Permission"</strong> pada role yang ingin dikonfigurasi (misal: Admin Revitalisasi atau User).</li>
            <li>Centang atau hapus centang pada izin modul yang bersangkutan (misal: mencabut izin delete transaksi untuk mencegah manipulasi data).</li>
            <li>Klik <strong>"Simpan Perubahan Permission"</strong> untuk memperbarui hak akses sistem secara instan.</li>
        </ol>
    </div>

    <!-- BAB 10: FAQ & TROUBLESHOOTING -->
    <div class="page-break content-container">
        <h1 class="chapter-title">BAB 10: Tanya Jawab & Panduan Solusi Kendala</h1>

        <h2 class="section-title">10.1 Tanya Jawab Umum (FAQ)</h2>

        <div class="avoid-break" style="margin-bottom: 14px;">
            <h3 class="sub-section-title">Q1: Mengapa menu dropdown "Ubah Role" sempat tidak mau membuka saat diklik?</h3>
            <p><strong>Jawaban:</strong> Dropdown tersebut digerakkan oleh framework antarmuka <em>Alpine.js</em>. Jika aset JavaScript terblokir atau browser menyimpan cache lama saat Vite server terhenti, Alpine.js tidak terinisiasi. Solusinya: Lakukan <strong>Hard Refresh</strong> di browser dengan menekan <code>Ctrl + F5</code> (Windows) atau <code>Cmd + Shift + R</code> (Mac).</p>
        </div>

        <div class="avoid-break" style="margin-bottom: 14px;">
            <h3 class="sub-section-title">Q2: Bagaimana jika nilai Pagu Kontrak Proyek berubah karena adanya addendum / perubahan kontrak?</h3>
            <p><strong>Jawaban:</strong> Buka menu <em>"Data Proyek"</em>, klik tombol <em>Edit</em> pada paket kegiatan terkait, perbarui nilai pagu kontrak baru dan nomor addendum SPK, lalu simpan. Sistem akan langsung mengkalkulasi ulang selisih anggaran pada halaman RAB.</p>
        </div>

        <div class="avoid-break" style="margin-bottom: 14px;">
            <h3 class="sub-section-title">Q3: Apakah kuitansi belanja bisa dicetak ulang jika ada nota yang hilang?</h3>
            <p><strong>Jawaban:</strong> Ya. Seluruh data kuitansi tersimpan permanen di basis data. Bendahara atau kepala sekolah dapat membuka menu <em>"Laporan" &rarr; "Kuitansi Pengeluaran"</em>, lalu klik tombol cetak pada nomor kuitansi yang dibutuhkan kapan saja.</p>
        </div>

        <h2 class="section-title">10.2 Kontak Layanan Bantuan & Supervisi</h2>
        <p>Bila satuan pendidikan menemui kendala teknis atau memiliki pertanyaan terkait perlakuan akuntansi perbendaharaan revitalisasi sekolah, silakan menghubungi Tim Teknis Dinas:</p>

        <table class="doc-table">
            <thead>
                <tr>
                    <th>Unit Pelaksana</th>
                    <th>Alamat Kantor</th>
                    <th>Kanal Bantuan / Layanan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Bidang Sarana dan Prasarana</strong><br>Dinas Pendidikan dan Kebudayaan Kab. Situbondo</td>
                    <td>Jl. Madura No. 55, Situbondo, Jawa Timur</td>
                    <td>Email: sarpras@disdik.situbondokab.go.id<br>Telepon: (0338) 671122</td>
                </tr>
                <tr>
                    <td><strong>Tim Helpdesk Sistem Informasi Si Revita</strong></td>
                    <td>Gedung Layanan Terpadu Lt. 2 Disdikbud Situbondo</td>
                    <td>WhatsApp Helpdesk: 0812-3456-7890<br>Jam Layanan: Senin - Jumat (08.00 - 15.30 WIB)</td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 40px; text-align: center; border-top: 1px solid #cbd5e1; padding-top: 20px;">
            <p style="font-weight: 700; color: #0f172a;">SI REVITA SITUBONDO — TAHUN ANGGARAN 2026</p>
            <p style="font-size: 8.5pt; color: #64748b;">Dokumen ini merupakan publikasi resmi petunjuk operasional sistem aplikasi pelaporan revitalisasi sekolah Kabupaten Situbondo.</p>
        </div>
    </div>

</body>
</html>
"""

# 1. Write HTML file
html_path = r"e:\laragon\www\sirevita\manual_assets\Buku_Panduan_Penggunaan_Si_Revita.html"
with open(html_path, "w", encoding="utf-8") as f:
    f.write(html_content)

print(f"HTML guide generated at: {html_path}")

# 2. Compile to PDF using headless Edge
edge_exe = r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
pdf_path = r"e:\laragon\www\sirevita\Buku_Panduan_Penggunaan_Si_Revita.pdf"

print("Compiling HTML to PDF using headless Edge...")
cmd = [
    edge_exe,
    "--headless=new",
    "--disable-gpu",
    f"--print-to-pdf={pdf_path}",
    "--no-pdf-header-footer",
    f"file:///{html_path.replace(os.sep, '/')}"
]

res = subprocess.run(cmd, capture_output=True, text=True)
print("Return code:", res.returncode)

if os.path.exists(pdf_path):
    size = os.path.getsize(pdf_path)
    print(f"SUCCESS: PDF generated at {pdf_path} (Size: {size:,} bytes)")
else:
    print("FAILED: PDF not created")
