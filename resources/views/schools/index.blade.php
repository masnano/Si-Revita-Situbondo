@extends('layouts.app')

@section('title', 'Data Sekolah Penerima Revitalisasi')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                <i data-lucide="school" class="w-4 h-4"></i>
                <span>Data Pokok Pendidikan Situbondo</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Sekolah Penerima Revitalisasi
            </h1>
            <p class="text-xs text-slate-500">Profil satuan pendidikan, rekening bank operasional, kepala sekolah & bendahara.</p>
        </div>

        @permission('sekolah.create')
        <a href="{{ route('schools.create') }}" 
           class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-emerald-600/20 transition-all">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Tambah Sekolah</span>
        </a>
        @endpermission
    </div>

    <!-- Search / Filter -->
    <form action="{{ route('schools.index') }}" method="GET" class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex flex-wrap items-center gap-3 text-xs">
        <div class="w-full sm:w-auto">
            <select name="jenjang" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="">-- Semua Jenjang --</option>
                <option value="SD" {{ request('jenjang') == 'SD' ? 'selected' : '' }}>SD</option>
                <option value="SMP" {{ request('jenjang') == 'SMP' ? 'selected' : '' }}>SMP</option>
                <option value="SMA" {{ request('jenjang') == 'SMA' ? 'selected' : '' }}>SMA</option>
                <option value="SMK" {{ request('jenjang') == 'SMK' ? 'selected' : '' }}>SMK</option>
            </select>
        </div>

        <div class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama sekolah, NPSN, kecamatan..."
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:outline-none focus:border-emerald-500">
        </div>

        <button type="submit" class="px-4 py-2 bg-slate-800 text-white font-semibold rounded-lg hover:bg-slate-900">
            Cari
        </button>
    </form>

    <!-- School Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($schools as $sch)
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all p-5 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm shrink-0">
                        {{ $sch->jenjang }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase bg-slate-100 text-slate-600">
                            {{ $sch->status }}
                        </span>
                        <h3 class="font-bold text-sm text-slate-900 mt-1 truncate">{{ $sch->name }}</h3>
                        <p class="text-[11px] font-mono text-slate-500">NPSN: {{ $sch->npsn }}</p>
                    </div>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl text-xs space-y-1.5 text-slate-600">
                    <div class="flex items-center gap-1.5 truncate">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                        <span class="truncate">{{ $sch->address }}, Kec. {{ $sch->kecamatan }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                        <span class="truncate">Kepsek: <strong class="text-slate-800">{{ $sch->principal_name }}</strong></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <i data-lucide="badge-check" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                        <span class="truncate">Bendahara: <strong class="text-slate-800">{{ $sch->treasurer_name }}</strong></span>
                    </div>
                    <div class="flex items-center gap-1.5 text-sky-700 pt-1 border-t border-slate-200/60 font-mono text-[11px]">
                        <i data-lucide="credit-card" class="w-3.5 h-3.5 shrink-0"></i>
                        <span class="truncate">{{ $sch->bank_name }} - {{ $sch->bank_account_number }}</span>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 mt-4 flex items-center justify-between text-xs">
                <span class="text-slate-500 text-[11px] font-medium">
                    {{ $sch->projects_count }} Paket Kegiatan
                </span>
                <div class="flex items-center gap-2">
                    @permission('sekolah.update')
                    <a href="{{ route('schools.edit', $sch->id) }}" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                    </a>
                    @endpermission
                    @permission('sekolah.delete')
                    <form action="{{ route('schools.destroy', $sch->id) }}" method="POST" onsubmit="return confirm('Hapus data sekolah ini?')">
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
        <div class="col-span-3 text-center py-12 text-slate-400 text-sm bg-white rounded-2xl border border-slate-200">
            Belum ada sekolah terdaftar.
        </div>
        @endforelse
    </div>

    @if($schools->hasPages())
    <div class="mt-4">
        {{ $schools->links() }}
    </div>
    @endif

</div>
@endsection

