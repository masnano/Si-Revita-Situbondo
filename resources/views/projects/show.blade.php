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

    <!-- Kurva S & Jadwal Pelaksanaan Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        <i data-lucide="trending-up" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Jadwal Pelaksanaan Pekerjaan & Kurva S</h3>
                        <p class="text-xs text-slate-500">Distribusi bobot kemajuan mingguan untuk kalender belanja BKU swakelola.</p>
                    </div>
                </div>
            </div>

            @if($project->hasKurvaS())
                <div class="flex items-center gap-2">
                    <a href="{{ route('projects.kurva_s.download', $project->id) }}" target="_blank"
                       class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all">
                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        <span>Lihat Berkas Kurva S ({{ strtoupper(pathinfo($project->kurva_s_filename ?? 'pdf', PATHINFO_EXTENSION)) }})</span>
                    </a>
                </div>
            @else
                @permission('proyek.update')
                <a href="{{ route('projects.edit', $project->id) }}"
                   class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1.5 transition-all">
                    <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                    <span>Unggah Kurva S</span>
                </a>
                @endpermission
            @endif
        </div>

        @if($project->hasKurvaS() && !empty($project->kurva_s_data))
            @php
                $kurva = $project->kurva_s_data;
                $totalWeeks = $kurva['total_weeks'] ?? 13;
                $workItems = $kurva['work_items'] ?? [];
                $weeklyWeights = $kurva['weekly_weights'] ?? [];
                $cumulativeWeights = $kurva['cumulative_weights'] ?? [];
            @endphp

            <!-- Info Bar Kurva S -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 text-xs">
                <div>
                    <span class="text-slate-500 text-[11px] block">Nama Berkas:</span>
                    <span class="font-bold text-slate-800">{{ $project->kurva_s_filename ?: 'Kurva S Revitalisasi.pdf' }}</span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">Durasi Pelaksanaan:</span>
                    <span class="font-bold text-emerald-700 font-mono">{{ $totalWeeks }} Minggu ({{ $project->start_date ? $project->start_date->format('d M Y') : '-' }} s/d {{ $project->end_date ? $project->end_date->format('d M Y') : '-' }})</span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">Sinkronisasi RPD:</span>
                    <span class="font-bold text-indigo-700 flex items-center gap-1">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600"></i>
                        <span>Aktif & Terhubung ke Pecah Bahan</span>
                    </span>
                </div>
            </div>

            <!-- Tabel Distribusi Pekerjaan Mingguan -->
            @if(!empty($workItems))
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-100/80 text-slate-700 text-[10px] font-bold uppercase tracking-wider">
                                <th class="py-2.5 px-3 border-b border-slate-200">No</th>
                                <th class="py-2.5 px-3 border-b border-slate-200">Uraian Pekerjaan / Lingkup</th>
                                <th class="py-2.5 px-3 border-b border-slate-200 text-right">Biaya (Rp)</th>
                                <th class="py-2.5 px-3 border-b border-slate-200 text-center">Bobot</th>
                                <th class="py-2.5 px-3 border-b border-slate-200 text-center">Minggu Aktif</th>
                                @for($w = 1; $w <= $totalWeeks; $w++)
                                    <th class="py-2 px-1.5 border-b border-slate-200 text-center font-mono text-[9px] min-w-[26px]">M{{ $w }}</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[11px]">
                            @foreach($workItems as $idx => $wi)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="py-2 px-3 text-slate-500 font-mono">{{ $idx + 1 }}</td>
                                    <td class="py-2 px-3 font-semibold text-slate-800">
                                        {{ $wi['name'] }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono text-slate-700">
                                        {{ $wi['cost'] > 0 ? number_format($wi['cost'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="py-2 px-3 text-center font-bold text-slate-700 font-mono">
                                        {{ $wi['weight'] > 0 ? number_format($wi['weight'], 2, ',', '.') . '%' : '-' }}
                                    </td>
                                    <td class="py-2 px-3 text-center font-semibold text-emerald-700">
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-[10px] border border-emerald-200">
                                            M{{ $wi['start_week'] }} - M{{ $wi['end_week'] }}
                                        </span>
                                    </td>
                                    @for($w = 1; $w <= $totalWeeks; $w++)
                                        @php
                                            $val = $wi['weekly_distribution'][$w] ?? null;
                                            $isActive = in_array($w, $wi['weeks'] ?? []);
                                        @endphp
                                        <td class="py-1 px-1 text-center font-mono text-[9px] {{ $isActive ? 'bg-emerald-50/70 text-emerald-800 font-bold' : 'text-slate-300' }}">
                                            @if($val !== null && $val > 0)
                                                {{ round($val, 1) }}%
                                            @elseif($isActive)
                                                &bull;
                                            @else
                                                -
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                            @endforeach
                            <!-- Baris Total Rencana -->
                            <tr class="bg-slate-50 font-bold text-[10px] text-slate-800 border-t-2 border-slate-300">
                                <td colspan="4" class="py-2 px-3 text-right uppercase">Rencana Mingguan (%)</td>
                                <td class="text-center font-mono">100%</td>
                                @for($w = 1; $w <= $totalWeeks; $w++)
                                    <td class="py-2 px-1 text-center font-mono text-emerald-700 text-[9px]">
                                        {{ isset($weeklyWeights[$w]) && $weeklyWeights[$w] > 0 ? number_format($weeklyWeights[$w], 2, ',', '.') . '%' : '0%' }}
                                    </td>
                                @endfor
                            </tr>
                            <!-- Baris Akumulasi Rencana -->
                            <tr class="bg-emerald-50/40 font-bold text-[10px] text-emerald-900 border-t border-slate-200">
                                <td colspan="4" class="py-2 px-3 text-right uppercase">Akumulasi Rencana (%)</td>
                                <td class="text-center font-mono">100%</td>
                                @for($w = 1; $w <= $totalWeeks; $w++)
                                    <td class="py-2 px-1 text-center font-mono text-emerald-800 text-[9px]">
                                        {{ isset($cumulativeWeights[$w]) ? number_format($cumulativeWeights[$w], 1, ',', '.') . '%' : '-' }}
                                    </td>
                                @endfor
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex items-center justify-between text-xs pt-1">
                <span class="text-slate-500 text-[11px]">
                    * Jadwal ini otomatis dijadikan rujukan tanggal belanja bahan, alat, dan upah tukang mingguan pada BKU.
                </span>
                <a href="{{ route('rpd.index', ['project_id' => $project->id]) }}" class="font-bold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                    <span>Buka RPD & Kalender BKU Proyek Ini</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        @else
            <div class="p-6 text-center border-2 border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                <i data-lucide="file-question" class="w-8 h-8 text-slate-400 mx-auto mb-2"></i>
                <h4 class="text-xs font-bold text-slate-700">Berkas Kurva S Belum Diunggah</h4>
                <p class="text-[11px] text-slate-500 mt-1 max-w-md mx-auto">
                    Unggah berkas Kurva S (.pdf atau .xlsx) melalui tombol Edit Paket untuk memetakan penanggalan belanja BKU secara otomatis sesuai target mingguan lapangan.
                </p>
                @permission('proyek.update')
                <div class="mt-3">
                    <a href="{{ route('projects.edit', $project->id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs">
                        <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                        <span>Unggah Kurva S Sekarang</span>
                    </a>
                </div>
                @endpermission
            </div>
        @endif
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

