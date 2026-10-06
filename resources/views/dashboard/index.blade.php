@extends('layouts.app')

@section('title', 'Dashboard Eksekutif Revitalisasi')

@section('content')
<div class="space-y-6">

    <!-- Top Greeting & School Filter Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="flex items-center gap-4">
            <img src="{{ asset('images/logo.png') }}" 
                 alt="Logo Si Revita Situbondo" 
                 class="w-14 h-14 object-contain rounded-2xl p-1 bg-slate-50 ring-1 ring-emerald-500/30 shrink-0">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-0.5">
                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                    <span>Sistem Informasi Revitalisasi dan Pelaporan Keuangan</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                    Selamat Datang, {{ auth()->user()->name }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                    Monitoring pelaksanaan revitalisasi dan pembukuan keuangan sekolah se-Kabupaten Situbondo TA 2026.
                </p>
            </div>
        </div>

        <!-- School Filter (for Root or Global User) -->
        <form action="{{ route('dashboard') }}" method="GET" class="flex items-center gap-2">
            <div class="relative">
                <select name="school_id" onchange="this.form.submit()" 
                        class="text-xs font-semibold pl-3 pr-8 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="">-- Seluruh Sekolah Situbondo --</option>
                    @foreach($schools as $sch)
                        <option value="{{ $sch->id }}" {{ $selectedSchoolId == $sch->id ? 'selected' : '' }}>
                            {{ $sch->name }} ({{ $sch->jenjang }})
                        </option>
                    @endforeach
                </select>
            </div>
            @if($selectedSchoolId)
                <a href="{{ route('dashboard') }}" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100" title="Reset Filter">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            @endif
        </form>
    </div>

    <!-- 4 Main KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Pagu Total -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Pagu Revitalisasi</span>
                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xl sm:text-2xl font-extrabold text-slate-900">
                    @rupiah($totalBudget)
                </div>
                <div class="flex items-center gap-1.5 mt-1 text-xs text-slate-500">
                    <span class="font-semibold text-blue-600">{{ $totalProjects }} Paket Kegiatan</span>
                    <span>di Situbondo</span>
                </div>
            </div>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
        </div>

        <!-- Realisasi Keuangan -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Realisasi Keuangan</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xl sm:text-2xl font-extrabold text-emerald-600">
                    @rupiah($totalRealization)
                </div>
                <div class="flex items-center gap-2 mt-1 text-xs text-slate-500">
                    <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold font-mono">
                        {{ $financialPercentage }}%
                    </span>
                    <span>Serapan Anggaran</span>
                </div>
            </div>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
        </div>

        <!-- Sisa Anggaran -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Sisa Pagu Anggaran</span>
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i data-lucide="pie-chart" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xl sm:text-2xl font-extrabold text-slate-900">
                    @rupiah($remainingBudget)
                </div>
                <div class="flex items-center gap-1.5 mt-1 text-xs text-slate-500">
                    <span class="font-semibold text-amber-600">{{ round(100 - $financialPercentage, 1) }}%</span>
                    <span>Tersisa untuk diserap</span>
                </div>
            </div>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-amber-500 to-orange-500"></div>
        </div>

        <!-- Saldo Kas & Bank -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Posisi Kas & Bank</span>
                <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <i data-lucide="vault" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="mt-2.5 space-y-1">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 flex items-center gap-1"><i data-lucide="wallet" class="w-3 h-3 text-amber-500"></i> Kas Tunai (BPK):</span>
                    <span class="font-bold text-slate-900">@rupiah($saldoKasTunai)</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 flex items-center gap-1"><i data-lucide="building-2" class="w-3 h-3 text-sky-500"></i> Bank Jatim (BB):</span>
                    <span class="font-bold text-slate-900">@rupiah($saldoBank)</span>
                </div>
            </div>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-purple-500 to-pink-500"></div>
        </div>

    </div>

    <!-- Quick Navigation Hub to 4 Core Output Books -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 rounded-2xl p-6 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <i data-lucide="file-spreadsheet" class="w-5 h-5 text-emerald-400"></i>
                    Output 4 Buku Utama Laporan Keuangan Revitalisasi
                </h3>
                <p class="text-xs text-slate-300">
                    Akses cepat dan cetak buku laporan standar Dinas Pendidikan dan Kebudayaan Kab. Situbondo.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('panduan.index') }}" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white font-semibold text-xs flex items-center gap-1.5 transition-all border border-slate-700">
                    <i data-lucide="book-open" class="w-3.5 h-3.5 text-emerald-400"></i>
                    <span>Buku Panduan (PDF)</span>
                </a>
                <a href="{{ route('reports.bku') }}" class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs flex items-center gap-1.5 transition-all shadow-md shadow-emerald-500/20">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                    Buka BKU Sekarang
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="{{ route('reports.bku') }}" class="p-3.5 rounded-xl bg-slate-800/80 border border-slate-700 hover:border-emerald-500/50 hover:bg-slate-800 transition-all group">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <i data-lucide="book-open" class="w-4 h-4"></i>
                </div>
                <div class="font-bold text-sm text-white">BKU</div>
                <div class="text-[11px] text-slate-400">Buku Kas Umum</div>
            </a>

            <a href="{{ route('reports.bpk') }}" class="p-3.5 rounded-xl bg-slate-800/80 border border-slate-700 hover:border-amber-500/50 hover:bg-slate-800 transition-all group">
                <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <i data-lucide="wallet" class="w-4 h-4"></i>
                </div>
                <div class="font-bold text-sm text-white">BPK</div>
                <div class="text-[11px] text-slate-400">Buku Pembantu Kas</div>
            </a>

            <a href="{{ route('reports.bb') }}" class="p-3.5 rounded-xl bg-slate-800/80 border border-slate-700 hover:border-sky-500/50 hover:bg-slate-800 transition-all group">
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <i data-lucide="building-2" class="w-4 h-4"></i>
                </div>
                <div class="font-bold text-sm text-white">BB</div>
                <div class="text-[11px] text-slate-400">Buku Pembantu Bank</div>
            </a>

            <a href="{{ route('reports.bp') }}" class="p-3.5 rounded-xl bg-slate-800/80 border border-slate-700 hover:border-rose-500/50 hover:bg-slate-800 transition-all group">
                <div class="w-8 h-8 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <i data-lucide="receipt-tax" class="w-4 h-4"></i>
                </div>
                <div class="font-bold text-sm text-white">BP</div>
                <div class="text-[11px] text-slate-400">Buku Pembantu Pajak</div>
            </a>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Chart 1: Progres Fisik vs Keuangan -->
        <div class="lg:col-span-8 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-bold text-sm text-slate-900">Perbandingan Progres Fisik Lapangan vs Serapan Keuangan</h3>
                    <p class="text-xs text-slate-500">Evaluasi keselarasan kemajuan fisik konstruksi dengan pencairan dana</p>
                </div>
                <span class="text-xs px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 font-semibold">Sekolah Situbondo</span>
            </div>
            <div class="h-64 sm:h-72">
                <canvas id="progressChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Komposisi Realisasi Belanja Kategori -->
        <div class="lg:col-span-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col">
            <div class="mb-4">
                <h3 class="font-bold text-sm text-slate-900">Distribusi Pos Belanja (RAB)</h3>
                <p class="text-xs text-slate-500">Proporsi alokasi pagu menurut jenis belanja</p>
            </div>
            <div class="flex-1 flex items-center justify-center h-56">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

    </div>

    <!-- Tax Health & Recent Transactions Row -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Tax Health Card -->
        <div class="lg:col-span-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                        <i data-lucide="receipt-tax" class="w-4 h-4 text-rose-500"></i>
                        Status Kewajiban Pajak
                    </h3>
                    <a href="{{ route('reports.bp') }}" class="text-xs text-emerald-600 hover:underline font-semibold">Detail BP &rarr;</a>
                </div>

                <div class="space-y-3">
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between">
                        <div>
                            <div class="text-[11px] text-slate-500 uppercase font-semibold">Total Pajak Dipungut</div>
                            <div class="text-base font-bold text-slate-900">@rupiah($pajakDipungut)</div>
                        </div>
                        <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">100%</span>
                    </div>

                    <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-between">
                        <div>
                            <div class="text-[11px] text-emerald-700 uppercase font-semibold">Sudah Disetor (NTPN)</div>
                            <div class="text-base font-bold text-emerald-700">@rupiah($pajakDisetor)</div>
                        </div>
                        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
                    </div>

                    <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-between">
                        <div>
                            <div class="text-[11px] text-rose-700 uppercase font-semibold">Pajak Belum Disetor</div>
                            <div class="text-base font-bold text-rose-700">@rupiah($pajakTerutang)</div>
                        </div>
                        @if($pajakTerutang > 0)
                            <span class="px-2 py-0.5 rounded-full bg-rose-200 text-rose-800 text-[10px] font-bold animate-pulse">Perlu Setor</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full bg-emerald-200 text-emerald-800 text-[10px] font-bold">Lunas</span>
                        @endif
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-slate-400 mt-4 leading-relaxed">
                *Pajak yang dipungut wajib disetorkan ke Kas Negara paling lambat akhir bulan atau sebelum batas waktu perbendaharaan.
            </p>
        </div>

        <!-- Recent Transactions Table -->
        <div class="lg:col-span-8 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-bold text-sm text-slate-900">Mutasi Transaksi Keuangan Terakhir</h3>
                    <p class="text-xs text-slate-500">Catatan penerimaan dan pengeluaran terbaru</p>
                </div>
                <a href="{{ route('transactions.index') }}" class="text-xs text-emerald-600 hover:underline font-semibold flex items-center gap-1">
                    <span>Semua Transaksi</span>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-semibold uppercase text-[10px]">
                            <th class="py-2.5 px-3">Tanggal / No Bukti</th>
                            <th class="py-2.5 px-3">Sekolah & Uraian</th>
                            <th class="py-2.5 px-3">Jenis Mutasi</th>
                            <th class="py-2.5 px-3 text-right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentTransactions as $tx)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="font-semibold text-slate-900">{{ $tx->transaction_date->format('d/m/Y') }}</div>
                                <div class="text-[11px] font-mono text-slate-400">{{ $tx->transaction_number }}</div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-semibold text-slate-800 truncate max-w-xs">{{ $tx->school->name }}</div>
                                <div class="text-[11px] text-slate-500 truncate max-w-xs">{{ $tx->description }}</div>
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if(in_array($tx->type, ['penerimaan_dana', 'bunga_bank']))
                                    <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 font-semibold text-[10px]">Masuk (Bank)</span>
                                @elseif($tx->type === 'tarik_tunai')
                                    <span class="px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 font-semibold text-[10px]">Tarik Tunai Kas</span>
                                @elseif($tx->type === 'belanja_tunai')
                                    <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 font-semibold text-[10px]">Belanja Kas</span>
                                @elseif($tx->type === 'belanja_transfer')
                                    <span class="px-2 py-0.5 rounded-md bg-sky-100 text-sky-800 font-semibold text-[10px]">Belanja Bank</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-semibold text-[10px]">{{ ucfirst($tx->type) }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right whitespace-nowrap font-bold {{ in_array($tx->type, ['penerimaan_dana', 'bunga_bank']) ? 'text-emerald-600' : 'text-slate-900' }}">
                                {{ in_array($tx->type, ['penerimaan_dana', 'bunga_bank']) ? '+' : '-' }} @rupiah($tx->amount)
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-6 text-slate-400 text-xs">Belum ada transaksi tercatat.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Chart 1: Progress Fisik vs Keuangan
        const ctxProgress = document.getElementById('progressChart').getContext('2d');
        new Chart(ctxProgress, {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartProjectLabels) !!},
                datasets: [
                    {
                        label: 'Progres Fisik Lapangan (%)',
                        data: {!! json_encode($chartPhysicalProgress) !!},
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderColor: '#10b981',
                        borderWidth: 1,
                        borderRadius: 6,
                    },
                    {
                        label: 'Serapan Keuangan (%)',
                        data: {!! json_encode($chartFinancialProgress) !!},
                        backgroundColor: 'rgba(59, 130, 246, 0.85)',
                        borderColor: '#3b82f6',
                        borderWidth: 1,
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) { return value + '%'; }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                }
            }
        });

        // Chart 2: Komposisi RAB Kategori
        const ctxCategory = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($categoryLabels) !!},
                datasets: [{
                    data: {!! json_encode($categoryData) !!},
                    backgroundColor: [
                        '#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, font: { size: 10 } }
                    }
                },
                cutout: '65%'
            }
        });
    });
</script>
@endpush

