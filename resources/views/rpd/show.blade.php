@extends('layouts.app')

@section('title', 'Output Pecah Bahan RPD (Isian BKU) - Si Revita Situbondo')

@section('content')
<div class="space-y-6" x-data="rpdViewer()">
    <!-- Top Action & Navigation Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('rpd.index') }}" class="hover:text-emerald-600">Pecah Bahan RPD</a>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span class="text-slate-800 font-medium">Output Isian BKU</span>
            </nav>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="layers" class="w-7 h-7 text-emerald-600"></i>
                    <span>{{ $rpd->title }}</span>
                </h1>
                {!! $rpd->status_badge !!}
            </div>
            <p class="text-xs md:text-sm text-slate-500 mt-1">
                {{ $rpd->school->name }} &bull; {{ $rpd->project->title }} ({{ $rpd->term_stage }})
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Export Options -->
            <div class="inline-flex rounded-xl shadow-sm border border-slate-200 bg-white p-1">
                <a href="{{ route('rpd.export_bku', ['rpd' => $rpd->id, 'format' => 'xlsx']) }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition-colors">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-600"></i>
                    <span>Export Excel BKU</span>
                </a>
                <div class="w-px bg-slate-200 my-1"></div>
                <a href="{{ route('rpd.export_bku', ['rpd' => $rpd->id, 'format' => 'csv']) }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 hover:bg-teal-50 hover:text-teal-700 transition-colors">
                    <i data-lucide="download" class="w-4 h-4 text-teal-600"></i>
                    <span>CSV</span>
                </a>
            </div>

            @if($rpd->status === 'posted_to_bku')
                <!-- Link Langsung ke BKU Resmi -->
                <a href="{{ route('reports.bku', ['project_id' => $rpd->project_id]) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs md:text-sm font-bold shadow-md shadow-emerald-600/20 transition-all">
                    <i data-lucide="book-open-check" class="w-4 h-4"></i>
                    <span>Lihat di BKU Resmi</span>
                </a>
            @else
                @role('root,admin')
                <!-- Tombol Posting ke BKU Resmi -->
                <form action="{{ route('rpd.post_bku', $rpd->id) }}" method="POST" 
                      onsubmit="return confirm('Apakah Anda yakin ingin memposting seluruh {{ $items->count() }} item pecahan bahan ini ke Buku Kas Umum (BKU)? Transaksi resmi akan otomatis dibuat.')">
                    @csrf
                    <button type="submit" 
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-xs md:text-sm font-bold shadow-lg shadow-emerald-600/30 transition-all hover:-translate-y-0.5">
                        <i data-lucide="check-check" class="w-4 h-4"></i>
                        <span>Posting ke BKU Sekarang</span>
                    </button>
                </form>
                @endrole
            @endif
        </div>
    </div>

    @if($rpd->status === 'posted_to_bku')
    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-900 flex items-center justify-between gap-4 text-xs md:text-sm">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-500 text-white flex items-center justify-center font-bold">
                <i data-lucide="badge-check" class="w-5 h-5"></i>
            </div>
            <div>
                <strong>Semua item telah dibukukan ke Buku Kas Umum (BKU)!</strong>
                <p class="text-xs text-emerald-700 mt-0.5">
                    Diposting pada {{ $rpd->posted_at?->format('d F Y - H:i') }}. Anda dapat melihat kuitansi, buku pembantu kas (BPK), bank (BB), dan pajak (BP).
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.bku', ['project_id' => $rpd->project_id]) }}" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs whitespace-nowrap">
                Buka BKU &rarr;
            </a>
            <a href="{{ route('reports.kuitansi') }}" class="px-3 py-1.5 rounded-lg bg-white text-emerald-800 border border-emerald-300 font-semibold text-xs whitespace-nowrap">
                Cetak Kuitansi &rarr;
            </a>
        </div>
    </div>
    @endif

    <!-- Statistic Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- 1. Total RPD -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total RPD Bruto</p>
            <h4 class="text-base font-extrabold text-slate-900 mt-1">
                Rp {{ number_format($summary['total_budget'], 0, ',', '.') }}
            </h4>
            <span class="text-[11px] text-slate-400">{{ $summary['total_items'] }} baris item</span>
        </div>

        <!-- 2. Belanja Bahan -->
        <div class="bg-white rounded-xl p-4 border border-emerald-200 shadow-sm">
            <p class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider">Bahan / Material</p>
            <h4 class="text-base font-extrabold text-emerald-600 mt-1">
                Rp {{ number_format($summary['total_materials'], 0, ',', '.') }}
            </h4>
            <span class="text-[11px] text-slate-400">{{ $items->where('category', 'bahan')->count() }} transaksi material</span>
        </div>

        <!-- 3. Upah Tenaga -->
        <div class="bg-white rounded-xl p-4 border border-blue-200 shadow-sm">
            <p class="text-[11px] font-bold text-blue-600 uppercase tracking-wider">Upah Tenaga Kerja</p>
            <h4 class="text-base font-extrabold text-blue-600 mt-1">
                Rp {{ number_format($summary['total_wages'], 0, ',', '.') }}
            </h4>
            <span class="text-[11px] text-slate-400">{{ $items->where('category', 'upah')->count() }} pos upah tukang</span>
        </div>

        <!-- 4. Alat & Operasional -->
        <div class="bg-white rounded-xl p-4 border border-amber-200 shadow-sm">
            <p class="text-[11px] font-bold text-amber-600 uppercase tracking-wider">Alat & Operasional</p>
            <h4 class="text-base font-extrabold text-amber-600 mt-1">
                Rp {{ number_format($summary['total_equipment'] + $summary['total_operational'], 0, ',', '.') }}
            </h4>
            <span class="text-[11px] text-slate-400">{{ $items->whereIn('category', ['alat', 'operasional'])->count() }} baris</span>
        </div>

        <!-- 5. Potongan Pajak -->
        <div class="bg-white rounded-xl p-4 border border-rose-200 shadow-sm">
            <p class="text-[11px] font-bold text-rose-600 uppercase tracking-wider">Pajak (PPN & PPh)</p>
            <h4 class="text-base font-extrabold text-rose-600 mt-1">
                Rp {{ number_format($summary['total_tax'], 0, ',', '.') }}
            </h4>
            <span class="text-[11px] text-rose-500/80">PPN: {{ number_format($summary['total_tax_ppn'], 0, ',', '.') }}</span>
        </div>

        <!-- 6. Jumlah Bersih -->
        <div class="bg-white rounded-xl p-4 border border-teal-200 shadow-sm">
            <p class="text-[11px] font-bold text-teal-600 uppercase tracking-wider">Dibayarkan Bersih</p>
            <h4 class="text-base font-extrabold text-teal-600 mt-1">
                Rp {{ number_format($summary['total_net'], 0, ',', '.') }}
            </h4>
            <span class="text-[11px] text-slate-400">Netto ke rekanan</span>
        </div>
    </div>

    <!-- Saluran Kas: Kas Tunai vs Bank Jatim Card -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 rounded-xl bg-teal-50/60 border border-teal-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-teal-600 text-white flex items-center justify-center font-bold">
                    <i data-lucide="wallet" class="w-5 h-5"></i>
                </div>
                <div>
                    <h5 class="text-xs font-bold uppercase text-teal-900">Buku Pembantu Kas Tunai (BPK)</h5>
                    <p class="text-xs text-teal-700">{{ $summary['cash_count'] }} transaksi belanja tunai</p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-base font-mono font-bold text-teal-900">
                    Rp {{ number_format($summary['cash_amount'], 0, ',', '.') }}
                </div>
                <span class="text-[10px] text-teal-600">&le; Rp 5.000.000</span>
            </div>
        </div>

        <div class="p-4 rounded-xl bg-indigo-50/60 border border-indigo-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold">
                    <i data-lucide="building-2" class="w-5 h-5"></i>
                </div>
                <div>
                    <h5 class="text-xs font-bold uppercase text-indigo-900">Buku Pembantu Bank Jatim (BB)</h5>
                    <p class="text-xs text-indigo-700">{{ $summary['bank_count'] }} transaksi transfer bank</p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-base font-mono font-bold text-indigo-900">
                    Rp {{ number_format($summary['bank_amount'], 0, ',', '.') }}
                </div>
                <span class="text-[10px] text-indigo-600">&gt; Rp 5.000.000 / Non-Tunai</span>
            </div>
        </div>
    </div>

    <!-- Output Pecah Bahan Table (Isian BKU) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Table Toolbar -->
        <div class="p-4 border-b border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="table-2" class="w-4 h-4 text-emerald-600"></i>
                    <span>Tabel Output Pecah Bahan (Rincian Siap Jadi Isian BKU)</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Berikut adalah rincian transaksi belanja yang telah diuraikan dari file RPD lengkap dengan narasi BKU, potongan pajak, dan buku kas.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <!-- Filter Kategori Tabs -->
                <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-xs">
                    <button type="button" @click="categoryFilter = 'all'" 
                            :class="categoryFilter === 'all' ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-600 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-md transition-all">
                        Semua ({{ $items->count() }})
                    </button>
                    <button type="button" @click="categoryFilter = 'bahan'" 
                            :class="categoryFilter === 'bahan' ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-600 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-md transition-all">
                        Bahan ({{ $items->where('category', 'bahan')->count() }})
                    </button>
                    <button type="button" @click="categoryFilter = 'upah'" 
                            :class="categoryFilter === 'upah' ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-600 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-md transition-all">
                        Upah ({{ $items->where('category', 'upah')->count() }})
                    </button>
                    <button type="button" @click="categoryFilter = 'lainnya'" 
                            :class="categoryFilter === 'lainnya' ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-600 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-md transition-all">
                        Alat & Ops
                    </button>
                </div>
            </div>
        </div>

        <!-- Table Responsive Container -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs md:text-sm">
                <thead>
                    <tr class="bg-slate-100/80 text-slate-700 border-b border-slate-200 font-semibold">
                        <th class="py-3 px-3 text-center w-12">No</th>
                        <th class="py-3 px-3 whitespace-nowrap">Tgl Rencana BKU</th>
                        <th class="py-3 px-3 whitespace-nowrap">Buku Kas</th>
                        <th class="py-3 px-3 whitespace-nowrap">Kategori</th>
                        <th class="py-3 px-4 min-w-[280px]">Uraian Belanja & Narasi Baku BKU</th>
                        <th class="py-3 px-3 text-right whitespace-nowrap">Vol & Satuan</th>
                        <th class="py-3 px-3 text-right whitespace-nowrap">Harga Satuan</th>
                        <th class="py-3 px-3 text-right whitespace-nowrap font-bold text-slate-900">Pengeluaran Bruto</th>
                        <th class="py-3 px-3 text-right whitespace-nowrap text-rose-600">PPN (11%)</th>
                        <th class="py-3 px-3 text-right whitespace-nowrap text-rose-600">PPh (22/21)</th>
                        <th class="py-3 px-3 text-right whitespace-nowrap font-bold text-teal-600">Dibayar Bersih</th>
                        <th class="py-3 px-3 whitespace-nowrap">Penerima / Toko</th>
                        <th class="py-3 px-3 text-center whitespace-nowrap">Status</th>
                        @if($rpd->status !== 'posted_to_bku')
                        <th class="py-3 px-3 text-center w-16">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $idx => $it)
                        <tr x-show="categoryFilter === 'all' || (categoryFilter === 'bahan' && '{{ $it->category }}' === 'bahan') || (categoryFilter === 'upah' && '{{ $it->category }}' === 'upah') || (categoryFilter === 'lainnya' && ('{{ $it->category }}' === 'alat' || '{{ $it->category }}' === 'operasional'))"
                            class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-3 text-center text-slate-500 font-mono">
                                {{ $idx + 1 }}
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap font-mono text-slate-800">
                                {{ $it->planned_date->format('d/m/Y') }}
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                {!! $it->payment_method_badge !!}
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                {!! $it->category_badge !!}
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900">{{ $it->item_name }}</div>
                                @if($it->specification)
                                    <div class="text-[11px] text-slate-500">Spek: {{ $it->specification }}</div>
                                @endif
                                <div class="text-[11px] text-emerald-700 mt-1 italic bg-emerald-50/60 p-1 rounded border border-emerald-100">
                                    &ldquo;{{ $it->bku_description }}&rdquo;
                                </div>
                            </td>
                            <td class="py-3 px-3 text-right font-mono whitespace-nowrap">
                                {{ number_format($it->volume, $it->volume == (int)$it->volume ? 0 : 2, ',', '.') }} {{ $it->unit }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono whitespace-nowrap text-slate-600">
                                Rp {{ number_format($it->unit_price, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold whitespace-nowrap text-slate-900">
                                Rp {{ number_format($it->total_price, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono whitespace-nowrap text-rose-600">
                                {{ $it->tax_ppn > 0 ? 'Rp ' . number_format($it->tax_ppn, 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono whitespace-nowrap text-rose-600">
                                @php $pph = $it->tax_pph22 + $it->tax_pph21; @endphp
                                {{ $pph > 0 ? 'Rp ' . number_format($pph, 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold whitespace-nowrap text-teal-600">
                                Rp {{ number_format($it->net_amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap text-slate-700">
                                <div class="font-medium">{{ $it->supplier_name ?: '-' }}</div>
                            </td>
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                @if($it->is_posted)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                                        {{ $it->bku_number }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">
                                        Draf Isian
                                    </span>
                                @endif
                            </td>
                            @if($rpd->status !== 'posted_to_bku')
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                @role('root,admin')
                                <button type="button" @click="openEditModal({{ json_encode($it) }})" 
                                        title="Edit Baris Pecah Bahan"
                                        class="p-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                </button>
                                @endrole
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="py-8 text-center text-slate-500">
                                Belum ada baris pecahan bahan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100/90 font-bold border-t-2 border-slate-300">
                        <td colspan="7" class="py-3.5 px-4 text-right uppercase text-xs text-slate-700">
                            Total Rekapitulasi Isian BKU:
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono text-slate-900">
                            Rp {{ number_format($summary['total_budget'], 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono text-rose-600">
                            Rp {{ number_format($summary['total_tax_ppn'], 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono text-rose-600">
                            Rp {{ number_format($summary['total_tax_pph22'] + $summary['total_tax_pph21'], 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono text-teal-600">
                            Rp {{ number_format($summary['total_net'], 0, ',', '.') }}
                        </td>
                        <td colspan="{{ $rpd->status !== 'posted_to_bku' ? 3 : 2 }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Edit Row Modal (Alpine.js) -->
    <div x-show="showEdit" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-xl w-full border border-slate-200 shadow-2xl overflow-hidden"
             @click.away="showEdit = false">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h4 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="edit" class="w-4 h-4 text-emerald-600"></i>
                    <span>Sesuaikan Baris Pecah Bahan BKU</span>
                </h4>
                <button type="button" @click="showEdit = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form :action="'{{ url('/rpd/items') }}/' + editingItem.id" method="POST" class="p-5 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama / Uraian Bahan</label>
                    <input type="text" name="item_name" x-model="editingItem.item_name" required
                           class="w-full text-xs rounded-lg border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Kategori</label>
                        <select name="category" x-model="editingItem.category" required
                                class="w-full text-xs rounded-lg border border-slate-300 bg-white text-slate-800 font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                            <option value="bahan" class="bg-white text-slate-800 py-1">Bahan / Material</option>
                            <option value="upah" class="bg-white text-slate-800 py-1">Upah Tenaga Kerja</option>
                            <option value="alat" class="bg-white text-slate-800 py-1">Alat Kerja</option>
                            <option value="operasional" class="bg-white text-slate-800 py-1">Operasional / SPJ</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Saluran Pembayaran</label>
                        <select name="payment_method" x-model="editingItem.payment_method" required
                                class="w-full text-xs rounded-lg border border-slate-300 bg-white text-slate-800 font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                            <option value="belanja_tunai" class="bg-white text-slate-800 py-1">Kas Tunai (BPK)</option>
                            <option value="belanja_transfer" class="bg-white text-slate-800 py-1">Bank Jatim (BB)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Volume</label>
                        <input type="number" step="0.01" name="volume" x-model="editingItem.volume" required
                               class="w-full text-xs rounded-lg border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Satuan</label>
                        <input type="text" name="unit" x-model="editingItem.unit" required
                               class="w-full text-xs rounded-lg border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Harga Satuan (Rp)</label>
                        <input type="number" step="1" name="unit_price" x-model="editingItem.unit_price" required
                               class="w-full text-xs rounded-lg border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tgl Rencana BKU</label>
                        <input type="date" name="planned_date" x-model="editingItem.planned_date" required
                               class="w-full text-xs rounded-lg border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Penerima / Toko Rekanan</label>
                        <input type="text" name="supplier_name" x-model="editingItem.supplier_name"
                               class="w-full text-xs rounded-lg border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="has_tax" id="modal_has_tax" value="1" x-model="editingItem.has_tax"
                           class="rounded text-emerald-600 focus:ring-emerald-500">
                    <label for="modal_has_tax" class="text-xs font-semibold text-slate-700">
                        Kena Potongan Pajak PPN & PPh (Otomatis jika &ge; Rp 2 Juta)
                    </label>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end gap-2">
                    <button type="button" @click="showEdit = false" class="px-4 py-2 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function rpdViewer() {
    return {
        categoryFilter: 'all',
        showEdit: false,
        editingItem: {},
        openEditModal(item) {
            this.editingItem = { ...item };
            // Format tgl yyyy-mm-dd untuk input type=date
            if (this.editingItem.planned_date && this.editingItem.planned_date.length > 10) {
                this.editingItem.planned_date = this.editingItem.planned_date.substring(0, 10);
            }
            this.showEdit = true;
        }
    };
}
</script>
@endpush
@endsection

