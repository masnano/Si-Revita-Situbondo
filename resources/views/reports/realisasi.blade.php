@extends('layouts.app')

@section('title', 'Laporan Realisasi Anggaran Revitalisasi')

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
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-amber-600 uppercase">Sisa Anggaran Belum Terserap</span>
            <div class="text-base font-bold text-amber-600 mt-1">@rupiah($sisaTotal)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-indigo-600 uppercase">Persentase Serapan Keuangan</span>
            <div class="text-base font-black text-indigo-600 mt-1">{{ $persenTotal }}%</div>
        </div>
    </div>

    <!-- Printable Official Sheet -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-10 print-card">
        
        <div class="border-b-2 border-slate-900 pb-4 mb-6 flex items-center justify-between gap-4">
            <img src="{{ asset('images/logo.png') }}" alt="Logo Si Revita Situbondo" class="w-16 h-16 sm:w-20 sm:h-20 object-contain shrink-0">
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
            <h2 class="text-lg font-extrabold uppercase text-slate-900 underline decoration-slate-400 underline-offset-4">
                Laporan Realisasi Penggunaan Dana Revitalisasi Sekolah
            </h2>
            <p class="text-xs font-semibold text-slate-700 mt-1">
                Paket: {{ $selectedProject->title ?? 'Revitalisasi Sekolah' }}
            </p>
            <p class="text-[11px] text-slate-500 font-mono mt-0.5">
                No. SPK: {{ $selectedProject->spk_number ?? '-' }} | Sumber Dana: {{ $selectedProject->funding_source ?? '-' }}
            </p>
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
                        <td class="py-2 px-4 border border-slate-300 text-slate-800">{{ $item['name'] }}</td>
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

        <div class="mt-12 grid grid-cols-2 gap-8 text-center text-xs text-slate-900">
            <div>
                <p>Mengetahui,</p>
                <p class="font-bold">Kepala Sekolah</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->principal_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->principal_nip ?? '...................................' }}</p>
            </div>
            <div>
                <p>Situbondo, {{ date('d F Y') }}</p>
                <p class="font-bold">Bendahara Pengeluaran Revitalisasi</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->treasurer_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->treasurer_nip ?? '...................................' }}</p>
            </div>
        </div>

    </div>

</div>
@endsection

