@extends('layouts.app')

@section('title', 'Manajemen Pengguna (User Management)')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-purple-600 mb-1">
                <i data-lucide="crown" class="w-4 h-4"></i>
                <span>Modul Administrasi Khusus Root</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Kelola Pengguna Sistem
            </h1>
            <p class="text-xs text-slate-500">Atur akun operator sekolah, bendahara revitalisasi, dan tim pengawas dinas.</p>
        </div>

        <a href="{{ route('users.create') }}" 
           class="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-purple-600/20 transition-all">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span>Tambah User Baru</span>
        </a>
    </div>

    <!-- Filter & Search -->
    <form action="{{ route('users.index') }}" method="GET" class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex flex-wrap items-center gap-3 text-xs">
        <div class="w-full sm:w-auto">
            <select name="role_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="">-- Semua Role --</option>
                @foreach($roles as $r)
                    <option value="{{ $r->id }}" {{ request('role_id') == $r->id ? 'selected' : '' }}>{{ $r->display_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <select name="school_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="">-- Semua Sekolah --</option>
                @foreach($schools as $sch)
                    <option value="{{ $sch->id }}" {{ request('school_id') == $sch->id ? 'selected' : '' }}>{{ $sch->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, email, username..."
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50">
        </div>

        <button type="submit" class="px-4 py-2 bg-slate-800 text-white font-semibold rounded-lg hover:bg-slate-900">
            Cari
        </button>
    </form>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Nama Pengguna</th>
                        <th class="py-3 px-4">Username & Email</th>
                        <th class="py-3 px-4">Peran (Role)</th>
                        <th class="py-3 px-4">Satuan Pendidikan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $u)
                    <tr class="hover:bg-slate-50/70">
                        <td class="py-3 px-4">
                            <div class="font-bold text-slate-900">{{ $u->name }}</div>
                            <div class="text-[10px] text-slate-400">{{ $u->phone ?? '-' }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-mono text-slate-800 font-semibold">{{ $u->username }}</div>
                            <div class="text-slate-500 text-[11px]">{{ $u->email }}</div>
                        </td>
                        <td class="py-3 px-4">
                            @if($u->isRoot())
                                <span class="px-2.5 py-1 rounded-md bg-purple-100 text-purple-800 font-bold text-[10px] border border-purple-200">
                                    👑 Root (Superadmin)
                                </span>
                            @elseif($u->isAdmin())
                                <span class="px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-800 font-bold text-[10px] border border-emerald-200">
                                    ⚡ Admin Revitalisasi
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-md bg-sky-100 text-sky-800 font-bold text-[10px] border border-sky-200">
                                    👁️ User / Viewer
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-medium text-slate-700">
                            {{ $u->school->name ?? 'Semua Sekolah (Tingkat Kota)' }}
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($u->is_active)
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">Aktif</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[10px] font-bold">Nonaktif</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('users.edit', $u->id) }}" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                </a>

                                @if($u->id !== auth()->id())
                                <form action="{{ route('users.destroy', $u->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus user ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400 text-xs">Tidak ada user ditemukan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $users->links() }}
        </div>
        @endif
    </div>

</div>
@endsection

