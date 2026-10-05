@extends('layouts.app')

@section('title', 'Paket Revitalisasi Sekolah')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                <i data-lucide="hard-hat" class="w-4 h-4"></i>
                <span>Proyek Fisik & Sarpras</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Paket Revitalisasi Sekolah
            </h1>
            <p class="text-xs text-slate-500">Monitoring SPK, pagu kontrak bantuan, dan realisasi fisik vs perbendaharaan.</p>
        </div>

        @permission('proyek.create')
        <a href="{{ route('projects.create') }}" 
           class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-emerald-600/20 transition-all">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Tambah Paket Proyek</span>
        </a>
        @endpermission
    </div>

    <!-- Projects List -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @forelse($projects as $p)
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all p-6 space-y-4">
            
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase {{ $p->status === 'selesai' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                        {{ ucfirst($p->status) }}
                    </span>
                    <h3 class="font-bold text-base text-slate-900 mt-1.5">
                        <a href="{{ route('projects.show', $p->id) }}" class="hover:text-emerald-600 transition-colors">
                            {{ $p->title }}
                        </a>
                    </h3>
                    <div class="text-xs text-slate-500 flex items-center gap-1.5 mt-0.5">
                        <i data-lucide="school" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>{{ $p->school->name }} (TA {{ $p->fiscal_year }})</span>
                    </div>
                </div>

                <div class="text-right">
                    <span class="text-[11px] text-slate-400 font-semibold block uppercase">Nilai Pagu Kontrak</span>
                    <span class="text-base font-extrabold text-slate-900 font-mono">@rupiah($p->contract_amount)</span>
                </div>
            </div>

            <!-- Double Progress Bar (Physical vs Financial) -->
            <div class="space-y-3 p-3.5 bg-slate-50 rounded-xl border border-slate-100 text-xs">
                <!-- Physical Progress -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                            <i data-lucide="hammer" class="w-3.5 h-3.5 text-emerald-600"></i>
                            Progres Fisik Lapangan:
                        </span>
                        <span class="font-bold font-mono text-emerald-600">{{ $p->physical_progress }}%</span>
                    </div>
                    <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ min(100, $p->physical_progress) }}%"></div>
                    </div>
                </div>

                <!-- Financial Progress -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                            <i data-lucide="badge-dollar-sign" class="w-3.5 h-3.5 text-blue-600"></i>
                            Serapan Keuangan:
                        </span>
                        <span class="font-bold font-mono text-blue-600">{{ $p->financial_progress }}%</span>
                    </div>
                    <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full" style="width: {{ min(100, $p->financial_progress) }}%"></div>
                    </div>
                </div>
            </div>

            <!-- SPK & Timeline Metadata -->
            <div class="grid grid-cols-2 gap-2 text-xs text-slate-600">
                <div>
                    <span class="text-[10px] text-slate-400 block uppercase font-medium">No. SPK / Penetapan</span>
                    <span class="font-mono font-medium truncate block">{{ $p->spk_number }}</span>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 block uppercase font-medium">Sumber Pembiayaan</span>
                    <span class="truncate block">{{ $p->funding_source }}</span>
                </div>
            </div>

            <!-- Footer Action -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                <a href="{{ route('projects.show', $p->id) }}" class="font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                    <span>Lihat Rincian & RAB</span>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </a>

                <div class="flex items-center gap-1">
                    @permission('proyek.update')
                    <a href="{{ route('projects.edit', $p->id) }}" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                    </a>
                    @endpermission

                    @permission('proyek.delete')
                    <form action="{{ route('projects.destroy', $p->id) }}" method="POST" onsubmit="return confirm('Hapus paket revitalisasi ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </form>
                    @endpermission
                </div>
            </div>

        </div>
        @empty
        <div class="col-span-2 text-center py-12 text-slate-400 bg-white rounded-2xl border border-slate-200 text-sm">
            Belum ada paket revitalisasi terdaftar.
        </div>
        @endforelse
    </div>

</div>
@endsection

