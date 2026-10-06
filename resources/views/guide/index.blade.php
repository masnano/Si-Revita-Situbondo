@extends('layouts.app')

@section('title', 'Buku Panduan Penggunaan')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Toolbar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                <i data-lucide="book-open" class="w-4 h-4"></i>
                <span>Petunjuk Operasional & Standar Pelaporan</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                <span>Buku Panduan Penggunaan (User Manual)</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold border border-emerald-300">
                    Edisi Resmi 2026
                </span>
            </h1>
            <p class="text-xs text-slate-500 mt-1 max-w-2xl leading-relaxed">
                Dokumen komprehensif berisi panduan langkah demi langkah penggunaan aplikasi Si Revita Situbondo, mulai dari perumusan RAB, pembukuan kas/bank, pemotongan pajak otomatis, hingga penerbitan 4 buku perbendaharaan baku (BKU, BPK, BB, BP) dan kuitansi resmi.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <!-- Unduh File PDF Button -->
            <a href="{{ route('panduan.download') }}" 
               class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-2 shadow-md shadow-emerald-600/25 transition-all">
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>Unduh PDF Panduan ({{ $fileSize }} MB)</span>
            </a>

            <!-- Buka di Tab Baru Button -->
            <a href="{{ asset('docs/Buku_Panduan_Penggunaan_Si_Revita.pdf') }}" 
               target="_blank" 
               class="px-4 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 font-semibold text-xs flex items-center gap-2 transition-colors">
                <i data-lucide="external-link" class="w-4 h-4"></i>
                <span>Buka di Tab Baru</span>
            </a>
        </div>
    </div>

    <!-- Quick Highlights / Ringkasan Bab Buku Panduan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <i data-lucide="shield-check" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Bab 1 - 3</span>
                <h4 class="text-xs font-bold text-slate-900 mt-0.5">Akses & Data Master</h4>
                <p class="text-[11px] text-slate-500 mt-0.5">Hierarki hak akses, login cepat, profil sekolah, dan pagu paket proyek.</p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <i data-lucide="clipboard-list" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Bab 4 - 5</span>
                <h4 class="text-xs font-bold text-slate-900 mt-0.5">RAB & Transaksi</h4>
                <p class="text-[11px] text-slate-500 mt-0.5">4 kategori belanja baku, kas/bank debit-kredit, dan kalkulator pajak PPN/PPh.</p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                <i data-lucide="receipt-tax" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-sky-600 uppercase tracking-wider">Bab 6 - 7</span>
                <h4 class="text-xs font-bold text-slate-900 mt-0.5">Setor NTPN & 4 Buku</h4>
                <p class="text-[11px] text-slate-500 mt-0.5">Input nomor NTPN pajak, output BKU, BPK (Kas), BB (Bank), dan BP (Pajak).</p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                <i data-lucide="printer" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-purple-600 uppercase tracking-wider">Bab 8 - 10</span>
                <h4 class="text-xs font-bold text-slate-900 mt-0.5">Kuitansi, Role & FAQ</h4>
                <p class="text-[11px] text-slate-500 mt-0.5">Cetak kuitansi 3 tanda tangan, matriks izin Root, dan solusi kendala teknis.</p>
            </div>
        </div>
    </div>

    <!-- Embedded PDF Viewer Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col">
        <!-- Viewer Header Toolbar -->
        <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></div>
                <div class="text-xs font-bold text-slate-200">
                    Penampil Dokumen PDF Terpadu (21 Halaman Lengkap dengan Tangkapan Layar)
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs">
                <a href="{{ route('panduan.download') }}" 
                   title="Unduh Salinan PDF"
                   class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white flex items-center gap-1.5 transition-colors font-medium">
                    <i data-lucide="download" class="w-3.5 h-3.5 text-emerald-400"></i>
                    <span>Unduh PDF</span>
                </a>
                <a href="{{ asset('docs/Buku_Panduan_Penggunaan_Si_Revita.pdf') }}" 
                   target="_blank"
                   title="Buka pada tab penuh browser"
                   class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white flex items-center gap-1.5 transition-colors font-medium">
                    <i data-lucide="maximize-2" class="w-3.5 h-3.5 text-sky-400"></i>
                    <span class="hidden sm:inline">Layar Penuh</span>
                </a>
            </div>
        </div>

        <!-- PDF Embedded Frame -->
        @if($pdfExists)
        <div class="w-full bg-slate-100 relative min-h-[750px] sm:min-h-[850px]">
            <iframe src="{{ asset('docs/Buku_Panduan_Penggunaan_Si_Revita.pdf') }}#toolbar=1&navpanes=0&scrollbar=1" 
                    type="application/pdf"
                    class="w-full h-[750px] sm:h-[850px] border-0">
                <!-- Fallback content if browser cannot render iframe PDF -->
                <div class="p-12 text-center">
                    <i data-lucide="file-text" class="w-12 h-12 text-slate-400 mx-auto mb-3"></i>
                    <h3 class="text-base font-bold text-slate-800">Browser Anda tidak mendukung pratinjau PDF langsung</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                        Anda tetap dapat membaca seluruh isi buku panduan dengan mengunduh file dokumen resmi di bawah ini.
                    </p>
                    <div class="mt-4">
                        <a href="{{ route('panduan.download') }}" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs inline-flex items-center gap-2">
                            <i data-lucide="download" class="w-4 h-4"></i>
                            <span>Unduh Buku Panduan PDF</span>
                        </a>
                    </div>
                </div>
            </iframe>
        </div>
        @else
        <div class="p-12 text-center bg-rose-50 border border-rose-200 m-6 rounded-xl">
            <i data-lucide="alert-triangle" class="w-10 h-10 text-rose-500 mx-auto mb-2"></i>
            <h3 class="text-sm font-bold text-rose-800">File Buku Panduan Belum Tersedia</h3>
            <p class="text-xs text-rose-600 mt-1">Silakan hubungi administrator sistem untuk mengompilasi dokumen panduan.</p>
        </div>
        @endif

        <!-- Viewer Footer Bar -->
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>
                Pemerintah Kabupaten Situbondo — Dinas Pendidikan dan Kebudayaan — Bidang Sarana & Prasarana
            </div>
            <div class="text-[11px] text-slate-400">
                Terakhir Diperbarui: <strong>Tahun Anggaran 2026</strong>
            </div>
        </div>
    </div>

</div>
@endsection

