@extends('layouts.app')

@section('title', 'Tambah Paket Revitalisasi')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1 mb-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Kembali ke Paket Revitalisasi</span>
        </a>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
            Tambah Paket Revitalisasi Baru
        </h1>
    </div>

    <form action="{{ route('projects.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-5">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Sekolah Pelaksana *</label>
                <select name="school_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    @foreach($schools as $sch)
                        <option value="{{ $sch->id }}">{{ $sch->name }} ({{ $sch->jenjang }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tahun Anggaran *</label>
                <input type="number" name="fiscal_year" required value="{{ old('fiscal_year', 2026) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Paket Kegiatan Revitalisasi *</label>
            <input type="text" name="title" required value="{{ old('title') }}" placeholder="Contoh: Revitalisasi Ruang Kelas, Perpustakaan, dan Lab Komputer"
                   class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Sumber Pembiayaan / Dana *</label>
            <input type="text" name="funding_source" required value="{{ old('funding_source', 'DAK Fisik Bidang Pendidikan TA 2026') }}"
                   class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Surat Perjanjian / SPK / SK *</label>
                <input type="text" name="spk_number" required value="{{ old('spk_number') }}" placeholder="027/451/..."
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal SPK / SK Penetapan *</label>
                <input type="date" name="spk_date" required value="{{ old('spk_date', date('Y-m-d')) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Total Nilai Pagu Kontrak (Rp) *</label>
                <input type="number" step="any" name="contract_amount" required value="{{ old('contract_amount') }}" placeholder="850000000"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Status Proyek *</label>
                <select name="status" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    <option value="perencanaan">Perencanaan</option>
                    <option value="pelaksanaan" selected>Pelaksanaan</option>
                    <option value="selesai">Selesai</option>
                    <option value="evaluasi">Evaluasi</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Mulai *</label>
                <input type="date" name="start_date" required value="{{ old('start_date', date('Y-m-d')) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Selesai *</label>
                <input type="date" name="end_date" required value="{{ old('end_date') }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Progres Fisik Saat Ini (%) *</label>
                <input type="number" step="0.01" min="0" max="100" name="physical_progress" required value="{{ old('physical_progress', 0) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Deskripsi Ruang Lingkup Revitalisasi</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">{{ old('description') }}</textarea>
        </div>

        <!-- Upload Kurva S / Jadwal Pelaksanaan -->
        <div class="p-4 bg-emerald-50/60 rounded-2xl border border-emerald-200/80 space-y-3">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-bold text-emerald-900 flex items-center gap-1.5">
                    <i data-lucide="trending-up" class="w-4 h-4 text-emerald-600"></i>
                    <span>Berkas Kurva S / Jadwal Pelaksanaan Pekerjaan (PDF / Excel)</span>
                </label>
                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-2 py-0.5 rounded-full">Mendukung .PDF & .XLSX</span>
            </div>
            <p class="text-[11px] text-slate-600 leading-relaxed">
                Unggah dokumen Kurva S resmi (format Kurva S Dinas / P2SP seperti <span class="font-mono text-emerald-700">SDN 3 SUMBERARGO Kurva S.pdf</span>). Sistem akan secara otomatis menganalisis bobot rencana per minggu dan menyelaraskan jadwal penanggalan belanja BKU pada modul Pecah Bahan RPD.
            </p>
            <div>
                <input type="file" name="kurva_s" accept=".pdf,.xlsx,.xls"
                       class="block w-full text-xs text-slate-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer border border-emerald-300 rounded-xl bg-white p-1.5 shadow-sm">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
            <a href="{{ route('projects.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition-all">
                Simpan Paket
            </button>
        </div>
    </form>

</div>
@endsection

