@extends('layouts.app')

@php
    $mInfo = $reportData['month_info'];
    $monthName = $mInfo['month_name'];
    $year = $mInfo['year'];
    $monthIndex = $reportData['month_index'];
    $schoolCleanName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $selectedSchool->name ?? 'Sekolah');
    $pageTitle = "Format Kemendikdasmen Bulan ke-{$monthIndex} ({$monthName} {$year}) - {$schoolCleanName}";
    $recon = $reportData['reconciliation'];
    $closing = $reportData['closing'];
@endphp

@section('title', $pageTitle)

@section('content')
<div class="space-y-6" x-data="{ activeTab: 'bku', bppAlt: 'alt2' }">

    <!-- Header & Filter Bar -->
    <div class="bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 text-white p-6 rounded-3xl border border-emerald-500/30 shadow-xl relative overflow-hidden no-print">
        <div class="absolute -right-12 -top-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 tracking-wide uppercase">
                        <i data-lucide="award" class="w-3.5 h-3.5 text-emerald-400"></i>
                        Format Kemendikdasmen 2026
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-800 text-slate-300 border border-slate-700">
                        Revitalisasi SD {{ $selectedSchool->status === 'Negeri' ? 'Sekolah Negeri' : 'Sekolah Swasta' }}
                    </span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-white flex items-center gap-3">
                    <span>Output Laporan Keuangan & Perpajakan</span>
                </h1>
                <p class="text-sm text-slate-300 mt-1 max-w-2xl leading-relaxed">
                    Format pembukuan resmi 4 Buku (BKU, BPKT, BB, BPP) sesuai materi Bimtek Percepatan Kemendikdasmen. Dilengkapi rumus otomatis, metode bruto perpajakan, dan verifikasi rekonsiliasi kas.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-emerald-200/90 font-mono">
                    <span><strong class="text-white">{{ $selectedSchool->name }}</strong> (NPSN: {{ $selectedSchool->npsn }})</span>
                    <span>•</span>
                    <span>Rekening: {{ $selectedSchool->bank_name }} ({{ $selectedSchool->bank_account_number }})</span>
                </div>
            </div>

            <!-- Filter & Action Controls -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                <form action="{{ route('reports.kemendikdasmen') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    @role('root,admin')
                    <select name="school_id" onchange="this.form.submit()" 
                            class="text-xs font-semibold px-3 py-2.5 rounded-xl border border-slate-700 bg-slate-800 text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @foreach($schools as $sch)
                            <option value="{{ $sch->id }}" {{ $selectedSchool->id == $sch->id ? 'selected' : '' }}>
                                {{ $sch->name }} ({{ $sch->status }})
                            </option>
                        @endforeach
                    </select>
                    @endrole

                    <select name="month_index" onchange="this.form.submit()" 
                            class="text-xs font-bold px-3 py-2.5 rounded-xl border border-emerald-500/50 bg-emerald-950/80 text-emerald-200 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @foreach($availableMonths as $idx => $m)
                            <option value="{{ $idx }}" {{ $monthIndex == $idx ? 'selected' : '' }}>
                                {{ $m['label'] }} ({{ $m['count'] }} Transaksi)
                            </option>
                        @endforeach
                    </select>
                </form>

                <div class="flex items-center gap-2">
                    <!-- Export Excel Resmi (Split Button: Default Semua Bulan, Opsi Per Bulan) -->
                    <div class="relative inline-flex items-center shadow-lg shadow-emerald-900/50 rounded-xl" x-data="{ openExport: false }">
                        <a href="{{ route('reports.kemendikdasmen.export', ['school_id' => $selectedSchool->id]) }}" 
                           title="Unduh 1 file Excel utuh memuat semua bulan (Bulan 1 s.d. selesai) sesuai format resmi Bimtek"
                           class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-l-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition-all border-y border-l border-emerald-400/30">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-200"></i>
                            <span>Export Excel (Semua Bulan)</span>
                        </a>
                        <button type="button" @click="openExport = !openExport" @click.outside="openExport = false"
                                title="Pilihan Opsi Export"
                                class="px-2 py-2.5 rounded-r-xl bg-emerald-700 hover:bg-emerald-600 text-white border-y border-r border-emerald-400/30 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4 text-emerald-200"></i>
                        </button>

                        <!-- Dropdown Options -->
                        <div x-show="openExport" x-cloak
                             class="absolute right-0 top-full mt-2 w-64 rounded-xl bg-slate-900 border border-slate-700 shadow-2xl py-1.5 z-50 text-xs">
                            <a href="{{ route('reports.kemendikdasmen.export', ['school_id' => $selectedSchool->id]) }}" 
                               class="flex items-start gap-2.5 px-3.5 py-2 text-white hover:bg-emerald-600/30 font-semibold border-b border-slate-800 transition-colors">
                                <i data-lucide="layers" class="w-4 h-4 text-emerald-400 mt-0.5 shrink-0"></i>
                                <div>
                                    <div class="font-bold text-emerald-300">Semua Bulan (Standar Bimtek)</div>
                                    <div class="text-[10px] text-slate-400 font-normal">1 File Excel memuat Bulan 1 s.d. {{ count($availableMonths) }}</div>
                                </div>
                            </a>
                            <a href="{{ route('reports.kemendikdasmen.export', ['school_id' => $selectedSchool->id, 'month_index' => $monthIndex]) }}" 
                               class="flex items-start gap-2.5 px-3.5 py-2 text-slate-300 hover:bg-slate-800 font-semibold transition-colors">
                                <i data-lucide="calendar" class="w-4 h-4 text-blue-400 mt-0.5 shrink-0"></i>
                                <div>
                                    <div class="font-bold text-slate-200">Hanya Bulan ke-{{ $monthIndex }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ $availableMonths[$monthIndex]['label'] ?? "Bulan ke-$monthIndex" }}</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Download Master Template Kosong -->
                    <a href="{{ route('reports.kemendikdasmen.template') }}" 
                       title="Unduh Format Kosong BKU BPKT BB BPP 2026 Asli Kemendikdasmen"
                       class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs border border-slate-700 transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        <span>Template Kosong</span>
                    </a>

                    <!-- Print Button -->
                    <button onclick="window.print()" 
                            class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs border border-slate-700 transition-colors">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span>Cetak</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reconciliation & Compliance Summary Widget -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 no-print">
        <!-- Saldo BKU -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Saldo Buku Kas Umum</div>
            <div class="text-xl font-black text-slate-900 font-mono">
                Rp {{ number_format($recon['saldo_bku'], 2, ',', '.') }}
            </div>
            <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>BKU Bulan ke-{{ $monthIndex }}</span>
            </div>
        </div>

        <!-- Saldo Bank -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">1. Saldo Bank (BB)</div>
            <div class="text-xl font-black text-sky-700 font-mono">
                Rp {{ number_format($recon['saldo_bank'], 2, ',', '.') }}
            </div>
            <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                <span>Rekening Operasional</span>
            </div>
        </div>

        <!-- Saldo Kas Tunai -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">2. Saldo Kas Tunai (BPKT)</div>
            <div class="text-xl font-black text-amber-700 font-mono">
                Rp {{ number_format($recon['saldo_kas_tunai'], 2, ',', '.') }}
            </div>
            <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Uang di Brankas Sekolah</span>
            </div>
        </div>

        <!-- Status Perbedaan (Reconciliation Status) -->
        <div class="bg-white p-4 rounded-2xl border {{ $recon['is_balanced'] ? 'border-emerald-300 bg-emerald-50/40' : 'border-rose-300 bg-rose-50/40' }} shadow-sm">
            <div class="text-[11px] font-bold {{ $recon['is_balanced'] ? 'text-emerald-700' : 'text-rose-700' }} uppercase tracking-wider mb-1 flex items-center justify-between">
                <span>Perbedaan Buku</span>
                <i data-lucide="{{ $recon['is_balanced'] ? 'check-circle-2' : 'alert-circle' }}" class="w-4 h-4"></i>
            </div>
            <div class="text-xl font-black font-mono {{ $recon['is_balanced'] ? 'text-emerald-700' : 'text-rose-700' }}">
                Rp {{ number_format($recon['perbedaan'], 2, ',', '.') }}
            </div>
            <div class="text-[10px] {{ $recon['is_balanced'] ? 'text-emerald-700 font-semibold' : 'text-rose-700 font-semibold' }} mt-1">
                {{ $recon['is_balanced'] ? '✓ SEIMBANG (BALANCE Rp 0)' : '⚠ PERBEDAAN TERDETEKSI' }}
            </div>
        </div>

        <!-- Rekap Pajak Belum Disetor -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Pajak Belum Disetor</div>
            <div class="text-xl font-black text-rose-700 font-mono">
                Rp {{ number_format($reportData['bpp']['saldo_belum_disetor'], 2, ',', '.') }}
            </div>
            <div class="text-[10px] text-slate-500 mt-1">
                Dipungut: Rp {{ number_format($reportData['bpp']['total_dipungut'], 0, ',', '.') }}
            </div>
        </div>
    </div>

    <!-- Navigation Tabs for the 4 Books + Guide -->
    <div class="border-b border-slate-200 no-print">
        <nav class="flex space-x-2 -mb-px" aria-label="Tabs">
            <!-- Tab BKU -->
            <button @click="activeTab = 'bku'" 
                    :class="activeTab === 'bku' ? 'border-emerald-600 text-emerald-700 bg-emerald-50/50 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-4 text-xs rounded-t-xl border-b-2 flex items-center gap-2 transition-all">
                <i data-lucide="book-open" class="w-4 h-4 text-emerald-600"></i>
                <span>Buku Kas Umum (BKU{{ $monthIndex }})</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-emerald-100 text-emerald-800 font-mono font-semibold">
                    {{ count($reportData['bku']['rows']) }}
                </span>
            </button>

            <!-- Tab BPKT -->
            <button @click="activeTab = 'bpkt'" 
                    :class="activeTab === 'bpkt' ? 'border-amber-600 text-amber-700 bg-amber-50/50 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-4 text-xs rounded-t-xl border-b-2 flex items-center gap-2 transition-all">
                <i data-lucide="wallet" class="w-4 h-4 text-amber-600"></i>
                <span>Kas Tunai (BPKT{{ $monthIndex }})</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-amber-100 text-amber-800 font-mono font-semibold">
                    {{ count($reportData['bpkt']['rows']) }}
                </span>
            </button>

            <!-- Tab BB -->
            <button @click="activeTab = 'bb'" 
                    :class="activeTab === 'bb' ? 'border-sky-600 text-sky-700 bg-sky-50/50 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-4 text-xs rounded-t-xl border-b-2 flex items-center gap-2 transition-all">
                <i data-lucide="building-2" class="w-4 h-4 text-sky-600"></i>
                <span>Buku Bank (BB{{ $monthIndex }})</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-sky-100 text-sky-800 font-mono font-semibold">
                    {{ count($reportData['bb']['rows']) }}
                </span>
            </button>

            <!-- Tab BPP -->
            <button @click="activeTab = 'bpp'" 
                    :class="activeTab === 'bpp' ? 'border-rose-600 text-rose-700 bg-rose-50/50 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-4 text-xs rounded-t-xl border-b-2 flex items-center gap-2 transition-all">
                <i data-lucide="receipt-tax" class="w-4 h-4 text-rose-600"></i>
                <span>Buku Pajak (BPP{{ $monthIndex }})</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-rose-100 text-rose-800 font-mono font-semibold">
                    {{ count($reportData['bpp']['alt1_rows']) }}
                </span>
            </button>

            <!-- Tab Petunjuk Bimtek Kemendikdasmen -->
            <button @click="activeTab = 'petunjuk'" 
                    :class="activeTab === 'petunjuk' ? 'border-purple-600 text-purple-700 bg-purple-50/50 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-4 text-xs rounded-t-xl border-b-2 flex items-center gap-2 transition-all">
                <i data-lucide="info" class="w-4 h-4 text-purple-600"></i>
                <span>Petunjuk Format Kemendikdasmen</span>
            </button>
        </nav>
    </div>

    <!-- Print Only Header -->
    <div class="hidden print:block text-center border-b-2 border-slate-900 pb-4 mb-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH</h2>
        <h3 class="text-xs font-semibold text-slate-700">DIREKTORAT SEKOLAH DASAR - PROGRAM REVITALISASI SD TAHUN 2026</h3>
        <h1 class="text-base font-extrabold uppercase mt-1 text-slate-900" id="printReportTitle">
            LAPORAN KEUANGAN & PERPAJAKAN
        </h1>
        <p class="text-xs text-slate-600 mt-1">
            <strong>{{ $selectedSchool->name }}</strong> · Bulan {{ $monthName }} {{ $year }} (Bulan ke-{{ $monthIndex }})
        </p>
    </div>

    <!-- TAB CONTENT: BKU -->
    <div x-show="activeTab === 'bku'" class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">BUKU KAS UMUM (BKU{{ $monthIndex }})</h2>
                    <p class="text-xs text-slate-500 font-mono">Bulan {{ $monthName }} {{ $year }}</p>
                </div>
                <div class="text-xs text-slate-600">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 font-semibold">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        Metode Pencatatan Bruto (DPP + PPN / Pajak)
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-emerald-800 text-white font-bold text-center uppercase tracking-wider">
                            <th class="py-3 px-2 border-r border-emerald-700 w-12">No</th>
                            <th class="py-3 px-3 border-r border-emerald-700 w-24">Tanggal</th>
                            <th class="py-3 px-4 border-r border-emerald-700 text-left">Uraian Transaksi</th>
                            <th class="py-3 px-3 border-r border-emerald-700 w-28">No. Bukti</th>
                            <th class="py-3 px-3 border-r border-emerald-700 text-right w-32">Penerimaan (Rp)</th>
                            <th class="py-3 px-3 border-r border-emerald-700 text-right w-32">Pengeluaran (Rp)</th>
                            <th class="py-3 px-3 text-right w-36">Saldo (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-sans">
                        @forelse($reportData['bku']['rows'] as $idx => $row)
                        <tr class="hover:bg-slate-50/80 transition-colors {{ !empty($row['is_initial']) ? 'bg-amber-50/60 font-semibold' : '' }}">
                            <td class="py-2.5 px-2 text-center text-slate-600 font-mono border-r border-slate-100">
                                {{ $idx + 1 }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono border-r border-slate-100 whitespace-nowrap">
                                {{ $row['tanggal'] instanceof \Carbon\Carbon ? $row['tanggal']->format('d/m/y') : $row['tanggal'] }}
                            </td>
                            <td class="py-2.5 px-4 text-slate-800 border-r border-slate-100 leading-snug">
                                <div class="font-medium">{{ $row['uraian'] }}</div>
                                @if(!empty($row['tgl_setor_pajak']))
                                    <div class="text-[10px] text-amber-700 font-mono mt-0.5 flex items-center gap-1">
                                        <i data-lucide="calendar-clock" class="w-3 h-3"></i>
                                        <span>Rencana Setor: {{ $row['tgl_setor_pajak'] instanceof \Carbon\Carbon ? $row['tgl_setor_pajak']->format('d/m/y') : $row['tgl_setor_pajak'] }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['no_bukti'] }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-emerald-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['penerimaan'] > 0 ? number_format($row['penerimaan'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-rose-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['pengeluaran'] > 0 ? number_format($row['pengeluaran'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap bg-emerald-50/30">
                                {{ number_format($row['saldo'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">Belum ada transaksi pada bulan ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300">
                            <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider text-xs border-r border-slate-200">
                                Total Buku Kas Umum (BKU{{ $monthIndex }})
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-800 border-r border-slate-200">
                                {{ number_format($reportData['bku']['total_penerimaan'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-rose-800 border-r border-slate-200">
                                {{ number_format($reportData['bku']['total_pengeluaran'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-slate-900 bg-emerald-100/60 font-black">
                                {{ number_format($reportData['bku']['saldo_akhir'], 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Bagian Penutupan BKU & Tanda Tangan Resmi Kemendikdasmen -->
            <div class="p-6 bg-slate-50/70 border-t border-slate-200 space-y-6">
                <!-- Narasi Penutupan -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-semibold text-slate-800 leading-relaxed mb-4">
                        {{ $closing['kalimat_penutupan'] }}
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                        <!-- Posisi Kas & Bank -->
                        <div class="space-y-2 border-r border-slate-100 pr-4">
                            <div class="font-bold text-slate-900 uppercase tracking-wider text-[11px] mb-2">Keadaan Posisi Kas:</div>
                            <div class="flex justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-600 font-medium">Saldo Buku Kas Umum:</span>
                                <span class="font-mono font-bold text-slate-900">Rp {{ number_format($recon['saldo_bku'], 2, ',', '.') }}</span>
                            </div>
                            <div class="text-slate-500 font-semibold pt-1">Terdiri dari:</div>
                            <div class="flex justify-between py-1 pl-4 border-b border-slate-100">
                                <span class="text-slate-600">1. Saldo Bank (BB{{ $monthIndex }}):</span>
                                <span class="font-mono font-semibold text-sky-700">Rp {{ number_format($recon['saldo_bank'], 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between py-1 pl-4 border-b border-slate-100">
                                <span class="text-slate-600">2. Saldo Pembantu Kas Tunai (BPKT{{ $monthIndex }}):</span>
                                <span class="font-mono font-semibold text-amber-700">Rp {{ number_format($recon['saldo_kas_tunai'], 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 font-bold border-t border-slate-200 bg-slate-50 px-2 rounded">
                                <span class="text-slate-800">Total Bank + Kas Tunai:</span>
                                <span class="font-mono text-slate-900">Rp {{ number_format($recon['total_bank_tunai'], 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 font-bold {{ $recon['is_balanced'] ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }} px-2 rounded">
                                <span>Perbedaan:</span>
                                <span class="font-mono">Rp {{ number_format($recon['perbedaan'], 2, ',', '.') }} ({{ $recon['is_balanced'] ? 'SEIMBANG' : 'SELISIH' }})</span>
                            </div>
                        </div>

                        <!-- Rekapitulasi Pajak -->
                        <div class="space-y-2">
                            <div class="font-bold text-slate-900 uppercase tracking-wider text-[11px] mb-2">Rekapitulasi Pajak (Buku Pembantu Pajak):</div>
                            @if($reportData['bpp']['saldo_awal'] > 0)
                            <div class="flex justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-600">Saldo awal pajak belum disetor:</span>
                                <span class="font-mono font-semibold text-slate-700">Rp {{ number_format($reportData['bpp']['saldo_awal'], 2, ',', '.') }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-600">Pajak dipungut/dipotong bulan ini:</span>
                                <span class="font-mono font-semibold text-slate-900">Rp {{ number_format($reportData['bpp']['total_dipungut'], 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-600">Pajak disetor ke Kas Negara:</span>
                                <span class="font-mono font-semibold text-emerald-700">Rp {{ number_format($reportData['bpp']['total_disetor'], 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 font-bold bg-rose-50 text-rose-900 px-2 rounded border border-rose-200">
                                <span>Pajak belum disetor (dalam saldo BKU):</span>
                                <span class="font-mono">Rp {{ number_format($reportData['bpp']['saldo_belum_disetor'], 2, ',', '.') }}</span>
                            </div>
                            <p class="text-[10px] text-slate-500 italic mt-2 leading-relaxed">
                                Catatan: Pajak yang belum disetor masih berada di rekening bank/kas tunai dan wajib disetor pada bulan berikutnya.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tanda Tangan Formal 3 Pihak -->
                <div class="pt-4">
                    <div class="text-center font-semibold text-slate-700 text-xs mb-6">
                        {{ $closing['lokasi'] }}, {{ $closing['tanggal_str'] }}
                        <div class="text-[11px] text-slate-500 font-normal">Mengetahui,</div>
                    </div>

                    <div class="grid grid-cols-3 gap-6 text-center text-xs">
                        <!-- Kepala Sekolah -->
                        <div class="space-y-16">
                            <div class="font-bold text-slate-900">{{ $closing['kepala_sekolah']['jabatan'] }}</div>
                            <div>
                                <div class="font-bold text-slate-900 underline">{{ $closing['kepala_sekolah']['nama'] }}</div>
                                <div class="text-slate-500 text-[11px]">NIP. {{ $closing['kepala_sekolah']['nip'] }}</div>
                            </div>
                        </div>

                        <!-- Ketua P2SP -->
                        <div class="space-y-16">
                            <div class="font-bold text-slate-900">{{ $closing['ketua_p2sp']['jabatan'] }}</div>
                            <div>
                                <div class="font-bold text-slate-900 underline">{{ $closing['ketua_p2sp']['nama'] }}</div>
                                <div class="text-slate-500 text-[11px]">Ketua Panitia Revitalisasi</div>
                            </div>
                        </div>

                        <!-- Bendahara -->
                        <div class="space-y-16">
                            <div class="font-bold text-slate-900">{{ $closing['bendahara']['jabatan'] }}</div>
                            <div>
                                <div class="font-bold text-slate-900 underline">{{ $closing['bendahara']['nama'] }}</div>
                                <div class="text-slate-500 text-[11px]">NIP. {{ $closing['bendahara']['nip'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT: BPKT (Buku Pembantu Kas Tunai) -->
    <div x-show="activeTab === 'bpkt'" class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
            <div class="p-4 bg-amber-50/50 border-b border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">BUKU PEMBANTU KAS TUNAI (BPKT{{ $monthIndex }})</h2>
                    <p class="text-xs text-slate-500 font-mono">Bulan {{ $monthName }} {{ $year }}</p>
                </div>
                <div class="text-xs text-amber-800 font-semibold bg-amber-100/80 px-3 py-1 rounded-lg border border-amber-300">
                    Saldo Akhir Kas Tunai: Rp {{ number_format($reportData['bpkt']['saldo_akhir'], 0, ',', '.') }}
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-amber-800 text-white font-bold text-center uppercase tracking-wider">
                            <th class="py-3 px-2 border-r border-amber-700 w-12">No</th>
                            <th class="py-3 px-3 border-r border-amber-700 w-24">Tanggal</th>
                            <th class="py-3 px-4 border-r border-amber-700 text-left">Uraian Transaksi Tunai</th>
                            <th class="py-3 px-3 border-r border-amber-700 w-28">No. Bukti</th>
                            <th class="py-3 px-3 border-r border-amber-700 text-right w-32">Penerimaan (Rp)</th>
                            <th class="py-3 px-3 border-r border-amber-700 text-right w-32">Pengeluaran (Rp)</th>
                            <th class="py-3 px-3 text-right w-36">Saldo Kas (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-sans">
                        @forelse($reportData['bpkt']['rows'] as $idx => $row)
                        <tr class="hover:bg-amber-50/30 transition-colors {{ !empty($row['is_initial']) ? 'bg-amber-50/60 font-semibold' : '' }}">
                            <td class="py-2.5 px-2 text-center text-slate-600 font-mono border-r border-slate-100">
                                {{ $idx + 1 }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono border-r border-slate-100 whitespace-nowrap">
                                {{ $row['tanggal'] instanceof \Carbon\Carbon ? $row['tanggal']->format('d/m/y') : $row['tanggal'] }}
                            </td>
                            <td class="py-2.5 px-4 text-slate-800 border-r border-slate-100 leading-snug">
                                {{ $row['uraian'] }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['no_bukti'] }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-emerald-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['penerimaan'] > 0 ? number_format($row['penerimaan'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-rose-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['pengeluaran'] > 0 ? number_format($row['pengeluaran'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap bg-amber-50/40">
                                {{ number_format($row['saldo'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">Belum ada transaksi kas tunai pada bulan ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300">
                            <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider text-xs border-r border-slate-200">
                                Total Buku Kas Tunai (BPKT{{ $monthIndex }})
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-800 border-r border-slate-200">
                                {{ number_format($reportData['bpkt']['total_penerimaan'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-rose-800 border-r border-slate-200">
                                {{ number_format($reportData['bpkt']['total_pengeluaran'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-slate-900 bg-amber-100/60 font-black">
                                {{ number_format($reportData['bpkt']['saldo_akhir'], 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT: BB (Buku Bank) -->
    <div x-show="activeTab === 'bb'" class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
            <div class="p-4 bg-sky-50/50 border-b border-sky-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">BUKU BANK (BB{{ $monthIndex }})</h2>
                    <p class="text-xs text-slate-500 font-mono">Bulan {{ $monthName }} {{ $year }}</p>
                </div>
                <div class="text-xs text-sky-800 font-semibold bg-sky-100/80 px-3 py-1 rounded-lg border border-sky-300">
                    Saldo Akhir Rekening Bank: Rp {{ number_format($reportData['bb']['saldo_akhir'], 0, ',', '.') }}
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-sky-800 text-white font-bold text-center uppercase tracking-wider">
                            <th class="py-3 px-2 border-r border-sky-700 w-12">No</th>
                            <th class="py-3 px-3 border-r border-sky-700 w-24">Tanggal</th>
                            <th class="py-3 px-4 border-r border-sky-700 text-left">Uraian Transaksi Bank</th>
                            <th class="py-3 px-3 border-r border-sky-700 w-28">No. Bukti</th>
                            <th class="py-3 px-3 border-r border-sky-700 text-right w-32">Penerimaan (Rp)</th>
                            <th class="py-3 px-3 border-r border-sky-700 text-right w-32">Pengeluaran (Rp)</th>
                            <th class="py-3 px-3 text-right w-36">Saldo Bank (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-sans">
                        @forelse($reportData['bb']['rows'] as $idx => $row)
                        <tr class="hover:bg-sky-50/30 transition-colors {{ !empty($row['is_initial']) ? 'bg-sky-50/60 font-semibold' : '' }}">
                            <td class="py-2.5 px-2 text-center text-slate-600 font-mono border-r border-slate-100">
                                {{ $idx + 1 }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono border-r border-slate-100 whitespace-nowrap">
                                {{ $row['tanggal'] instanceof \Carbon\Carbon ? $row['tanggal']->format('d/m/y') : $row['tanggal'] }}
                            </td>
                            <td class="py-2.5 px-4 text-slate-800 border-r border-slate-100 leading-snug">
                                {{ $row['uraian'] }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['no_bukti'] }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-emerald-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['penerimaan'] > 0 ? number_format($row['penerimaan'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-rose-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['pengeluaran'] > 0 ? number_format($row['pengeluaran'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap bg-sky-50/40">
                                {{ number_format($row['saldo'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">Belum ada transaksi melalui bank pada bulan ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300">
                            <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider text-xs border-r border-slate-200">
                                Total Buku Bank (BB{{ $monthIndex }})
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-800 border-r border-slate-200">
                                {{ number_format($reportData['bb']['total_penerimaan'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-rose-800 border-r border-slate-200">
                                {{ number_format($reportData['bb']['total_pengeluaran'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-slate-900 bg-sky-100/60 font-black">
                                {{ number_format($reportData['bb']['saldo_akhir'], 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT: BPP (Buku Pembantu Pajak) -->
    <div x-show="activeTab === 'bpp'" class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
            <div class="p-4 bg-rose-50/40 border-b border-rose-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">BUKU PEMBANTU PAJAK (BPP{{ $monthIndex }})</h2>
                    <p class="text-xs text-slate-500 font-mono">Bulan {{ $monthName }} {{ $year }}</p>
                </div>

                <!-- Toggle Alternatif 1 vs Alternatif 2 -->
                <div class="inline-flex rounded-xl p-1 bg-slate-200/80 border border-slate-300/80">
                    <button type="button" @click="bppAlt = 'alt2'"
                            :class="bppAlt === 'alt2' ? 'bg-white text-rose-700 font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900 font-medium'"
                            class="px-3 py-1.5 text-xs rounded-lg transition-all">
                        Alternatif 2 (Kolom Per Jenis Pajak)
                    </button>
                    <button type="button" @click="bppAlt = 'alt1'"
                            :class="bppAlt === 'alt1' ? 'bg-white text-rose-700 font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900 font-medium'"
                            class="px-3 py-1.5 text-xs rounded-lg transition-all">
                        Alternatif 1 (Rekap Pajak)
                    </button>
                </div>
            </div>

            <!-- BPP Alternatif 2 (Kolom per jenis pajak) -->
            <div x-show="bppAlt === 'alt2'" class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-rose-900 text-white font-bold text-center uppercase tracking-wider">
                            <th class="py-3 px-2 border-r border-rose-800 w-10">No</th>
                            <th class="py-3 px-3 border-r border-rose-800 w-20">Tanggal</th>
                            <th class="py-3 px-4 border-r border-rose-800 text-left">Uraian Transaksi</th>
                            <th class="py-3 px-2 border-r border-rose-800 w-24">No. Bukti</th>
                            <th class="py-3 px-2 border-r border-rose-800 w-24">Jenis Bukti</th>
                            <th class="py-3 px-2 border-r border-rose-800 w-32">Nomor Bukti Pajak</th>
                            <th class="py-3 px-2 border-r border-rose-800 text-right w-24">PPN</th>
                            <th class="py-3 px-2 border-r border-rose-800 text-right w-24">PPh 4(2)</th>
                            <th class="py-3 px-2 border-r border-rose-800 text-right w-24">PPh 21</th>
                            <th class="py-3 px-2 border-r border-rose-800 text-right w-24">PPh 22</th>
                            <th class="py-3 px-2 border-r border-rose-800 text-right w-24">PPh 23</th>
                            <th class="py-3 px-2 text-right w-24">Pajak Daerah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-sans">
                        @forelse($reportData['bpp']['alt2_rows'] as $idx => $row)
                        <tr class="hover:bg-rose-50/20 transition-colors {{ !empty($row['is_initial']) ? 'bg-rose-50/50 font-semibold' : '' }}">
                            <td class="py-2.5 px-2 text-center text-slate-600 font-mono border-r border-slate-100">
                                {{ $idx + 1 }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono border-r border-slate-100 whitespace-nowrap">
                                {{ $row['tanggal'] instanceof \Carbon\Carbon ? $row['tanggal']->format('d/m/y') : $row['tanggal'] }}
                            </td>
                            <td class="py-2.5 px-4 text-slate-800 border-r border-slate-100 leading-snug">
                                {{ $row['uraian'] }}
                            </td>
                            <td class="py-2.5 px-2 text-center text-slate-700 font-mono font-medium border-r border-slate-100 whitespace-nowrap">
                                {{ $row['no_bukti'] }}
                            </td>
                            <td class="py-2.5 px-2 text-center text-slate-600 font-sans border-r border-slate-100 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded text-[10px] {{ $row['jenis_bukti'] === 'NTPN' ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $row['jenis_bukti'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-2 text-center text-slate-700 font-mono text-[11px] border-r border-slate-100 whitespace-nowrap">
                                {{ $row['nomor_bukti_pajak'] }}
                            </td>
                            <td class="py-2.5 px-2 text-right font-mono border-r border-slate-100 {{ $row['ppn'] < 0 ? 'text-rose-700 font-bold' : ($row['ppn'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-300') }}">
                                {{ $row['ppn'] < 0 ? '(' . number_format(abs($row['ppn']), 0, ',', '.') . ')' : ($row['ppn'] > 0 ? number_format($row['ppn'], 0, ',', '.') : '-') }}
                            </td>
                            <td class="py-2.5 px-2 text-right font-mono border-r border-slate-100 {{ $row['pph4_2'] < 0 ? 'text-rose-700 font-bold' : ($row['pph4_2'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-300') }}">
                                {{ $row['pph4_2'] < 0 ? '(' . number_format(abs($row['pph4_2']), 0, ',', '.') . ')' : ($row['pph4_2'] > 0 ? number_format($row['pph4_2'], 0, ',', '.') : '-') }}
                            </td>
                            <td class="py-2.5 px-2 text-right font-mono border-r border-slate-100 {{ $row['pph21'] < 0 ? 'text-rose-700 font-bold' : ($row['pph21'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-300') }}">
                                {{ $row['pph21'] < 0 ? '(' . number_format(abs($row['pph21']), 0, ',', '.') . ')' : ($row['pph21'] > 0 ? number_format($row['pph21'], 0, ',', '.') : '-') }}
                            </td>
                            <td class="py-2.5 px-2 text-right font-mono border-r border-slate-100 {{ $row['pph22'] < 0 ? 'text-rose-700 font-bold' : ($row['pph22'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-300') }}">
                                {{ $row['pph22'] < 0 ? '(' . number_format(abs($row['pph22']), 0, ',', '.') . ')' : ($row['pph22'] > 0 ? number_format($row['pph22'], 0, ',', '.') : '-') }}
                            </td>
                            <td class="py-2.5 px-2 text-right font-mono border-r border-slate-100 {{ $row['pph23'] < 0 ? 'text-rose-700 font-bold' : ($row['pph23'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-300') }}">
                                {{ $row['pph23'] < 0 ? '(' . number_format(abs($row['pph23']), 0, ',', '.') . ')' : ($row['pph23'] > 0 ? number_format($row['pph23'], 0, ',', '.') : '-') }}
                            </td>
                            <td class="py-2.5 px-2 text-right font-mono {{ $row['pajak_daerah'] < 0 ? 'text-rose-700 font-bold' : ($row['pajak_daerah'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-300') }}">
                                {{ $row['pajak_daerah'] < 0 ? '(' . number_format(abs($row['pajak_daerah']), 0, ',', '.') . ')' : ($row['pajak_daerah'] > 0 ? number_format($row['pajak_daerah'], 0, ',', '.') : '-') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="12" class="py-8 text-center text-slate-400">Belum ada transaksi pajak pada bulan ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-rose-50 font-bold text-slate-900 border-t-2 border-rose-300">
                            <td colspan="6" class="py-3 px-4 text-right uppercase tracking-wider text-xs border-r border-rose-200">
                                Saldo Pajak Belum Disetor (BPP{{ $monthIndex }} Alt2)
                            </td>
                            <td colspan="6" class="py-3 px-3 text-right font-mono text-rose-800 text-sm font-black">
                                Rp {{ number_format($reportData['bpp']['saldo_belum_disetor'], 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- BPP Alternatif 1 (Kolom Jenis Pajak) -->
            <div x-show="bppAlt === 'alt1'" class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-rose-900 text-white font-bold text-center uppercase tracking-wider">
                            <th class="py-3 px-2 border-r border-rose-800 w-12">No</th>
                            <th class="py-3 px-3 border-r border-rose-800 w-24">Tanggal</th>
                            <th class="py-3 px-4 border-r border-rose-800 text-left">Uraian Transaksi</th>
                            <th class="py-3 px-3 border-r border-rose-800 w-28">No. Bukti</th>
                            <th class="py-3 px-2 border-r border-rose-800 w-24">Jenis Bukti</th>
                            <th class="py-3 px-3 border-r border-rose-800 w-32">Nomor Bukti Pajak</th>
                            <th class="py-3 px-3 border-r border-rose-800 w-24">Jenis Pajak</th>
                            <th class="py-3 px-3 border-r border-rose-800 text-right w-28">Penerimaan</th>
                            <th class="py-3 px-3 border-r border-rose-800 text-right w-28">Pengeluaran</th>
                            <th class="py-3 px-3 text-right w-32">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-sans">
                        @forelse($reportData['bpp']['alt1_rows'] as $idx => $row)
                        <tr class="hover:bg-rose-50/20 transition-colors {{ !empty($row['is_initial']) ? 'bg-rose-50/50 font-semibold' : '' }}">
                            <td class="py-2.5 px-2 text-center text-slate-600 font-mono border-r border-slate-100">
                                {{ $idx + 1 }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono border-r border-slate-100 whitespace-nowrap">
                                {{ $row['tanggal'] instanceof \Carbon\Carbon ? $row['tanggal']->format('d/m/y') : $row['tanggal'] }}
                            </td>
                            <td class="py-2.5 px-4 text-slate-800 border-r border-slate-100 leading-snug">
                                {{ $row['uraian'] }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono font-medium border-r border-slate-100 whitespace-nowrap">
                                {{ $row['no_bukti'] }}
                            </td>
                            <td class="py-2.5 px-2 text-center text-slate-600 border-r border-slate-100 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded text-[10px] {{ $row['jenis_bukti'] === 'NTPN' ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $row['jenis_bukti'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono text-[11px] border-r border-slate-100 whitespace-nowrap">
                                {{ $row['nomor_bukti_pajak'] }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-rose-800 font-bold font-mono border-r border-slate-100 whitespace-nowrap">
                                {{ $row['jenis_pajak'] }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-emerald-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['penerimaan'] > 0 ? number_format($row['penerimaan'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-rose-700 font-mono font-semibold border-r border-slate-100 whitespace-nowrap">
                                {{ $row['pengeluaran'] > 0 ? number_format($row['pengeluaran'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap bg-rose-50/40">
                                {{ number_format($row['saldo'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-slate-400">Belum ada transaksi pajak pada bulan ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300">
                            <td colspan="7" class="py-3 px-4 text-right uppercase tracking-wider text-xs border-r border-slate-200">
                                Total Buku Pajak (BPP{{ $monthIndex }} Alt1)
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-800 border-r border-slate-200">
                                {{ number_format($reportData['bpp']['total_dipungut'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-rose-800 border-r border-slate-200">
                                {{ number_format($reportData['bpp']['total_disetor'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-rose-800 bg-rose-100/60 font-black">
                                {{ number_format($reportData['bpp']['saldo_belum_disetor'], 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT: PETUNJUK FORMAT KEMENDIKDASMEN -->
    <div x-show="activeTab === 'petunjuk'" class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-6 space-y-6">
            <div class="border-b border-slate-200 pb-4">
                <h2 class="text-lg font-black text-slate-900">Petunjuk Format Kemendikdasmen RI (Bimtek Revitalisasi SD 2026)</h2>
                <p class="text-xs text-slate-500 mt-1">Pedoman teknis pencatatan pada Buku Kas Umum (BKU), Buku Pembantu Kas Tunai (BPKT), Buku Bank (BB), dan Buku Pembantu Pajak (BPP).</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                <!-- Ketentuan Umum -->
                <div class="space-y-3">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                        <span>Ketentuan Pokok Pembukuan</span>
                    </h3>
                    <ul class="space-y-2 text-slate-600 leading-relaxed list-disc list-inside">
                        <li><strong>Susunan Sheet:</strong> Setiap bulan memiliki 4 buku (BKU, BPKT, BB, BPP) dengan indeks bulan (BKU1, BPKT1, BB1, BPP1 untuk bulan ke-1, dst).</li>
                        <li><strong>Metode Bruto Pajak:</strong> Belanja dicatat sebesar beban penuh kegiatan (DPP + PPN). Pajak yang dipungut/dipotong dicatat sebagai Penerimaan, saat disetor dicatat sebagai Pengeluaran.</li>
                        <li><strong>Satu Baris Satu Mutasi:</strong> Dalam satu baris mutasi hanya terisi salah satu (Penerimaan saja atau Pengeluaran saja).</li>
                        <li><strong>Keseimbangan Buku:</strong> Pada penutupan akhir bulan, Saldo BKU harus persis sama dengan Saldo Bank + Saldo Kas Tunai (Perbedaan Rp 0).</li>
                    </ul>
                </div>

                <!-- Alur Koding Bukti Transaksi -->
                <div class="space-y-3">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <i data-lucide="tag" class="w-4 h-4 text-sky-600"></i>
                        <span>Standar Penomoran Dokumen Bukti (KODE-BB-NNN)</span>
                    </h3>
                    <div class="grid grid-cols-2 gap-2 text-[11px] font-mono">
                        <div class="p-2 bg-slate-50 rounded border border-slate-200">
                            <strong>TF-BB-NNN:</strong> Transfer Dana Masuk
                        </div>
                        <div class="p-2 bg-slate-50 rounded border border-slate-200">
                            <strong>CEK-BB-NNN:</strong> Penarikan Tunai Bank
                        </div>
                        <div class="p-2 bg-slate-50 rounded border border-slate-200">
                            <strong>KW-BB-NNN:</strong> Kuitansi Belanja / Upah
                        </div>
                        <div class="p-2 bg-slate-50 rounded border border-slate-200">
                            <strong>NT-BB-NNN:</strong> Nota Konsumsi / ATK
                        </div>
                        <div class="p-2 bg-slate-50 rounded border border-slate-200">
                            <strong>SPL-BB-NNN:</strong> Transaksi SIPLAH
                        </div>
                        <div class="p-2 bg-slate-50 rounded border border-slate-200">
                            <strong>BPN-BB-NNN:</strong> Setor Pajak Pusat (NTPN)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ketentuan Pajak Negeri vs Swasta -->
            <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-200 text-xs space-y-2">
                <div class="font-bold text-emerald-950 flex items-center gap-2">
                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-700"></i>
                    <span>Perbedaan Ketentuan Pajak Sekolah Negeri vs Sekolah Swasta:</span>
                </div>
                <p class="text-emerald-900 leading-relaxed">
                    <strong>Sekolah Negeri:</strong> Bertindak sebagai instansi pemerintah pemungut pajak. Wajib memungut PPN (11%) dan PPh 22 (1.5%) atas pembelian material/barang non-SIPLAH di atas Rp 2.000.000, serta memotong PPh 21 atas honor/upah dan PPh 4 ayat (2) atas jasa konstruksi.
                </p>
                <p class="text-emerald-900 leading-relaxed">
                    <strong>Sekolah Swasta:</strong> Bukan instansi pemerintah pemungut PPN/PPh 22 barang material. Pembelian material dibayarkan penuh ke penyedia PKP, namun tetap memotong PPh 21 honorarium/upah dan PPh 4 ayat (2) / PPh 23 jika bertindak sebagai pemotong.
                </p>
            </div>
        </div>
    </div>

</div>
@endsection
