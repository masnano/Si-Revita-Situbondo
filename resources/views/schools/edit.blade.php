@extends('layouts.app')

@section('title', 'Edit Data Sekolah')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <a href="{{ route('schools.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1 mb-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Kembali ke Daftar Sekolah</span>
        </a>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
            Edit Profil {{ $school->name }}
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

    <form action="{{ route('schools.update', $school->id) }}" method="POST" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">NPSN *</label>
                <input type="text" name="npsn" required value="{{ old('npsn', $school->npsn) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Satuan Pendidikan *</label>
                <input type="text" name="name" required value="{{ old('name', $school->name) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Jenjang Pendidikan *</label>
                <select name="jenjang" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    <option value="SD" {{ $school->jenjang == 'SD' ? 'selected' : '' }}>SD</option>
                    <option value="SMP" {{ $school->jenjang == 'SMP' ? 'selected' : '' }}>SMP</option>
                    <option value="SMA" {{ $school->jenjang == 'SMA' ? 'selected' : '' }}>SMA</option>
                    <option value="SMK" {{ $school->jenjang == 'SMK' ? 'selected' : '' }}>SMK</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Status Satuan Pendidikan *</label>
                <select name="status" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    <option value="Negeri" {{ $school->status == 'Negeri' ? 'selected' : '' }}>Negeri</option>
                    <option value="Swasta" {{ $school->status == 'Swasta' ? 'selected' : '' }}>Swasta</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Lengkap *</label>
            <input type="text" name="address" required value="{{ old('address', $school->address) }}"
                   class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kecamatan di Situbondo *</label>
                <input type="text" name="kecamatan" required value="{{ old('kecamatan', $school->kecamatan) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kabupaten *</label>
                <input type="text" name="kabupaten" required value="{{ old('kabupaten', $school->kabupaten) }}" readonly
                       class="w-full px-3 py-2 border border-slate-200 bg-slate-50 rounded-xl text-xs text-slate-600">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kepala Sekolah *</label>
                <input type="text" name="principal_name" required value="{{ old('principal_name', $school->principal_name) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">NIP Kepala Sekolah</label>
                <input type="text" name="principal_nip" value="{{ old('principal_nip', $school->principal_nip) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Bendahara Revitalisasi *</label>
                <input type="text" name="treasurer_name" required value="{{ old('treasurer_name', $school->treasurer_name) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">NIP Bendahara</label>
                <input type="text" name="treasurer_nip" value="{{ old('treasurer_nip', $school->treasurer_nip) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Bank Operasional *</label>
                <input type="text" name="bank_name" required value="{{ old('bank_name', $school->bank_name) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Rekening Sekolah *</label>
                <input type="text" name="bank_account_number" required value="{{ old('bank_account_number', $school->bank_account_number) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pemilik Rekening *</label>
                <input type="text" name="bank_account_holder" required value="{{ old('bank_account_holder', $school->bank_account_holder) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
            <a href="{{ route('schools.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md transition-all">
                Simpan Perubahan
            </button>
        </div>
    </form>

</div>
@endsection

