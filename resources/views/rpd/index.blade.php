@extends('layouts.app')

@section('title', 'Pecah Bahan RPD & Generator Isian BKU - Si Revita Situbondo')

@section('content')
<div class="space-y-6">
    <!-- Header Banner -->
    <div class="relative overflow-hidden bg-gradient-to-r from-emerald-800 via-teal-800 to-cyan-900 rounded-2xl shadow-xl border border-emerald-700/40 p-6 md:p-8 text-white">
        <div class="absolute -right-12 -top-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-32 -bottom-16 w-56 h-56 bg-teal-400/10 rounded-full blur-2xl pointer-events-none"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-200 text-xs font-semibold backdrop-blur-md">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-300"></i>
                    <span>Fitur Pintar Pecah Bahan Otomatis</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white flex items-center gap-3">
                    <i data-lucide="layers" class="w-8 h-8 text-emerald-300"></i>
                    <span>Pecah Bahan RPD & Isian BKU</span>
                </h1>
                <p class="text-sm md:text-base text-emerald-100/90 leading-relaxed">
                    Unggah dokumen <strong class="text-white">RPD (Rencana Penarikan / Penggunaan Dana)</strong> format Excel/CSV. Sistem secara otomatis memecah bahan material, memisahkan upah tukang & alat, menghitung potongan pajak PPN/PPh, serta menghasilkan draf transaksi yang <strong class="text-amber-300">langsung siap dibukukan ke BKU, BPK, BB, dan BP</strong>.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('rpd.template', ['format' => 'xlsx']) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs md:text-sm font-semibold border border-white/20 backdrop-blur-md transition-all shadow-sm hover:shadow">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-300"></i>
                    <span>Unduh Template Excel</span>
                </a>
                <a href="{{ route('rpd.template', ['format' => 'csv']) }}" 
                   class="inline-flex items-center gap-2 px-3.5 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs md:text-sm font-semibold border border-white/20 backdrop-blur-md transition-all shadow-sm hover:shadow">
                    <i data-lucide="download" class="w-4 h-4 text-teal-300"></i>
                    <span>CSV</span>
                </a>
                @role('root,admin')
                <a href="{{ route('rpd.create') }}" 
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-900 rounded-xl text-xs md:text-sm font-bold shadow-lg shadow-amber-500/30 transition-all hover:-translate-y-0.5">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                    <span>Upload File RPD</span>
                </a>
                @endrole
            </div>
        </div>
    </div>

    <!-- Statistic Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Dokumen RPD</p>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['total_docs']) }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Dokumen diunggah</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 flex items-center justify-center text-blue-600 dark:text-blue-400">
                <i data-lucide="files" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Nilai RPD</p>
                <h3 class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">Rp {{ number_format($stats['total_budget'], 0, ',', '.') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Akumulasi anggaran RPD</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                <i data-lucide="badge-dollar-sign" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Siap Diposting (Dianalisis)</p>
                <h3 class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ number_format($stats['ready_count']) }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Menunggu posting BKU</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <i data-lucide="clock" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sudah di BKU</p>
                <h3 class="text-2xl font-bold text-teal-600 dark:text-teal-400 mt-1">{{ number_format($stats['posted_count']) }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Telah menjadi transaksi resmi</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-teal-50 dark:bg-teal-950/60 border border-teal-200 dark:border-teal-800 flex items-center justify-center text-teal-600 dark:text-teal-400">
                <i data-lucide="check-circle-2" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Filter & Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <!-- Filter Bar -->
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
            <form action="{{ route('rpd.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Sekolah</label>
                    <select name="school_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">-- Semua Sekolah --</option>
                        @foreach($schools as $sch)
                            <option value="{{ $sch->id }}" {{ request('school_id') == $sch->id ? 'selected' : '' }}>{{ $sch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Proyek Revitalisasi</label>
                    <select name="project_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">-- Semua Paket Proyek --</option>
                        @foreach($projects as $prj)
                            <option value="{{ $prj->id }}" {{ request('project_id') == $prj->id ? 'selected' : '' }}>{{ $prj->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Status Dokumen</label>
                    <select name="status" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">-- Semua Status --</option>
                        <option value="analyzed" {{ request('status') == 'analyzed' ? 'selected' : '' }}>Siap Posting (Dianalisis)</option>
                        <option value="posted_to_bku" {{ request('status') == 'posted_to_bku' ? 'selected' : '' }}>Terposting ke BKU</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition-all">
                        <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                        <span>Filter</span>
                    </button>
                    <a href="{{ route('rpd.index') }}" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-medium">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs md:text-sm">
                <thead>
                    <tr class="bg-slate-100/75 dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700 font-semibold">
                        <th class="py-3 px-4 w-12 text-center">No</th>
                        <th class="py-3 px-4">Dokumen RPD & Tahap</th>
                        <th class="py-3 px-4">Sekolah & Paket Revitalisasi</th>
                        <th class="py-3 px-4 text-center">Rincian Item</th>
                        <th class="py-3 px-4 text-right">Total Anggaran RPD</th>
                        <th class="py-3 px-4 text-center">Pecah Bahan & Upah</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse($documents as $index => $doc)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="py-3.5 px-4 text-center text-slate-500 font-mono">
                                {{ $documents->firstItem() + $index }}
                            </td>
                            <td class="py-3.5 px-4">
                                <a href="{{ route('rpd.show', $doc->id) }}" class="font-bold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 hover:underline flex items-center gap-1.5">
                                    <i data-lucide="file-text" class="w-4 h-4 flex-shrink-0"></i>
                                    <span>{{ $doc->title }}</span>
                                </a>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        {{ $doc->term_stage }}
                                    </span>
                                    <span>File: <code class="font-mono">{{ $doc->file_name }}</code></span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-slate-900 dark:text-white">{{ $doc->school->name }}</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $doc->project->title }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                                    {{ $doc->items_count }} Baris
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                Rp {{ number_format($doc->total_budget, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="inline-flex flex-col gap-1 text-[11px] text-left">
                                    <span class="text-emerald-600 dark:text-emerald-400 font-medium">
                                        • Bahan: Rp {{ number_format($doc->total_materials, 0, ',', '.') }}
                                    </span>
                                    <span class="text-blue-600 dark:text-blue-400 font-medium">
                                        • Upah: Rp {{ number_format($doc->total_wages, 0, ',', '.') }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                {!! $doc->status_badge !!}
                                @if($doc->posted_at)
                                    <div class="text-[10px] text-slate-400 mt-1">
                                        {{ $doc->posted_at->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('rpd.show', $doc->id) }}" 
                                       title="Lihat Hasil Pecah Bahan & Isian BKU"
                                       class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition-colors">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </a>

                                    <a href="{{ route('rpd.export_bku', ['rpd' => $doc->id, 'format' => 'xlsx']) }}" 
                                       title="Unduh Format Isian BKU (Excel)"
                                       class="p-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:hover:bg-teal-900/60 dark:text-teal-300 border border-teal-200 dark:border-teal-800 transition-colors">
                                        <i data-lucide="download" class="w-4 h-4"></i>
                                    </a>

                                    @role('root,admin')
                                    @if($doc->status !== 'posted_to_bku')
                                    <form action="{{ route('rpd.destroy', $doc->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus draf RPD ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                title="Hapus Dokumen"
                                                class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:hover:bg-rose-900/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800 transition-colors">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                    @endif
                                    @endrole
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="max-w-sm mx-auto space-y-3">
                                    <div class="w-16 h-16 mx-auto rounded-full bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <i data-lucide="layers" class="w-8 h-8"></i>
                                    </div>
                                    <h4 class="text-base font-bold text-slate-800 dark:text-slate-200">Belum Ada Dokumen RPD</h4>
                                    <p class="text-xs text-slate-500">
                                        Unggah file RPD (Rencana Penarikan Dana) sekolah Anda untuk memecah rincian belanja bahan & otomatis menghasilkan draf isian Buku Kas Umum (BKU).
                                    </p>
                                    @role('root,admin')
                                    <div class="pt-2">
                                        <a href="{{ route('rpd.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold">
                                            <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                                            <span>Mulai Unggah RPD Sekarang</span>
                                        </a>
                                    </div>
                                    @endrole
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($documents->hasPages())
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $documents->links() }}
        </div>
        @endif
    </div>

    <!-- How It Works / SOP Penjelasan Pecah Bahan -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-800 rounded-2xl p-6 text-white border border-slate-700/60 shadow-lg">
        <h3 class="text-base font-bold text-emerald-400 flex items-center gap-2 mb-3">
            <i data-lucide="info" class="w-5 h-5"></i>
            <span>Alur Kerja: Dari Dokumen RPD Menjadi Isian Buku Kas Umum (BKU)</span>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
            <div class="bg-white/5 rounded-xl p-4 border border-white/10 space-y-1.5">
                <div class="w-6 h-6 rounded-full bg-emerald-500 text-slate-950 font-bold flex items-center justify-center text-xs">1</div>
                <h5 class="font-bold text-white text-sm">Unggah RPD</h5>
                <p class="text-slate-300 leading-relaxed">Pilih sekolah, proyek, dan file RPD (Excel/CSV) berisi rincian item belanja bahan, upah, dan alat per termin.</p>
            </div>
            <div class="bg-white/5 rounded-xl p-4 border border-white/10 space-y-1.5">
                <div class="w-6 h-6 rounded-full bg-emerald-500 text-slate-950 font-bold flex items-center justify-center text-xs">2</div>
                <h5 class="font-bold text-white text-sm">Otomasi Pecah Bahan</h5>
                <p class="text-slate-300 leading-relaxed">Sistem mendeteksi kategori material bangunan, upah HOK tenaga kerja, sewa alat, serta menghitung PPN 11% & PPh 22/21.</p>
            </div>
            <div class="bg-white/5 rounded-xl p-4 border border-white/10 space-y-1.5">
                <div class="w-6 h-6 rounded-full bg-emerald-500 text-slate-950 font-bold flex items-center justify-center text-xs">3</div>
                <h5 class="font-bold text-white text-sm">Review Kandidat BKU</h5>
                <p class="text-slate-300 leading-relaxed">Periksa tabel output isian BKU, sesuaikan nama toko rekanan Situbondo, tanggal belanja, dan cara bayar (Kas/Bank).</p>
            </div>
            <div class="bg-white/5 rounded-xl p-4 border border-white/10 space-y-1.5">
                <div class="w-6 h-6 rounded-full bg-emerald-500 text-slate-950 font-bold flex items-center justify-center text-xs">4</div>
                <h5 class="font-bold text-white text-sm">Posting ke BKU Resmi</h5>
                <p class="text-slate-300 leading-relaxed">Klik <strong>"Posting ke BKU"</strong> dan transaksi langsung tercatat resmi di BKU, Kas Tunai (BPK), Bank (BB), Pajak (BP), dan Kuitansi!</p>
            </div>
        </div>
    </div>
</div>
@endsection

