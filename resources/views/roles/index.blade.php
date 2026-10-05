@extends('layouts.app')

@section('title', 'Peran & Hak Akses (Role & Permissions)')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-purple-600 mb-1">
                <i data-lucide="sliders" class="w-4 h-4"></i>
                <span>Kontrol Hak Akses Granular</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Role & Matriks Permission
            </h1>
            <p class="text-xs text-slate-500">Root dapat mengatur permission per modul (create, update, delete, show, export, import, print) untuk setiap peran.</p>
        </div>
    </div>

    <!-- Role Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($roles as $role)
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase
                        {{ $role->name === 'root' ? 'bg-purple-100 text-purple-800 border border-purple-200' : '' }}
                        {{ $role->name === 'admin' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : '' }}
                        {{ $role->name === 'user' ? 'bg-sky-100 text-sky-800 border border-sky-200' : '' }}
                    ">
                        {{ $role->name }}
                    </span>
                    <span class="text-xs font-semibold text-slate-500 font-mono">
                        {{ $role->users->count() }} Pengguna
                    </span>
                </div>

                <h3 class="text-base font-bold text-slate-900">{{ $role->display_name }}</h3>
                <p class="text-xs text-slate-600 leading-relaxed">{{ $role->description }}</p>

                <div class="p-3 bg-slate-50 rounded-xl text-xs space-y-1 border border-slate-100">
                    <div class="flex justify-between text-slate-600">
                        <span>Total Izin Aktif:</span>
                        <strong class="font-mono text-slate-900">
                            {{ $role->name === 'root' ? 'Semua (Bypass)' : $role->permissions->count() . ' Izin' }}
                        </strong>
                    </div>
                </div>
            </div>

            <div class="pt-5 border-t border-slate-100 mt-4">
                @if($role->name === 'root')
                    <div class="text-center py-2 text-xs font-bold text-purple-700 bg-purple-50 rounded-xl border border-purple-200">
                        👑 Akses Penuh ke Semua Fitur
                    </div>
                @else
                    <a href="{{ route('roles.show', $role->id) }}" 
                       class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs flex items-center justify-center gap-2 transition-colors">
                        <i data-lucide="sliders" class="w-4 h-4"></i>
                        <span>Atur Permission Matrix &rarr;</span>
                    </a>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    <!-- Modules & Available Permissions Quick Reference -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4">
        <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
            <i data-lucide="shield" class="w-4 h-4 text-emerald-600"></i>
            Daftar Modul & Kemampuan Granular dalam Sistem
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            @foreach($permissions as $mod => $perms)
            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                <div class="font-bold text-slate-800 uppercase text-[11px] mb-2 flex items-center justify-between border-b pb-1">
                    <span>Modul {{ $mod }}</span>
                    <span class="text-[10px] text-slate-400 font-mono">{{ $perms->count() }} action</span>
                </div>
                <div class="space-y-1">
                    @foreach($perms as $p)
                        <div class="flex items-center gap-1.5 text-slate-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span class="font-mono text-[11px]">{{ $p->action }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>
@endsection

