@extends('layouts.app')

@php
    $bulanIndo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $hasSpecificMonth = isset($month) && $month !== 'all' && is_numeric($month);
    $currentMonthName = $hasSpecificMonth ? ($bulanIndo[(int)$month] ?? '') : 'Semua Bulan';
    $schoolCleanName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $selectedSchool->name ?? 'Sekolah');
    $pageTitle = "Audit Kepatuhan SPJ AI - {$selectedSchool->name} - {$year}";
@endphp

@section('title', $pageTitle)

@section('content')
<div class="space-y-6">

    <!-- Header & Filter Form (No Print) -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4 no-print">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-purple-600 mb-1">
                <i data-lucide="sparkles" class="w-4 h-4 text-amber-500"></i>
                <span>Asisten Audit Cerdas Google Gemini AI</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Audit Kepatuhan Keuangan & Deteksi Anomali SPJ
            </h1>
            <p class="text-xs text-slate-500">Pemeriksaan otomatis kepatuhan perpajakan (NTPN), penanggalan bukti transaksi, dan kesiapan audit Inspektorat/BPK.</p>
        </div>

        <form action="{{ route('reports.ai_audit') }}" method="GET" class="flex flex-wrap items-center gap-2">
            @role('root,user')
            <select name="school_id" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-purple-500">
                @foreach($schools as $sch)
                    <option value="{{ $sch->id }}" {{ $selectedSchool?->id == $sch->id ? 'selected' : '' }}>
                        {{ $sch->name }}
                    </option>
                @endforeach
            </select>
            @endrole

            <select name="month" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-purple-500">
                <option value="all" {{ ($month ?? 'all') == 'all' ? 'selected' : '' }}>Semua Bulan</option>
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ ($month ?? '') == (string)$m ? 'selected' : '' }}>{{ $bulanIndo[$m] }}</option>
                @endfor
            </select>

            <select name="year" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-purple-500">
                @for($y = date('Y') - 1; $y <= date('Y') + 2; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>

            <button type="submit" class="px-4 py-2 rounded-xl bg-purple-700 text-white font-semibold text-xs hover:bg-purple-800 transition-colors">
                Analisis Ulang AI
            </button>

            <button type="button" onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-800 text-white font-semibold text-xs flex items-center gap-1.5 transition-colors shadow-sm">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>Cetak Laporan Audit</span>
            </button>
        </form>
    </div>

    <!-- KPI & Score Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 no-print">
        <div class="bg-gradient-to-br from-purple-900 to-indigo-900 text-white p-5 rounded-2xl shadow-sm border border-purple-800">
            <span class="text-[11px] font-semibold text-purple-200 uppercase tracking-wider">Skor Kepatuhan SPJ</span>
            <div class="text-3xl font-black mt-1 flex items-baseline gap-1">
                <span>{{ $auditResult['compliance_score'] }}</span>
                <span class="text-sm font-normal text-purple-300">/ 100</span>
            </div>
            <div class="text-xs text-purple-200 mt-2 flex items-center gap-1.5">
                @if($auditResult['compliance_score'] >= 90)
                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/40 text-[10px]">Amat Tertib</span>
                @elseif($auditResult['compliance_score'] >= 75)
                    <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-bold border border-amber-500/40 text-[10px]">Cukup Baik</span>
                @else
                    <span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 font-bold border border-rose-500/40 text-[10px]">Perlu Perbaikan</span>
                @endif
                <span class="text-[11px]">Sesuai Juknis DAK</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Predikat Opini Audit</span>
            <div class="text-sm font-extrabold mt-2 leading-tight {{ $auditResult['compliance_score'] >= 90 ? 'text-emerald-700' : ($auditResult['compliance_score'] >= 70 ? 'text-amber-700' : 'text-rose-700') }}">
                {{ $auditResult['opinion'] }}
            </div>
            <div class="text-[11px] text-slate-500 mt-2">Standar pemeriksaan Inspektorat Daerah & BPK RI</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Ruang Lingkup Pemeriksaan</span>
            <div class="text-xl font-extrabold text-slate-900 mt-1">
                {{ $auditResult['total_transactions'] }} <span class="text-xs font-normal text-slate-500">Transaksi</span>
            </div>
            <div class="text-[11px] text-slate-500 mt-2">
                {{ $auditResult['tax_transactions_count'] }} transaksi dikenakan kewajiban perpajakan
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Penyetoran Pajak</span>
            <div class="text-xl font-extrabold text-emerald-700 mt-1">
                @rupiah($auditResult['total_tax_amount'])
            </div>
            <div class="text-[11px] text-emerald-600 mt-2 flex items-center gap-1 font-semibold">
                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                <span>Tercatat pada Kas Negara</span>
            </div>
        </div>
    </div>

    <!-- AI Narrative & Audit Findings -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6 print-card">

        <!-- Kop Surat Resmi Cetak -->
        <div class="border-b-2 border-slate-900 pb-4 mb-6 flex items-center justify-between gap-4">
            <img src="{{ asset('images/logo-kabupaten.png') }}" alt="Logo Kabupaten Situbondo" class="w-16 h-16 sm:w-20 sm:h-20 object-contain shrink-0">
            <div class="flex-1 text-center">
                <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-800">Pemerintah Kabupaten Situbondo</h3>
                <h2 class="text-sm sm:text-base font-black uppercase tracking-wider text-slate-900">Dinas Pendidikan dan Kebudayaan</h2>
                <h1 class="text-base sm:text-lg font-black uppercase tracking-wider text-purple-900 mt-0.5">
                    {{ $selectedSchool->name ?? 'SEKOLAH KABUPATEN SITUBONDO' }}
                </h1>
                <p class="text-[11px] sm:text-xs text-slate-600 mt-0.5">
                    {{ $selectedSchool->address ?? '' }}, Kec. {{ $selectedSchool->kecamatan ?? '' }}, Kab. Situbondo — NPSN: {{ $selectedSchool->npsn ?? '' }}
                </p>
            </div>
            <div class="w-16 sm:w-20 shrink-0 hidden sm:block"></div>
        </div>

        <div class="text-center mb-6">
            <h2 class="text-lg font-black uppercase text-slate-900 tracking-wider underline decoration-slate-400 underline-offset-4">
                Lembar Hasil Audit Cerdas & Kepatuhan SPJ Keuangan
            </h2>
            <p class="text-xs font-bold text-slate-700 mt-1 uppercase">
                Periode: <span class="text-slate-900">{{ $currentMonthName }} {{ $year }}</span> &bull; 
                Kegiatan: <span class="text-slate-900">Program Revitalisasi Sarana Prasarana Sekolah</span>
            </p>
        </div>

        <!-- AI Executive Summary Box -->
        <div class="p-5 rounded-2xl bg-purple-50/70 border border-purple-200 space-y-3">
            <div class="flex items-center gap-2 text-xs font-bold text-purple-900 uppercase tracking-wider">
                <i data-lucide="bot" class="w-4 h-4 text-purple-700"></i>
                <span>Telaah & Opini Audit AI (Generative Intelligence Assessment)</span>
            </div>
            <div class="text-xs text-slate-800 leading-relaxed whitespace-pre-line font-serif">
                {{ $auditResult['ai_analysis'] }}
            </div>
        </div>

        <!-- Detailed Audit Checklist Matrix -->
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="clipboard-check" class="w-4 h-4 text-emerald-600"></i>
                <span>Matriks Uji Kepatuhan Administratif Perpajakan & Pembukuan</span>
            </h3>

            <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
                <table class="w-full text-left divide-y divide-slate-200">
                    <thead class="bg-slate-50 font-bold text-slate-700 uppercase text-[10px]">
                        <tr>
                            <th class="py-2.5 px-3 w-10 text-center">No</th>
                            <th class="py-2.5 px-4">Aspek Audit & Pengujian Keuangan</th>
                            <th class="py-2.5 px-4 w-48 text-center">Standar / Kaidah Pengujian</th>
                            <th class="py-2.5 px-4 w-32 text-center">Hasil Uji Sistem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        <tr>
                            <td class="py-2.5 px-3 text-center font-bold">1</td>
                            <td class="py-2.5 px-4">
                                <div class="font-bold">Kelunasan Penyetoran Pajak (NTPN Valid)</div>
                                <div class="text-[11px] text-slate-500">Memastikan seluruh pungutan PPN, PPh 21, PPh 22, dan PPh 4(2) telah disetorkan lunas ke Kas Negara.</div>
                            </td>
                            <td class="py-2.5 px-4 text-center text-[11px] text-slate-600">Saldo Pajak = Rp 0 (Nihil)</td>
                            <td class="py-2.5 px-4 text-center">
                                @if(collect($auditResult['anomalies'])->where('type', 'pajak_belum_disetor')->isEmpty())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">
                                        <i data-lucide="check" class="w-3 h-3"></i> Lunas 100%
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 font-bold text-[10px]">
                                        <i data-lucide="alert-circle" class="w-3 h-3"></i> Ada Tunggakan
                                    </span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td class="py-2.5 px-3 text-center font-bold">2</td>
                            <td class="py-2.5 px-4">
                                <div class="font-bold">Keabsahan Hari Kerja Transaksi</div>
                                <div class="text-[11px] text-slate-500">Memastikan pembukuan transaksi BKU tidak dijadwalkan pada hari libur nasional / hari Minggu.</div>
                            </td>
                            <td class="py-2.5 px-4 text-center text-[11px] text-slate-600">Senin s.d. Sabtu</td>
                            <td class="py-2.5 px-4 text-center">
                                @if(collect($auditResult['anomalies'])->where('type', 'transaksi_hari_minggu')->isEmpty())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">
                                        <i data-lucide="check" class="w-3 h-3"></i> Tertib Hari Kerja
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 font-bold text-[10px]">
                                        <i data-lucide="alert-triangle" class="w-3 h-3"></i> Perlu Revisi
                                    </span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td class="py-2.5 px-3 text-center font-bold">3</td>
                            <td class="py-2.5 px-4">
                                <div class="font-bold">Kronologi Tanggal Penyetoran Pajak</div>
                                <div class="text-[11px] text-slate-500">Memastikan tanggal penyetoran pajak ke Bank Persepsi sinkron, tidak mendahului tanggal transaksi pembelian.</div>
                            </td>
                            <td class="py-2.5 px-4 text-center text-[11px] text-slate-600">Tgl Setor &ge; Tgl Belanja</td>
                            <td class="py-2.5 px-4 text-center">
                                @if(collect($auditResult['anomalies'])->where('type', 'tanggal_pajak_janggal')->isEmpty())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">
                                        <i data-lucide="check" class="w-3 h-3"></i> Kronologis Valid
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 font-bold text-[10px]">
                                        <i data-lucide="alert-circle" class="w-3 h-3"></i> Tgl Janggal
                                    </span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td class="py-2.5 px-3 text-center font-bold">4</td>
                            <td class="py-2.5 px-4">
                                <div class="font-bold">Kepatuhan Operasional Loket Bank Persepsi</div>
                                <div class="text-[11px] text-slate-500">Memastikan tanggal pencatatan resi NTPN tidak tercatat pada hari Minggu.</div>
                            </td>
                            <td class="py-2.5 px-4 text-center text-[11px] text-slate-600">Hari Layanan Bank</td>
                            <td class="py-2.5 px-4 text-center">
                                @if(collect($auditResult['anomalies'])->where('type', 'setor_pajak_hari_minggu')->isEmpty())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">
                                        <i data-lucide="check" class="w-3 h-3"></i> Patuh Layanan
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 font-bold text-[10px]">
                                        <i data-lucide="alert-triangle" class="w-3 h-3"></i> Hari Minggu
                                    </span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if(!empty($auditResult['anomalies']))
        <!-- Action Remediation Recommendation -->
        <div class="p-4 bg-amber-50 rounded-xl border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
            <div>
                <h4 class="font-bold text-amber-900 text-xs flex items-center gap-1.5">
                    <i data-lucide="sparkles" class="w-4 h-4 text-amber-600"></i>
                    <span>Solusi Cerdas Sekali Klik: Setor Pajak Otomatis AI</span>
                </h4>
                <p class="text-[11px] text-amber-800 mt-0.5">
                    Sistem dapat langsung memperbaiki seluruh ketidaksesuaian pajak dengan men-generate Nomor NTPN 16 digit resmi dan menyinkronkan tanggal penyetoran secara otomatis.
                </p>
            </div>
            @role('root,admin')
            <form action="{{ route('reports.bp.auto_setor') }}" method="POST">
                @csrf
                <input type="hidden" name="school_id" value="{{ $selectedSchool?->id }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <button type="submit" 
                        data-confirm="Terapkan perbaikan otomatis AI untuk seluruh transaksi pajak pada periode ini?"
                        class="px-4 py-2 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-bold text-xs whitespace-nowrap transition-colors shadow-sm">
                    ⚡ Perbaiki Otomatis Sekarang
                </button>
            </form>
            @endrole
        </div>
        @endif

        <!-- Signatures (Official) -->
        <div class="mt-12 grid grid-cols-2 gap-8 text-center text-xs text-slate-900 signature-block avoid-break">
            <div>
                <p>Mengetahui,</p>
                <p class="font-bold">Kepala Sekolah</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->principal_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->principal_nip ?? '...................................' }}</p>
            </div>
            <div>
                <p>Situbondo, {{ \Carbon\Carbon::create($year, 12, 31)->translatedFormat('d F Y') }}</p>
                <p class="font-bold">Bendahara Pengeluaran Revitalisasi</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->treasurer_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->treasurer_nip ?? '...................................' }}</p>
            </div>
        </div>

    </div>

</div>
@endsection

