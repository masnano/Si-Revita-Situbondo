@extends('layouts.app')

@section('title', 'Edit Paket Revitalisasi')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1 mb-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Kembali ke Paket Revitalisasi</span>
        </a>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
            Edit Paket: {{ $project->title }}
        </h1>
    </div>

    <form action="{{ route('projects.update', $project->id) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Sekolah Pelaksana *</label>
                <select name="school_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    @foreach($schools as $sch)
                        <option value="{{ $sch->id }}" {{ $project->school_id == $sch->id ? 'selected' : '' }}>
                            {{ $sch->name }} ({{ $sch->jenjang }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tahun Anggaran *</label>
                <input type="number" name="fiscal_year" required value="{{ old('fiscal_year', $project->fiscal_year) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Paket Kegiatan Revitalisasi *</label>
            <input type="text" name="title" required value="{{ old('title', $project->title) }}"
                   class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Sumber Pembiayaan / Dana *</label>
            <input type="text" name="funding_source" required value="{{ old('funding_source', $project->funding_source) }}"
                   class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Surat Perjanjian / SPK / SK *</label>
                <input type="text" name="spk_number" required value="{{ old('spk_number', $project->spk_number) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal SPK / SK Penetapan *</label>
                <input type="date" name="spk_date" required value="{{ old('spk_date', $project->spk_date ? $project->spk_date->format('Y-m-d') : '') }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Total Nilai Pagu Kontrak (Rp) *</label>
                <input type="number" step="any" name="contract_amount" required value="{{ old('contract_amount', $project->contract_amount) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Status Proyek *</label>
                <select name="status" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    <option value="perencanaan" {{ $project->status == 'perencanaan' ? 'selected' : '' }}>Perencanaan</option>
                    <option value="pelaksanaan" {{ $project->status == 'pelaksanaan' ? 'selected' : '' }}>Pelaksanaan</option>
                    <option value="selesai" {{ $project->status == 'selesai' ? 'selected' : '' }}>Selesai</option>
                    <option value="evaluasi" {{ $project->status == 'evaluasi' ? 'selected' : '' }}>Evaluasi</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Mulai *</label>
                <input type="date" name="start_date" required value="{{ old('start_date', $project->start_date ? $project->start_date->format('Y-m-d') : '') }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Selesai *</label>
                <input type="date" name="end_date" required value="{{ old('end_date', $project->end_date ? $project->end_date->format('Y-m-d') : '') }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Progres Fisik (%) *</label>
                <input type="number" step="0.01" min="0" max="100" name="physical_progress" required value="{{ old('physical_progress', $project->physical_progress) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold text-emerald-600">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Deskripsi Ruang Lingkup</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">{{ old('description', $project->description) }}</textarea>
        </div>

        <!-- Berkas Kurva S / Jadwal Pelaksanaan -->
        <div class="p-4 bg-emerald-50/60 rounded-2xl border border-emerald-200/80 space-y-3">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-bold text-emerald-900 flex items-center gap-1.5">
                    <i data-lucide="trending-up" class="w-4 h-4 text-emerald-600"></i>
                    <span>Berkas Kurva S / Jadwal Pelaksanaan Pekerjaan (PDF / Excel)</span>
                </label>
                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-2 py-0.5 rounded-full">Mendukung .PDF & .XLSX</span>
            </div>

            @if($project->hasKurvaS())
                <div class="p-3 bg-white rounded-xl border border-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">
                            <i data-lucide="file-check" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-slate-800 flex items-center gap-2">
                                <span>{{ $project->kurva_s_filename ?: 'Dokumen Kurva S Terlampir' }}</span>
                                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-1.5 py-0.5 rounded">
                                    {{ $project->kurva_s_total_weeks }} Minggu
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                Berhasil diekstrak & terintegrasi dengan modul Pecah Bahan RPD
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('projects.kurva_s.download', $project->id) }}" target="_blank"
                           class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold flex items-center gap-1 transition-all">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                            <span>Buka Berkas</span>
                        </a>
                        <label class="flex items-center gap-1.5 text-xs text-rose-600 hover:text-rose-700 font-semibold cursor-pointer px-2 py-1.5 rounded-lg hover:bg-rose-50">
                            <input type="checkbox" name="delete_kurva_s" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            <span>Hapus</span>
                        </label>
                    </div>
                </div>
            @endif

            <div>
                <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                    {{ $project->hasKurvaS() ? 'Ganti Berkas Kurva S Baru' : 'Unggah Berkas Kurva S' }}
                </label>
                <input type="file" name="kurva_s" accept=".pdf,.xlsx,.xls"
                       class="block w-full text-xs text-slate-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer border border-emerald-300 rounded-xl bg-white p-1.5 shadow-sm">
                <p class="text-[10px] text-slate-500 mt-1">
                    Format file: .pdf atau .xlsx (maks 20MB). Ketika diunggah, jadwal belanja BKU di RPD terkait akan otomatis disinkronkan.
                </p>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
            <a href="{{ route('projects.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md transition-all">
                Simpan Perubahan
            </button>
        </div>
    </form>

</div>
@endsection

