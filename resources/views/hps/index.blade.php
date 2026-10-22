@extends('layouts.app')

@section('title', 'Konversi Data HPS ke Template - Si Revita Situbondo')
@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="hpsConverter()">
    <!-- Breadcrumb & Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <span>Output Laporan Keuangan</span>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span class="text-slate-800 font-medium">Konversi Data HPS</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-slate-900 flex items-center gap-2.5">
                <i data-lucide="file-spreadsheet" class="w-7 h-7 text-sky-600"></i>
                <span>Konversi Sheet HPS ke Template DPP</span>
            </h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">
                Unggah berkas berisi sheet <strong class="text-sky-700">HPS</strong>. Harga pada HPS sudah berupa
                <strong class="text-sky-700">DPP</strong> sehingga disalin apa adanya. Hasilnya ditulis ke
                <strong class="text-sky-700">template resmi SPSE</strong> (<span class="font-mono text-slate-600">template-rincian.xlsx</span>) sehingga siap diunggah.
            </p>
        </div>

        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 shadow-sm transition-all">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Kembali ke Dashboard</span>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Upload Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8">
                <form action="{{ route('hps.convert') }}" method="POST" enctype="multipart/form-data" class="space-y-6" data-download="true">
                    @csrf

                    <!-- 1. Berkas HPS -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2 pb-2 border-b border-slate-100">
                            <span class="w-5 h-5 rounded-full bg-sky-100 text-sky-700 text-xs flex items-center justify-center font-bold">1</span>
                            <span>Unggah Berkas HPS</span>
                        </h3>

                        <div class="relative border-2 border-dashed border-slate-300 hover:border-sky-500 rounded-2xl p-6 md:p-8 text-center transition-all bg-slate-50/50 group"
                             :class="{ 'border-sky-500 bg-sky-50/20': fileExt === 'xlsx' || fileExt === 'xls' }">
                            <input type="file" name="hps_file" id="hps_file" accept=".xlsx,.xls,.csv,.pdf" required
                                   @change="handleFileSelect($event)"
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                            <div class="space-y-3 pointer-events-none">
                                <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm"
                                     :class="{
                                         'bg-sky-50 text-sky-600 border border-sky-200': fileExt === 'xlsx' || fileExt === 'xls',
                                         'bg-rose-50 text-rose-600 border border-rose-200': fileExt === 'pdf',
                                         'bg-cyan-50 text-cyan-600 border border-cyan-200': fileExt === 'csv',
                                         'bg-slate-100 text-slate-500 border border-slate-200': !fileExt
                                     }">
                                    <i data-lucide="upload-cloud" class="w-8 h-8"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">
                                        <span x-text="fileName ? fileName : 'Pilih berkas HPS atau seret file ke sini'"></span>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        Mendukung berkas <strong class="text-sky-600 font-bold">Excel (.xlsx, .xls)</strong>, <strong class="text-cyan-600 font-bold">CSV (.csv)</strong>, dan <strong class="text-rose-600 font-bold">PDF (.pdf)</strong> (Maks. 20 MB)
                                    </p>
                                </div>
                                <div x-show="fileSize" class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-mono bg-sky-100 text-sky-800">
                                    <span class="font-bold uppercase" x-text="fileExt"></span>
                                    <span>&bull;</span>
                                    <span x-text="fileSize"></span>
                                </div>
                            </div>
                        </div>
                        @error('hps_file')
                            <p class="text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 2. Parameter Konversi -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2 pb-2 border-b border-slate-100">
                            <span class="w-5 h-5 rounded-full bg-sky-100 text-sky-700 text-xs flex items-center justify-center font-bold">2</span>
                            <span>Parameter Konversi</span>
                        </h3>

                        <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4 space-y-3">
                            <div class="flex items-start gap-2.5">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-sky-600 mt-0.5 shrink-0"></i>
                                <p class="text-xs text-slate-700 leading-relaxed">
                                    Harga pada HPS sudah berupa <strong>DPP</strong>, sehingga disalin <strong>apa adanya</strong>
                                    tanpa dikonversi lagi.
                                </p>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-sky-600 mt-0.5 shrink-0"></i>
                                <p class="text-xs text-slate-700 leading-relaxed">
                                    Kolom <strong>Pajak(%)</strong> diisi <strong>11</strong> sebagai penanda tarif PPN.
                                </p>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-sky-600 mt-0.5 shrink-0"></i>
                                <p class="text-xs text-slate-700 leading-relaxed">
                                    Volume mengikuti HPS (2 desimal) dan Total disalin dari kolom <strong>Jumlah</strong> HPS.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="reset" @click="reset()" class="px-5 py-2.5 rounded-xl text-xs md:text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                            Bersihkan
                        </button>
                        <button type="submit" @click="submitForm($event)"
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs md:text-sm font-bold shadow-lg shadow-sky-600/30 transition-all hover:-translate-y-0.5 disabled:opacity-60">
                            <i data-lucide="sparkles" class="w-4 h-4"></i>
                            <span x-text="submitting ? 'Memproses...' : 'Proses & Unduh Template'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar Helper -->
        <div class="space-y-6">
            <div class="bg-gradient-to-br from-sky-900 to-slate-950 rounded-2xl p-6 text-white border border-sky-700/50 shadow-lg space-y-4">
                <div class="flex items-center gap-2.5 text-white font-bold text-sm">
                    <i data-lucide="table" class="w-5 h-5 text-sky-300"></i>
                    <span>Template Resmi SPSE</span>
                </div>
                <p class="text-xs text-slate-200 leading-relaxed">
                    Berkas keluaran memakai template resmi SPSE <span class="font-mono text-white">template-rincian.xlsx</span> (sheet
                    <span class="font-mono text-white">rincian-hps</span>). Header, format, dan dropdown kolom Kunci milik SPSE dipertahankan,
                    data hasil konversi hanya ditambahkan mulai baris kedua.
                </p>
                <ul class="text-xs text-slate-200 space-y-2.5">
                    <li class="flex items-start gap-2">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-sky-400 mt-0.5 shrink-0"></i>
                        <span><strong class="text-white font-bold">Jenis Barang/Jasa</strong> &mdash; diambil dari uraian / nama item HPS</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-sky-400 mt-0.5 shrink-0"></i>
                        <span><strong class="text-white font-bold">Satuan</strong> &mdash; disalin dari satuan HPS</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-sky-400 mt-0.5 shrink-0"></i>
                        <span><strong class="text-white font-bold">Vol</strong> &mdash; volume HPS, dibulatkan 2 desimal</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-sky-400 mt-0.5 shrink-0"></i>
                        <span><strong class="text-white font-bold">Harga</strong> &mdash; disalin apa adanya dari HPS (sudah DPP)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-sky-400 mt-0.5 shrink-0"></i>
                        <span><strong class="text-white font-bold">Pajak(%)</strong> &mdash; tarif PPN sebagai penanda (11)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-sky-400 mt-0.5 shrink-0"></i>
                        <span><strong class="text-white font-bold">Keterangan</strong> &mdash; kosong, siap diisi manual</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-sky-400 mt-0.5 shrink-0"></i>
                        <span><strong class="text-white font-bold">Kunci</strong> &mdash; dropdown SPSE (TRUE/FALSE), siap diisi manual</span>
                    </li>
                </ul>
            </div>

            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-sky-600"></i>
                    <span>Catatan Penting</span>
                </h4>
                <ul class="text-xs text-slate-600 space-y-2.5 list-disc list-inside">
                    <li>Baris judul kolom HPS dideteksi otomatis, sehingga letak header tidak harus di baris pertama.</li>
                    <li>Baris <em>Total</em>, <em>Sub Total</em>, dan <em>Rekapitulasi</em> otomatis dilewati.</li>
                    <li>Bila kolom harga satuan kosong namun kolom jumlah terisi, harga satuan dihitung dari jumlah dibagi volume.</li>
                    <li>Kolom Keterangan dan Kunci dibiarkan kosong agar dapat diisi sesuai kebutuhan dokumen Anda.</li>
                    <li>Kolom Harga berisi DPP. Paste ke kolom <strong>DPP</strong> SPSE dan jangan hitung PPN ulang agar PPN tidak terpotong dua kali.</li>
                    <li>SPSE membulatkan volume ke 2 desimal; volume pada template sudah mengikuti HPS sehingga total konsisten.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function hpsConverter() {
    return {
        fileName: '',
        fileSize: '',
        fileExt: '',
        submitting: false,
        submitForm(event) {
            const form = event.target.closest('form');
            if (!form) return;

            // Biarkan validasi HTML5 berjalan lebih dulu.
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }

            this.submitting = true;
            setTimeout(() => {
                this.submitting = false;
            }, 5000);
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
                this.reset();
            }
        },
        reset() {
            this.fileName = '';
            this.fileSize = '';
            this.fileExt = '';
            this.submitting = false;
        }
    };
}
</script>
@endpush
@endsection
