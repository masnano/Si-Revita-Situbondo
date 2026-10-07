@extends('layouts.app')

@section('title', 'Unggah File RPD & Mulai Pecah Bahan - Si Revita Situbondo')

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
                <span>Unggah Dokumen RPD & Pecah Bahan</span>
            </h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">
                Sistem akan membaca file RPD, mengelompokkan bahan material, menghitung PPN & PPh, lalu menyusun draf isian Buku Kas Umum (BKU).
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
                                            {{ $school->name }} ({{ $school->npsn }})
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
                                    <option value="Tahap 1" class="bg-white text-slate-800" {{ old('term_stage') == 'Tahap 1' ? 'selected' : '' }}>Tahap 1 (Pencairan Awal / Pondasi & Struktur)</option>
                                    <option value="Tahap 2" class="bg-white text-slate-800" {{ old('term_stage') == 'Tahap 2' ? 'selected' : '' }}>Tahap 2 (Dinding, Atap & Kusen)</option>
                                    <option value="Tahap 3" class="bg-white text-slate-800" {{ old('term_stage') == 'Tahap 3' ? 'selected' : '' }}>Tahap 3 (Finishing, Pengecatan & Penyerahan)</option>
                                    <option value="RPD Operasional & Bahan Mingguan" class="bg-white text-slate-800" {{ old('term_stage') == 'RPD Operasional & Bahan Mingguan' ? 'selected' : '' }}>RPD Operasional / Belanja Mingguan</option>
                                </select>
                            </div>

                            <div>
                                <label for="title" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Nama / Judul Dokumen (Opsional)
                                </label>
                                <input type="text" name="title" id="title" value="{{ old('title') }}" 
                                       placeholder="Contoh: RPD Tahap 1 - Rehabilitasi 3 Ruang Kelas"
                                       class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none placeholder-slate-400">
                            </div>
                        </div>
                    </div>

                    <!-- 2. Berkas File RPD -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2 pb-2 border-b border-slate-100">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-xs flex items-center justify-center font-bold">2</span>
                            <span>Unggah Berkas File RPD (Excel / CSV)</span>
                        </h3>

                        <!-- Drag and drop zone -->
                        <div class="relative border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-6 md:p-8 text-center transition-all bg-slate-50/50 group">
                            <input type="file" name="rpd_file" id="rpd_file" accept=".xlsx,.xls,.csv" required
                                   @change="handleFileSelect($event)"
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                            <div class="space-y-3 pointer-events-none">
                                <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform shadow-sm">
                                    <i data-lucide="file-up" class="w-8 h-8"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">
                                        <span x-text="fileName ? fileName : 'Pilih file RPD atau seret file ke sini'"></span>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        Mendukung format <strong class="text-emerald-600">.xlsx</strong>, <strong class="text-teal-600">.xls</strong>, atau <strong class="text-cyan-600">.csv</strong> (Maks. 10 MB)
                                    </p>
                                </div>
                                <div x-show="fileSize" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs bg-emerald-100 text-emerald-800 font-mono">
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
                            <span>Parameter Nilai Default (Bila di Dokumen Kosong)</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="default_date" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Tanggal Transaksi BKU Bawaan
                                </label>
                                <input type="date" name="default_date" id="default_date" value="{{ old('default_date', date('Y-m-d')) }}"
                                       class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                                <p class="text-[11px] text-slate-500 mt-1">Digunakan jika baris item tidak memiliki tanggal penarikan.</p>
                            </div>

                            <div>
                                <label for="default_store" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Toko Bahan Bangunan Bawaan
                                </label>
                                <input type="text" name="default_store" id="default_store" value="{{ old('default_store', 'Toko Bangunan Berkah Situbondo') }}"
                                       placeholder="Nama Toko Rekanan"
                                       class="w-full text-xs md:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                                <p class="text-[11px] text-slate-500 mt-1">Nama penerima belanja barang/bahan di Situbondo.</p>
                            </div>
                        </div>

                        <div>
                            <label for="notes" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Catatan / Keterangan Dokumen (Opsional)
                            </label>
                            <textarea name="notes" id="notes" rows="2" 
                                      placeholder="Tambahkan catatan pencairan dana atau catatan teknis revitalisasi..."
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
                    <span>Butuh Template Format RPD?</span>
                </div>
                <p class="text-xs text-emerald-100/90 leading-relaxed">
                    Unduh file template resmi yang telah dilengkapi rumus total biaya, kolom spesifikasi, dan contoh realistis belanja bahan bangunan (semen, pasir, bata, cat) serta upah tukang khas Situbondo.
                </p>
                <div class="space-y-2 pt-1">
                    <a href="{{ route('rpd.template', ['format' => 'xlsx']) }}" 
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-xl text-xs font-bold transition-all shadow-md">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        <span>Unduh Template Excel (.xlsx)</span>
                    </a>
                    <a href="{{ route('rpd.template', ['format' => 'csv']) }}" 
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-semibold transition-all border border-white/20">
                        <i data-lucide="file-text" class="w-4 h-4"></i>
                        <span>Unduh Template CSV (.csv)</span>
                    </a>
                </div>
            </div>

            <!-- Rules & Guidance Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                    <span>Panduan Kolom File RPD</span>
                </h4>
                <ul class="text-xs text-slate-600 space-y-2.5 list-disc list-inside">
                    <li><strong class="text-slate-800">Uraian / Nama Bahan:</strong> Wajib ada (misal Semen Gresik, Pasir Kali, Upah Tukang).</li>
                    <li><strong class="text-slate-800">Volume & Satuan:</strong> Contoh: 100 Zak, 8 M3, 24 OH.</li>
                    <li><strong class="text-slate-800">Harga Satuan:</strong> Angka nominal tarif (Rp).</li>
                    <li><strong class="text-slate-800">Kategori:</strong> Otomatis diidentifikasi sebagai <em>Bahan</em>, <em>Upah</em>, <em>Alat</em>, atau <em>Operasional</em> berdasarkan kata kunci.</li>
                    <li><strong class="text-slate-800">Perhitungan Pajak:</strong> Belanja bahan &ge; Rp 2.000.000 otomatis dikenakan PPN 11% & PPh 22 1.5%. Upah dikenakan PPh 21.</li>
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
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                this.fileSize = sizeMb > 1 ? `${sizeMb} MB` : `${(file.size / 1024).toFixed(1)} KB`;
            } else {
                this.fileName = '';
                this.fileSize = '';
            }
        }
    };
}
</script>
@endpush
@endsection

