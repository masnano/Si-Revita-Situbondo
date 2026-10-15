@extends('layouts.app')

@section('title', 'Unggah Berkas RPD & Mulai Pecah Bahan - Si Revita Situbondo')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="rpdUploader()">
    <!-- Breadcrumb & Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('rpd.index') }}" class="hover:text-emerald-600">Pecah Bahan RPD</a>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span class="text-slate-800 font-medium">Unggah Dokumen Baru</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-slate-900 flex items-center gap-2.5">
                <i data-lucide="upload-cloud" class="w-7 h-7 text-emerald-600"></i>
                <span>Unggah Berkas RPD & Pecah Bahan</span>
            </h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">
                Sistem didukung <strong class="text-emerald-700">AI Pemetaan Cerdas Fleksibel</strong> yang membaca berkas RPD tanpa perlu format template yang kaku (cukup ada: <strong>Nama/Uraian</strong>, <strong>VOL</strong>, <strong>Sat</strong>, <strong>Harga Satuan</strong>, <strong>Termin 1 (70%)</strong>, <strong>Termin 2 (30%)</strong>, <strong>Jumlah (100%)</strong>, dan <strong>Keterangan</strong>). Pos <em>Biaya Perencanaan</em> dan <em>Biaya Pengawasan</em> dijaga tetap utuh tanpa di-breakdown, sedangkan belanja fisik, mobilisasi, dan pengelolaan dipecah AI secara variatif, masuk akal, dan aman dari temuan BPK.
            </p>
        </div>

        <a href="{{ route('rpd.index') }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 shadow-sm transition-all">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Kembali ke Daftar</span>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Upload Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8">
                <form action="{{ route('rpd.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <!-- 1. Sasaran Sekolah & Proyek -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2 pb-2 border-b border-slate-100">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-xs flex items-center justify-center font-bold">1</span>
                            <span>Target Sekolah & Paket Revitalisasi</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="school_id" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Satuan Pendidikan (Sekolah) <span class="text-rose-500">*</span>
                                </label>
                                <select name="school_id" id="school_id" x-model="selectedSchool" @change="onSchoolChange()" required
                                        class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                                    <option value="" class="bg-white text-slate-800">-- Pilih Sekolah --</option>
                                    @foreach($schools as $school)
                                        <option value="{{ $school->id }}" class="bg-white text-slate-800" {{ old('school_id', $selectedSchoolId) == $school->id ? 'selected' : '' }}>
                                            {{ $school->name }} (NPSN: {{ $school->npsn }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('school_id')
                                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="project_id" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Paket Proyek Revitalisasi <span class="text-rose-500">*</span>
                                </label>
                                <select name="project_id" id="project_id" required
                                        class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                                    <option value="" class="bg-white text-slate-800">-- Pilih Paket Proyek --</option>
                                    @foreach($projects as $prj)
                                        <option value="{{ $prj->id }}" class="bg-white text-slate-800" data-school="{{ $prj->school_id }}" {{ old('project_id') == $prj->id ? 'selected' : '' }}>
                                            {{ $prj->title }} (TA {{ $prj->fiscal_year }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('project_id')
                                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <div>
                                <label for="term_stage" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Tahap / Termin Penarikan <span class="text-rose-500">*</span>
                                </label>
                                <select name="term_stage" id="term_stage" required
                                        class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                                    <option value="Tahap 1" class="bg-white text-slate-800" {{ old('term_stage') == 'Tahap 1' ? 'selected' : '' }}>Tahap 1 (Pencairan Awal / Termin 1 70%)</option>
                                    <option value="Tahap 2" class="bg-white text-slate-800" {{ old('term_stage') == 'Tahap 2' ? 'selected' : '' }}>Tahap 2 (Pencairan Lanjutan / Termin 2 30%)</option>
                                    <option value="Tahap 3" class="bg-white text-slate-800" {{ old('term_stage') == 'Tahap 3' ? 'selected' : '' }}>Tahap 3 (Finishing & Penyerahan)</option>
                                    <option value="Semua Tahap (Total 100%)" class="bg-white text-slate-800" {{ old('term_stage') == 'Semua Tahap (Total 100%)' ? 'selected' : '' }}>Semua Tahap / Total 100% (Akumulasi Penuh)</option>
                                    <option value="RPD Operasional & Bahan Mingguan" class="bg-white text-slate-800" {{ old('term_stage') == 'RPD Operasional & Bahan Mingguan' ? 'selected' : '' }}>RPD Operasional / Belanja Mingguan</option>
                                </select>
                            </div>

                            <div>
                                <label for="target_column" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Alokasi Kolom Anggaran RPD
                                </label>
                                <select name="target_column" id="target_column"
                                        class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                                    <option value="auto" {{ old('target_column', 'auto') == 'auto' ? 'selected' : '' }}>Otomatis (Sesuai Tahap yang dipilih di samping)</option>
                                    <option value="termin_1" {{ old('target_column') == 'termin_1' ? 'selected' : '' }}>Kolom Termin 1 (70%)</option>
                                    <option value="termin_2" {{ old('target_column') == 'termin_2' ? 'selected' : '' }}>Kolom Termin 2 (30%)</option>
                                    <option value="termin_3" {{ old('target_column') == 'termin_3' ? 'selected' : '' }}>Kolom Termin 3</option>
                                    <option value="total" {{ old('target_column') == 'total' ? 'selected' : '' }}>Kolom Jumlah Total (100%)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="title" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Nama / Judul Dokumen (Opsional)
                            </label>
                            <input type="text" name="title" id="title" value="{{ old('title') }}" 
                                   placeholder="Contoh: RPD Tahap 1 - Rehabilitasi Ruang Kelas, Perpustakaan & Toilet"
                                   class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none placeholder-slate-400">
                        </div>
                    </div>

                    <!-- 2. Berkas File RPD -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2 pb-2 border-b border-slate-100">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-xs flex items-center justify-center font-bold">2</span>
                            <span>Unggah Berkas RPD (Fleksibel: Excel / PDF / CSV)</span>
                        </h3>

                        <!-- Drag and drop zone -->
                        <div class="relative border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-6 md:p-8 text-center transition-all bg-slate-50/50 group"
                             :class="{ 'border-rose-400 bg-rose-50/20': fileExt === 'pdf', 'border-emerald-500 bg-emerald-50/20': fileExt === 'xlsx' || fileExt === 'xls' }">
                            <input type="file" name="rpd_file" id="rpd_file" accept=".xlsx,.xls,.pdf,.csv" required
                                   @change="handleFileSelect($event)"
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                            <div class="space-y-3 pointer-events-none">
                                <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm"
                                     :class="{
                                         'bg-rose-50 text-rose-600 border border-rose-200': fileExt === 'pdf',
                                         'bg-emerald-50 text-emerald-600 border border-emerald-200': fileExt === 'xlsx' || fileExt === 'xls',
                                         'bg-cyan-50 text-cyan-600 border border-cyan-200': fileExt === 'csv',
                                         'bg-slate-100 text-slate-500 border border-slate-200': !fileExt
                                     }">
                                    <template x-if="fileExt === 'pdf'">
                                        <i data-lucide="file-text" class="w-8 h-8"></i>
                                    </template>
                                    <template x-if="fileExt === 'xlsx' || fileExt === 'xls'">
                                        <i data-lucide="file-spreadsheet" class="w-8 h-8"></i>
                                    </template>
                                    <template x-if="fileExt !== 'pdf' && fileExt !== 'xlsx' && fileExt !== 'xls'">
                                        <i data-lucide="upload-cloud" class="w-8 h-8"></i>
                                    </template>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">
                                        <span x-text="fileName ? fileName : 'Pilih file RPD atau seret file ke sini'"></span>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        Mendukung berkas <strong class="text-emerald-600 font-bold">Excel (.xlsx, .xls)</strong> dan <strong class="text-rose-600 font-bold">PDF (.pdf)</strong> resmi RPD Revitalisasi (Maks. 20 MB)
                                    </p>
                                </div>
                                <div x-show="fileSize" class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-mono"
                                     :class="fileExt === 'pdf' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'">
                                    <span class="font-bold uppercase" x-text="fileExt"></span>
                                    <span>&bull;</span>
                                    <span x-text="fileSize"></span>
                                </div>
                            </div>
                        </div>
                        @error('rpd_file')
                            <p class="text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 3. Konfigurasi Default & Parameter Pecah Bahan -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2 pb-2 border-b border-slate-100">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-xs flex items-center justify-center font-bold">3</span>
                            <span>Parameter Nilai Default & Konfigurasi Pecah Bahan</span>
                        </h3>

                        <!-- Mode Pecah Bahan -->
                        <div class="p-4 rounded-xl bg-emerald-50/60 border border-emerald-200 space-y-3">
                            <label class="block text-xs font-bold text-emerald-950 uppercase tracking-wider">
                                Mode Pecah Bahan untuk Isian BKU <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label class="relative flex items-start gap-3 p-3.5 rounded-xl border border-emerald-300 bg-white shadow-sm cursor-pointer hover:border-emerald-500 transition-all">
                                    <input type="radio" name="breakdown_mode" value="detailed" {{ old('breakdown_mode', 'detailed') === 'detailed' ? 'checked' : '' }}
                                           class="mt-1 text-emerald-600 focus:ring-emerald-500 border-slate-300">
                                    <div class="text-xs">
                                        <div class="font-bold text-slate-800 flex items-center gap-1.5">
                                            <span>Pecah Bahan Rinci / Detail</span>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Rekomendasi BKU</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                            Memecah pagu Belanja Bahan & Upah menjadi item material riil (Semen, Pasir, Bata, Besi, Cat, Keramik, Plafon, Kusen, Pipa, Kloset) dan HOK Tukang/Pekerja sesuai jenis pekerjaan ruang tanpa selisih anggaran.
                                        </p>
                                    </div>
                                </label>

                                <label class="relative flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 bg-white shadow-sm cursor-pointer hover:border-slate-400 transition-all">
                                    <input type="radio" name="breakdown_mode" value="summary" {{ old('breakdown_mode') === 'summary' ? 'checked' : '' }}
                                           class="mt-1 text-emerald-600 focus:ring-emerald-500 border-slate-300">
                                    <div class="text-xs">
                                        <div class="font-bold text-slate-800">Ringkasan Global (Summary)</div>
                                        <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                            Menyimpan 1 baris Belanja Bahan dan 1 baris Belanja Upah per ruang pekerjaan sesuai lembar format RPD dinas.
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="default_date" class="block text-xs font-semibold text-slate-700 mb-1.5 flex items-center gap-1.5">
                                    <span>Tanggal Awal Transaksi BKU (Tanggal Pencairan Dana)</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Saldo Masuk Otomatis</span>
                                </label>
                                <input type="date" name="default_date" id="default_date" value="{{ old('default_date', date('Y-m-d')) }}"
                                       class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                                <p class="text-[11px] text-slate-500 mt-1">
                                    Dianggap sebagai tanggal pencairan dana termin. Sistem otomatis mencatat <strong>Saldo Masuk (Penerimaan SP2D)</strong> dan <strong>Penarikan Kas Tunai</strong> pada tanggal ini, lalu menjadwalkan belanja bahan & upah bertahap tanpa hari Minggu.
                                </p>
                            </div>

                            <div>
                                <label for="default_store" class="block text-xs font-semibold text-slate-700 mb-1.5 flex items-center justify-between">
                                    <span>Toko Rekanan Bahan Bangunan</span>
                                    <span class="text-[11px] font-normal text-slate-400">Opsional</span>
                                </label>
                                <input type="text" name="default_store" id="default_store" value="{{ old('default_store', '') }}"
                                       placeholder="Kosongkan jika belum ada (otomatis diisi titik-titik untuk manual)"
                                       class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none placeholder:text-slate-400">
                                <p class="text-[11px] text-slate-500 mt-1">Boleh dikosongkan. Jika tidak diisi, sistem otomatis mengisikan titik-titik (<span class="font-mono text-slate-600 font-bold">....................</span>) agar dapat diisi manual.</p>
                            </div>
                        </div>

                        <!-- 4. Pilihan Fitur AI Google Gemini (Gratis) -->
                        <div class="p-4 rounded-xl bg-gradient-to-r from-teal-50 via-emerald-50 to-cyan-50 border border-teal-200 shadow-sm space-y-3" x-data="{ enableAi: true, showApiKey: false }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-teal-600 text-white flex items-center justify-center shrink-0 shadow-sm font-bold">
                                        <i data-lucide="sparkles" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <label for="use_ai" class="text-xs font-bold text-teal-950 flex items-center gap-1.5 cursor-pointer">
                                            <span>Aktifkan Variasi Cerdas Google Gemini AI (Gratis)</span>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-teal-100 text-teal-800 border border-teal-300">Rekomendasi</span>
                                        </label>
                                        <p class="text-[11px] text-teal-800/90 mt-1 leading-relaxed">
                                            AI akan menyusun urutan jadwal tanggal belanja berfase konstruksi (pondasi &rarr; dinding &rarr; atap &rarr; finishing), menjadwalkan upah di hari Sabtu, memvariasikan kalimat narasi BKU resmi, dan merekomendasikan toko lokal Situbondo secara otomatis.
                                        </p>
                                        <div class="mt-2.5 grid grid-cols-1 md:grid-cols-2 gap-2 text-[11px]">
                                            <div class="p-2.5 rounded-lg bg-white/80 border border-teal-200 shadow-xs">
                                                <div class="font-bold text-teal-900 flex items-center gap-1.5">
                                                    <i data-lucide="truck" class="w-3.5 h-3.5 text-teal-600"></i>
                                                    <span>Auto-Pecah Biaya Mobilisasi (AI)</span>
                                                </div>
                                                <p class="text-slate-600 text-[10.5px] mt-1 leading-snug">
                                                    Pagu mobilisasi otomatis dipecah AI menjadi <strong>ongkos kirim/angkut material</strong> (pondasi, dinding/atap, finishing) dari toko ke sekolah serta sewa peralatan kerja.
                                                </p>
                                            </div>
                                            <div class="p-2.5 rounded-lg bg-white/80 border border-teal-200 shadow-xs">
                                                <div class="font-bold text-teal-900 flex items-center gap-1.5">
                                                    <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                                                    <span>Auto-Pecah Pengelolaan P2SP (AI)</span>
                                                </div>
                                                <p class="text-slate-600 text-[10.5px] mt-1 leading-snug">
                                                    Pagu pengelolaan otomatis dipecah AI menjadi <strong>kegiatan riil operasional P2SP</strong> (ATK/SPJ, konsumsi rapat, SPPD dinas & bank, spanduk transparansi, materai 10.000) yang <strong>anti-temuan BPK</strong>.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pt-0.5">
                                    <input type="checkbox" name="use_ai" id="use_ai" value="1" x-model="enableAi" checked
                                           class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 border-slate-300 cursor-pointer">
                                </div>
                            </div>

                            <div x-show="enableAi" x-transition class="pt-2 border-t border-teal-200/70 text-xs text-teal-900 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] text-teal-700">
                                        Status: <strong>{{ config('services.gemini.api_key') ? 'API Key Siap Terhubung (Google Gemini)' : 'Mode Cerdas Lokal Aktif (Dapat Menggunakan API Key Sendiri)' }}</strong>
                                    </span>
                                    <button type="button" @click="showApiKey = !showApiKey" class="text-[11px] font-bold text-teal-700 hover:text-teal-900 underline flex items-center gap-1">
                                        <i data-lucide="key" class="w-3 h-3"></i>
                                        <span x-text="showApiKey ? 'Sembunyikan Pengaturan Key' : 'Atur / Masukkan API Key Gemini (Opsional)'"></span>
                                    </button>
                                </div>

                                <div x-show="showApiKey" x-transition class="p-3 bg-white rounded-lg border border-teal-300 space-y-2">
                                    <label class="block text-[11px] font-semibold text-slate-700">
                                        Google Gemini API Key (Opsional / Free Tier)
                                    </label>
                                    <input type="password" name="gemini_api_key" placeholder="AIzaSy... (Dapatkan gratis di aistudio.google.com)"
                                           class="w-full text-xs rounded-lg border border-slate-300 p-2 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                    <p class="text-[10px] text-slate-500">
                                        Jika dikosongkan, sistem tetap menghasilkan variasi menggunakan Algoritma Cerdas Heuristik Lokal Situbondo (100% offline).
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="notes" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Catatan / Keterangan Dokumen (Opsional)
                            </label>
                            <textarea name="notes" id="notes" rows="2" 
                                      placeholder="Tambahkan catatan teknis pencairan atau progres revitalisasi..."
                                      class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none"></textarea>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <a href="{{ route('rpd.index') }}" class="px-5 py-2.5 rounded-xl text-xs md:text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                            Batal
                        </a>
                        <button type="submit" 
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs md:text-sm font-bold shadow-lg shadow-emerald-600/30 transition-all hover:-translate-y-0.5">
                            <i data-lucide="sparkles" class="w-4 h-4"></i>
                            <span>Proses & Pecah Bahan Sekarang</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar Helper & Template Download -->
        <div class="space-y-6">
            <!-- Template Card -->
            <div class="bg-gradient-to-br from-emerald-900 to-teal-950 rounded-2xl p-6 text-white border border-emerald-700/50 shadow-lg space-y-4">
                <div class="flex items-center gap-2.5 text-emerald-300 font-bold text-sm">
                    <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
                    <span>Template Berkas RPD</span>
                </div>
                <p class="text-xs text-emerald-100/90 leading-relaxed">
                    Unduh file template resmi sesuai format Dinas Pendidikan Situbondo (Pekerjaan Fisik: Ruang Kelas, Perpustakaan, Toilet dengan Belanja Bahan & Belanja Upah).
                </p>
                <div class="space-y-2 pt-1">
                    <a href="{{ route('rpd.template', ['type' => 'official', 'format' => 'xlsx']) }}" 
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-xl text-xs font-bold transition-all shadow-md">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                        <span>Template Resmi RPD (Excel .xlsx)</span>
                    </a>
                    <a href="{{ route('rpd.template', ['type' => 'official', 'format' => 'pdf']) }}" 
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-rose-600/90 hover:bg-rose-600 text-white rounded-xl text-xs font-semibold transition-all shadow-sm border border-rose-400/40">
                        <i data-lucide="file-text" class="w-4 h-4"></i>
                        <span>Format Resmi RPD (PDF .pdf)</span>
                    </a>
                    <a href="{{ route('rpd.template', ['type' => 'itemized', 'format' => 'xlsx']) }}" 
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-semibold transition-all border border-white/20">
                        <i data-lucide="list" class="w-4 h-4"></i>
                        <span>Template Rincian Item Bahan (.xlsx)</span>
                    </a>
                </div>
            </div>

            <!-- Rules & Guidance Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                    <span>Ketentuan Format & Pecah Bahan</span>
                </h4>
                <ul class="text-xs text-slate-600 space-y-2.5 list-disc list-inside">
                    <li><strong class="text-slate-800">Pemetaan AI Fleksibel (Template Bebas):</strong> Format file tidak perlu terlalu persis templatenya! Cukup memuat data: <strong class="text-emerald-700">Nama/Uraian</strong>, <strong class="text-emerald-700">VOL/Volume</strong>, <strong class="text-emerald-700">Sat/Satuan</strong>, <strong class="text-emerald-700">Harga Satuan</strong>, <strong class="text-emerald-700">Termin 1 (70%)</strong>, <strong class="text-emerald-700">Termin 2 (30%)</strong>, <strong class="text-emerald-700">Jumlah (100%)</strong>, dan <strong class="text-emerald-700">Keterangan</strong>. Fitur AI otomatis memetakan kolom dan memprosesnya sesuai kebutuhan BKU.</li>
                    <li><strong class="text-slate-800">Biaya Perencanaan & Pengawasan Tidak Dibreakdown:</strong> Pos <em>Biaya Perencanaan</em> tidak dipecah dan dijadwalkan di awal setelah pencairan 70%, sedangkan <em>Biaya Pengawasan</em> tetap utuh tanpa dirubah judulnya dan dijadwalkan di akhir setelah pencairan 30% serta seluruh pekerjaan fisik selesai.</li>
                    <li><strong class="text-slate-800">Pecah Bahan AI Variatif & Aman Temuan:</strong> Selain biaya perencanaan & pengawasan, seluruh pos fisik, mobilisasi, dan pengelolaan dipecah oleh AI secara variatif dan masuk akal (mengacu HSPK Situbondo 2026, toko terdekat, jadwal konstruksi bertahap, dan bebas dari temuan BPK).</li>
                    <li><strong class="text-slate-800">Standar Harga Situbondo (HSPK/SHS):</strong> AI & Mesin Cerdas memecah anggaran berbasis harga resmi Pemkab Situbondo terkini (Tukang: Rp 120.000–125.000/OH, Pembantu Tukang: Rp 90.000/OH, Bahan SNI) dengan total belanja tetap 100% presisi dan balance.</li>
                    <li><strong class="text-slate-800">Toko Bangunan Terdekat Situbondo:</strong> Sistem otomatis merekomendasikan nama toko bangunan riil terdekat dari alamat dan kecamatan lokasi sekolah di 17 kecamatan se-Kabupaten Situbondo.</li>
                    <li><strong class="text-slate-800">Kalkulasi Pajak Otomatis:</strong> Belanja bahan &ge; Rp 2.000.000 dihitung PPN 11% & PPh 22 1.5%. Upah tukang swakelola bebas PPh 21 jika harian &le; Rp 450.000 (PMK 168/2023).</li>
                    <li><strong class="text-slate-800">Siap Jadi BKU:</strong> Hasil pecah bahan langsung dapat diposting ke BKU, BPK, BB, dan BP hanya dengan satu klik.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function rpdUploader() {
    return {
        selectedSchool: '{{ old('school_id', $selectedSchoolId) }}',
        fileName: '',
        fileSize: '',
        fileExt: '',
        init() {
            if (this.selectedSchool) {
                this.onSchoolChange();
            }
        },
        onSchoolChange() {
            const projectSelect = document.getElementById('project_id');
            if (!projectSelect) return;
            const options = projectSelect.querySelectorAll('option');
            let firstMatched = false;

            options.forEach(opt => {
                if (!opt.value) return;
                const schoolId = opt.getAttribute('data-school');
                if (!this.selectedSchool || schoolId === this.selectedSchool) {
                    opt.style.display = '';
                    if (!firstMatched) {
                        projectSelect.value = opt.value;
                        firstMatched = true;
                    }
                } else {
                    opt.style.display = 'none';
                }
            });

            if (!firstMatched) {
                projectSelect.value = '';
            }
        },
        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.fileName = file.name;
                const ext = file.name.split('.').pop().toLowerCase();
                this.fileExt = ext;
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                this.fileSize = sizeMb > 1 ? `${sizeMb} MB` : `${(file.size / 1024).toFixed(1)} KB`;
            } else {
                this.fileName = '';
                this.fileSize = '';
                this.fileExt = '';
            }
        }
    };
}
</script>
@endpush
@endsection
