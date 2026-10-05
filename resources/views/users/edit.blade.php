@extends('layouts.app')

@section('title', 'Edit Pengguna')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div>
        <a href="{{ route('users.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1 mb-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Kembali ke Daftar Pengguna</span>
        </a>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
            Edit Pengguna: {{ $user->name }}
        </h1>
    </div>

    @if($errors->any())
    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs space-y-1">
        <div class="font-bold">Periksa input Anda:</div>
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('users.update', $user->id) }}" method="POST" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
                <input type="text" name="name" required value="{{ old('name', $user->name) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Username (Login) *</label>
                <input type="text" name="username" required value="{{ old('username', $user->username) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Email *</label>
                <input type="email" name="email" required value="{{ old('email', $user->email) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Ganti Password (Kosongkan jika tetap)</label>
                <input type="password" name="password" placeholder="Minimal 6 karakter"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Peran (Role) *</label>
                <select name="role_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    @foreach($roles as $r)
                        <option value="{{ $r->id }}" {{ $user->role_id == $r->id ? 'selected' : '' }}>
                            {{ $r->display_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Sekolah yang Dikelola</label>
                <select name="school_id" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    <option value="">-- Semua Sekolah (Akses Kota / Root) --</option>
                    @foreach($schools as $sch)
                        <option value="{{ $sch->id }}" {{ $user->school_id == $sch->id ? 'selected' : '' }}>
                            {{ $sch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor WhatsApp / HP</label>
            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                   class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
        </div>

        <div class="flex items-center gap-2 pt-2">
            <input type="checkbox" id="is_active" name="is_active" value="1" {{ $user->is_active ? 'checked' : '' }}
                   class="rounded border-slate-300 text-purple-600 focus:ring-0">
            <label for="is_active" class="text-xs font-semibold text-slate-700 cursor-pointer">
                Akun Aktif (Dapat login ke aplikasi)
            </label>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
            <a href="{{ route('users.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md transition-all">
                Simpan Perubahan
            </button>
        </div>
    </form>

</div>
@endsection

