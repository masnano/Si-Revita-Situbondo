@extends('layouts.app')

@section('title', 'Matriks Permission: ' . $role->display_name)

@section('content')
<div class="space-y-6" x-data="{ addPermModal: false }">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <a href="{{ route('roles.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1 mb-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Kembali ke Daftar Peran</span>
            </a>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Pengaturan Hak Akses: <span class="text-purple-700">{{ $role->display_name }}</span>
            </h1>
            <p class="text-xs text-slate-500">Centang izin modul yang diperbolehkan untuk peran ini (create, update, delete, view, export, import, print).</p>
        </div>

        <div class="flex items-center gap-2">
            <button @click="addPermModal = true" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1.5 transition-colors">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                <span>Daftarkan Izin Baru</span>
            </button>
        </div>
    </div>

    <!-- Main Permission Matrix Form -->
    <form action="{{ route('roles.update_permissions', $role->id) }}" method="POST">
        @csrf

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Matriks Modul & Hak Akses</span>
                <span class="text-xs text-slate-500 font-medium">Centang kotak untuk mengaktifkan izin</span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($allPermissions as $module => $perms)
                <div class="p-5 hover:bg-slate-50/50 transition-colors" x-data="{ 
                    checkAll(checked) {
                        $el.closest('.module-row').querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = checked);
                    }
                }" class="module-row">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-purple-600"></span>
                            <h4 class="font-bold text-sm text-slate-900 uppercase tracking-wide">
                                Modul {{ $module }}
                            </h4>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <button type="button" @click="checkAll(true)" class="text-[11px] font-semibold text-emerald-600 hover:underline">
                                Pilih Semua
                            </button>
                            <span class="text-slate-300">|</span>
                            <button type="button" @click="checkAll(false)" class="text-[11px] font-semibold text-rose-600 hover:underline">
                                Kosongkan
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 text-xs">
                        @foreach($perms as $p)
                        @php
                            $isChecked = in_array($p->id, $rolePermissionIds);
                        @endphp
                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 hover:bg-white hover:border-purple-300 cursor-pointer transition-all">
                            <input type="checkbox" name="permissions[]" value="{{ $p->id }}" {{ $isChecked ? 'checked' : '' }}
                                   class="rounded border-slate-300 text-purple-600 focus:ring-purple-500 w-4 h-4">
                            <div>
                                <div class="font-bold text-slate-800 capitalize">{{ $p->action }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $p->name }}</div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Submit Button Bar -->
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <span class="text-xs text-slate-500">Perubahan akan langsung berlaku setelah disimpan.</span>
                <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs shadow-md shadow-purple-600/25 transition-all">
                    Simpan Pengaturan Hak Akses
                </button>
            </div>
        </div>

    </form>

    <!-- Modal Daftarkan Permission Baru -->
    <div x-show="addPermModal" x-transition class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="addPermModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
            <h3 class="font-bold text-sm text-slate-900 mb-4 border-b pb-2">Daftarkan Izin (Permission) Baru</h3>
            
            <form action="{{ route('roles.store_permission') }}" method="POST" class="space-y-4 text-xs">
                @csrf

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nama Modul *</label>
                    <input type="text" name="module" required placeholder="Contoh: aset / spj / opname" class="w-full px-3 py-2 border rounded-xl font-mono">
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Aksi / Tindakan (Action) *</label>
                    <select name="action" required class="w-full px-3 py-2 border rounded-xl bg-slate-50 font-mono">
                        <option value="view">view / show</option>
                        <option value="create">create</option>
                        <option value="update">update</option>
                        <option value="delete">delete</option>
                        <option value="export">export</option>
                        <option value="import">import</option>
                        <option value="print">print</option>
                        <option value="approve">approve</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Judul Tampilan (Display Name) *</label>
                    <input type="text" name="display_name" required placeholder="Contoh: Export Rekapitulasi SPJ" class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Keterangan</label>
                    <input type="text" name="description" placeholder="Uraian izin..." class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="addPermModal = false" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold">Simpan Permission</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

