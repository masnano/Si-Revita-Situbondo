@extends('layouts.app')

@section('title', $project->title)

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Kembali ke Daftar Proyek</span>
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.realisasi', ['project_id' => $project->id]) }}" class="px-3.5 py-2 rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-bold text-xs border border-indigo-200 flex items-center gap-1.5">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>Cetak Laporan Realisasi</span>
            </a>
            @permission('proyek.update')
            <a href="{{ route('projects.edit', $project->id) }}" class="px-3.5 py-2 rounded-xl bg-blue-600 text-white font-bold text-xs hover:bg-blue-700 flex items-center gap-1.5">
                <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                <span>Edit Paket</span>
            </a>
            @endpermission
        </div>
    </div>

    <!-- Project Header Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
            <div>
                <span class="text-[10px] px-2.5 py-1 rounded-full font-bold uppercase {{ $project->status === 'selesai' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                    {{ ucfirst($project->status) }}
                </span>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 mt-2 tracking-tight">
                    {{ $project->title }}
                </h1>
                <p class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                    <span class="font-semibold text-slate-700">{{ $project->school->name }}</span>
                    <span>&bull;</span>
                    <span>Tahun Anggaran {{ $project->fiscal_year }}</span>
                    <span>&bull;</span>
                    <span>Sumber: {{ $project->funding_source }}</span>
                </p>
            </div>

            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-right">
                <span class="text-[11px] font-semibold text-slate-400 uppercase block">Total Nilai Pagu Kontrak</span>
                <span class="text-xl font-black text-slate-900 font-mono">@rupiah($project->contract_amount)</span>
            </div>
        </div>

        <p class="text-xs text-slate-600 leading-relaxed pt-2 border-t border-slate-100">
            {{ $project->description }}
        </p>

        <!-- Progress Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 pt-4 border-t border-slate-100 text-xs">
            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                <span class="text-emerald-700 font-medium">Progres Fisik Lapangan</span>
                <div class="text-lg font-black text-emerald-700 font-mono mt-1">{{ $project->physical_progress }}%</div>
            </div>
            <div class="p-3 bg-blue-50 rounded-xl border border-blue-100">
                <span class="text-blue-700 font-medium">Serapan Keuangan</span>
                <div class="text-lg font-black text-blue-700 font-mono mt-1">{{ $project->financial_progress }}%</div>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-slate-500 font-medium">Realisasi Dana</span>
                <div class="text-lg font-bold text-slate-800 font-mono mt-1">@rupiah($project->total_realization)</div>
            </div>
            <div class="p-3 bg-amber-50 rounded-xl border border-amber-100">
                <span class="text-amber-700 font-medium">Sisa Pagu Anggaran</span>
                <div class="text-lg font-bold text-amber-700 font-mono mt-1">@rupiah($project->remaining_budget)</div>
            </div>
        </div>
    </div>

    <!-- RAB Breakdown Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900">Rencana Anggaran Biaya (RAB) Paket</h3>
                <p class="text-xs text-slate-500">Rincian belanja material, upah tukang, alat, dan honor operasional.</p>
            </div>
            <a href="{{ route('rab.index', ['project_id' => $project->id]) }}" class="text-xs text-emerald-600 font-bold hover:underline">
                Kelola Item RAB &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                        <th class="py-2.5 px-3">Kode Pos</th>
                        <th class="py-2.5 px-3">Kategori</th>
                        <th class="py-2.5 px-4">Uraian Pekerjaan</th>
                        <th class="py-2.5 px-3 text-center">Volume</th>
                        <th class="py-2.5 px-3 text-right">Harga Satuan</th>
                        <th class="py-2.5 px-3 text-right">Pagu Pos (Rp)</th>
                        <th class="py-2.5 px-3 text-right">Realisasi (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($project->budgetItems as $item)
                    <tr class="hover:bg-slate-50/70">
                        <td class="py-2.5 px-3 font-mono text-slate-600">{{ $item->code ?? '-' }}</td>
                        <td class="py-2.5 px-3 font-semibold text-slate-700">{{ $item->category }}</td>
                        <td class="py-2.5 px-4 text-slate-800">{{ $item->name }}</td>
                        <td class="py-2.5 px-3 text-center font-mono">{{ $item->volume }} {{ $item->unit }}</td>
                        <td class="py-2.5 px-3 text-right font-mono">@rupiah($item->unit_price)</td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">@rupiah($item->total_price)</td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700">@rupiah($item->realized_amount)</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-slate-400 text-xs">Belum ada rincian RAB untuk paket ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

