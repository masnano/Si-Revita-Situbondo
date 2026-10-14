@extends('layouts.app')

@php
    $bulanIndo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $currentMonth = $month ?? date('n');
    $currentYear = $year ?? date('Y');
    $currentMonthName = $namaBulan ?? ($bulanIndo[(int)$currentMonth] ?? \Carbon\Carbon::create(null, (int)$currentMonth, 1)->translatedFormat('F'));
    $schoolCleanName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $selectedSchool->name ?? 'Sekolah');
    $pageTitle = "{$currentMonthName} {$currentYear} - Laporan Realisasi Anggaran - {$schoolCleanName}";
@endphp

@section('title', $pageTitle)

@section('content')
<div class="space-y-6">

    <!-- Controls (No Print) -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4 no-print">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 mb-1">
                <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                <span>Laporan Komparasi Pagu RAB vs Realisasi</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Laporan Realisasi Anggaran Revitalisasi
            </h1>
            <p class="text-xs text-slate-500">Evaluasi penyerapan dana per rincian pos belanja dan mata anggaran.</p>
        </div>

        <form action="{{ route('reports.realisasi') }}" method="GET" class="flex flex-wrap items-center gap-2">
            @role('root,user')
            <select name="school_id" onchange="this.form.submit()" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800">
                @foreach($schools as $sch)
                    <option value="{{ $sch->id }}" {{ $selectedSchool?->id == $sch->id ? 'selected' : '' }}>
                        {{ $sch->name }}
                    </option>
                @endforeach
            </select>
            @endrole

            @if($projects->count() > 1)
            <select name="project_id" onchange="this.form.submit()" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800">
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" {{ $selectedProject?->id == $p->id ? 'selected' : '' }}>
                        {{ $p->title }}
                    </option>
                @endforeach
            </select>
            @endif

            @if(!empty($availableMenus))
            <select name="menu_pekerjaan" onchange="this.form.submit()" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800">
                <option value="all" {{ ($selectedMenu ?? 'all') === 'all' ? 'selected' : '' }}>Semua Menu Pekerjaan</option>
                @foreach($availableMenus as $m)
                    <option value="{{ $m }}" {{ ($selectedMenu ?? '') === $m ? 'selected' : '' }}>{{ $m }}</option>
                @endforeach
            </select>
            @endif

            <select name="month" onchange="this.form.submit()" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ ($currentMonth ?? date('n')) == $m ? 'selected' : '' }}>{{ $bulanIndo[$m] }}</option>
                @endfor
            </select>

            <select name="year" onchange="this.form.submit()" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800">
                @for($y = date('Y') - 1; $y <= date('Y') + 2; $y++)
                    <option value="{{ $y }}" {{ ($currentYear ?? date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>

            <button type="button" onclick="window.print()" 
                    class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs flex items-center gap-1.5 transition-colors shadow-sm">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>Cetak Realisasi</span>
            </button>
        </form>
    </div>

    <!-- Summary KPI (No Print) -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 no-print">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-500 uppercase">Total Pagu Kontrak</span>
            <div class="text-base font-bold text-slate-900 mt-1">@rupiah($totalPagu)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-emerald-600 uppercase">Total Realisasi Belanja</span>
            <div class="text-base font-bold text-emerald-600 mt-1">@rupiah($totalRealisasi)</div>
        </div>
        <div class="{{ $sisaTotal < 0 ? 'bg-rose-50 border-rose-300' : 'bg-white border-slate-200' }} p-4 rounded-xl border shadow-sm">
            <span class="text-[11px] font-semibold {{ $sisaTotal < 0 ? 'text-rose-600' : 'text-amber-600' }} uppercase">
                {{ $sisaTotal < 0 ? 'Defisit / Melebihi Pagu' : 'Sisa Anggaran Belum Terserap' }}
            </span>
            <div class="text-base font-bold {{ $sisaTotal < 0 ? 'text-rose-700' : 'text-amber-600' }} mt-1">
                {{ $sisaTotal < 0 ? '-' : '' }}@rupiah(abs($sisaTotal))
            </div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold {{ $persenTotal > 100 ? 'text-rose-600' : 'text-indigo-600' }} uppercase">Persentase Serapan Keuangan</span>
            <div class="text-base font-black {{ $persenTotal > 100 ? 'text-rose-600' : 'text-indigo-600' }} mt-1">{{ $persenTotal }}%</div>
        </div>
    </div>

    @if($sisaTotal < 0)
    <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-3 no-print">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
        <div class="text-xs text-rose-900">
            <strong class="font-bold">Peringatan: Total Realisasi Belanja Melebihi Pagu Kontrak sebesar @rupiah(abs($sisaTotal)) ({{ $persenTotal }}%)!</strong>
            <p class="mt-1 text-slate-700">Penyebab umum: (1) Dokumen RPD diposting lebih dari 1 kali ke BKU (duplikasi transaksi), (2) Nominal transaksi belanja diinput tanpa memilih kolom termin target, atau (3) Nilai Pagu Kontrak Proyek di master data baru mencakup sebagian termin dan belum disesuaikan dengan nilai total kontrak.</p>
        </div>
    </div>
    @endif

    <!-- Printable Official Sheet -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-10 print-card">
        
        <div class="border-b-2 border-slate-900 pb-4 mb-6 flex items-center justify-between gap-4">
            <img src="{{ asset('images/logo-kabupaten.png') }}" alt="Logo Kabupaten Situbondo" class="w-16 h-16 sm:w-20 sm:h-20 object-contain shrink-0">
            <div class="flex-1 text-center">
                <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-800">Pemerintah Kabupaten Situbondo</h3>
                <h2 class="text-sm sm:text-base font-black uppercase tracking-wider text-slate-900">Dinas Pendidikan dan Kebudayaan</h2>
                <h1 class="text-base sm:text-lg font-black uppercase tracking-wider text-indigo-800 mt-0.5">
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
                Laporan Realisasi Penggunaan Dana Revitalisasi Sekolah
            </h2>
            <p class="text-xs font-bold text-slate-700 mt-1">
                Paket: {{ $selectedProject->title ?? 'Revitalisasi Sekolah' }}
            </p>
            <p class="text-xs font-bold text-slate-700 mt-0.5 uppercase">
                Periode: s.d. Bulan <span class="text-slate-900">{{ $currentMonthName }} {{ $currentYear }}</span>
            </p>
            <p class="text-[11px] text-slate-500 font-mono mt-0.5">
                No. SPK: {{ $selectedProject->spk_number ?? '-' }} | Sumber Dana: {{ $selectedProject->funding_source ?? '-' }}
            </p>
            <div class="inline-flex items-center gap-2 mt-2 px-3 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-900 text-xs font-semibold">
                <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
                <span>Rekening Penampung: <strong>{{ $selectedSchool->bank_name ?? '-' }}</strong> &mdash; No. Rek: <strong class="font-mono">{{ $selectedSchool->bank_account_number ?? '-' }}</strong> a.n <strong>{{ $selectedSchool->bank_account_holder ?? '-' }}</strong></span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="report-table w-full text-left text-xs border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-800 font-bold uppercase text-[10px] text-center">
                        <th class="py-2.5 px-2 border border-slate-300 w-10">No</th>
                        <th class="py-2.5 px-3 border border-slate-300 w-24">Kode Pos</th>
                        <th class="py-2.5 px-3 border border-slate-300 w-32">Kategori</th>
                        <th class="py-2.5 px-4 border border-slate-300">Uraian Pekerjaan / Belanja</th>
                        <th class="py-2.5 px-2 border border-slate-300 text-center w-20">Volume</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-28">Pagu RAB (Rp)</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-28">Realisasi (Rp)</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-28">Sisa (Rp)</th>
                        <th class="py-2.5 px-2 border border-slate-300 text-center w-16">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @php $no = 1; @endphp
                    @forelse($budgetItems as $item)
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-2 px-2 border border-slate-300 text-center">{{ $no++ }}</td>
                        <td class="py-2 px-3 border border-slate-300 font-mono text-center">{{ $item['code'] ?? '-' }}</td>
                        <td class="py-2 px-3 border border-slate-300 font-semibold text-slate-700">{{ $item['category'] }}</td>
                        <td class="py-2 px-4 border border-slate-300 text-slate-800">
                            @if(!empty($item['menu_pekerjaan']) && ($selectedMenu ?? 'all') === 'all')
                                <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 mr-1 mb-0.5">{{ $item['menu_pekerjaan'] }}</span>
                            @endif
                            {{ $item['name'] }}
                        </td>
                        <td class="py-2 px-2 border border-slate-300 text-center font-mono">{{ $item['volume'] }} {{ $item['unit'] }}</td>
                        <td class="py-2 px-3 border border-slate-300 text-right font-mono">{{ number_format($item['pagu'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 border border-slate-300 text-right font-mono text-emerald-700 font-bold">{{ number_format($item['realisasi'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 border border-slate-300 text-right font-mono text-amber-700 font-semibold">{{ number_format($item['sisa'], 0, ',', '.') }}</td>
                        <td class="py-2 px-2 border border-slate-300 text-center font-mono font-bold">{{ $item['persen'] }}%</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-6 text-center text-slate-400 italic border border-slate-300">
                            Belum ada rincian pos RAB pada paket revitalisasi ini.
                        </td>
                    </tr>
                    @endforelse

                    <tr class="bg-slate-100 font-bold text-slate-900">
                        <td colspan="5" class="py-2.5 px-4 border border-slate-300 text-right uppercase">
                            Total Anggaran & Realisasi
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono font-black">
                            {{ number_format($totalPagu, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono font-black text-emerald-800">
                            {{ number_format($totalRealisasi, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono font-black text-amber-800">
                            {{ number_format($sisaTotal, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-2 border border-slate-300 text-center font-mono font-black text-indigo-700">
                            {{ $persenTotal }}%
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-12 grid grid-cols-2 gap-8 text-center text-xs text-slate-900 signature-block avoid-break">
            <div>
                <p>Mengetahui,</p>
                <p class="font-bold">Kepala Sekolah</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->principal_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->principal_nip ?? '...................................' }}</p>
            </div>
            <div>
                <p>Situbondo, {{ \Carbon\Carbon::create($currentYear, (int)$currentMonth, 1)->endOfMonth()->translatedFormat('d F Y') }}</p>
                <p class="font-bold">Bendahara Pengeluaran Revitalisasi</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->treasurer_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->treasurer_nip ?? '...................................' }}</p>
            </div>
        </div>

    </div>

</div>
@endsection

