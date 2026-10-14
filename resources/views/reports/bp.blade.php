@extends('layouts.app')

@php
    $bulanIndo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $hasSpecificMonth = isset($month) && $month !== 'all' && is_numeric($month);
    $currentMonthName = $hasSpecificMonth 
        ? ($bulanIndo[(int)$month] ?? \Carbon\Carbon::create(null, (int)$month, 1)->translatedFormat('F'))
        : ($bulanIndo[(int)date('n')] ?? date('F'));
    $schoolCleanName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $selectedSchool->name ?? 'Sekolah');
    $pageTitle = "{$currentMonthName} {$year} - Buku Pembantu Pajak (BP) - {$schoolCleanName}";
@endphp

@section('title', $pageTitle)

@section('content')
<div class="space-y-6">

    <!-- Filter & Controls (No Print) -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4 no-print">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-rose-600 mb-1">
                <i data-lucide="receipt-tax" class="w-4 h-4"></i>
                <span>Laporan Pemotongan & Penyetoran Pajak (Output BP)</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Buku Pembantu Pajak (BP)
            </h1>
            <p class="text-xs text-slate-500">Mencatat pemotongan pajak belanja serta penyetorannya ke Kas Negara melalui e-Billing/NTPN.</p>
        </div>

        <form action="{{ route('reports.bp') }}" method="GET" class="flex flex-wrap items-center gap-2">
            @role('root,user')
            <select name="school_id" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-rose-500">
                @foreach($schools as $sch)
                    <option value="{{ $sch->id }}" {{ $selectedSchool?->id == $sch->id ? 'selected' : '' }}>
                        {{ $sch->name }}
                    </option>
                @endforeach
            </select>
            @endrole

            <select name="tax_type" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-rose-500">
                <option value="all" {{ $taxType == 'all' ? 'selected' : '' }}>Semua Jenis Pajak</option>
                <option value="PPN" {{ $taxType == 'PPN' ? 'selected' : '' }}>PPN (11%)</option>
                <option value="PPh 21" {{ $taxType == 'PPh 21' ? 'selected' : '' }}>PPh 21 (Upah/Honor)</option>
                <option value="PPh 22" {{ $taxType == 'PPh 22' ? 'selected' : '' }}>PPh 22 (Barang)</option>
                <option value="PPh 23" {{ $taxType == 'PPh 23' ? 'selected' : '' }}>PPh 23 (Sewa/Jasa)</option>
                <option value="PPh 4(2)" {{ $taxType == 'PPh 4(2)' ? 'selected' : '' }}>PPh 4(2) Final Konstruksi</option>
            </select>

            <select name="status" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-rose-500">
                <option value="all" {{ $status == 'all' ? 'selected' : '' }}>Semua Status</option>
                <option value="dipungut" {{ $status == 'dipungut' ? 'selected' : '' }}>Belum Disetor</option>
                <option value="disetor" {{ $status == 'disetor' ? 'selected' : '' }}>Sudah Disetor (NTPN)</option>
            </select>

            <select name="month" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-rose-500">
                <option value="all" {{ ($month ?? 'all') == 'all' ? 'selected' : '' }}>Semua Bulan</option>
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ ($month ?? '') == (string)$m ? 'selected' : '' }}>{{ $bulanIndo[$m] }}</option>
                @endfor
            </select>

            <select name="year" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-rose-500">
                @for($y = date('Y') - 1; $y <= date('Y') + 2; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>

            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 text-white font-semibold text-xs hover:bg-slate-900 transition-colors">
                Filter
            </button>

            @role('root,admin')
            <button type="submit" 
                    form="form-auto-setor-pajak"
                    data-confirm="Setorkan seluruh transaksi pajak pada periode ini secara otomatis? Sistem AI akan men-generate Nomor NTPN 16 digit resmi DJP dan menyinkronkan tanggal penyetoran dengan transaksi."
                    class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold text-xs flex items-center gap-1.5 transition-all shadow-sm">
                <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-300"></i>
                <span>Setor Otomatis Pajak AI</span>
            </button>
            @endrole

            <a href="{{ route('reports.ai_audit', ['school_id' => $selectedSchool?->id, 'year' => $year, 'month' => $month]) }}" 
               class="px-3.5 py-2 rounded-xl bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 font-bold text-xs flex items-center gap-1.5 transition-colors">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-purple-600"></i>
                <span>Audit Kepatuhan AI</span>
            </a>

            <a href="{{ route('reports.export', ['type' => 'bp', 'school_id' => $selectedSchool?->id, 'year' => $year]) }}" 
               class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs flex items-center gap-1.5 transition-colors shadow-sm">
                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                <span>Excel/CSV</span>
            </a>

            <button type="button" onclick="window.print()" 
                    class="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs flex items-center gap-1.5 transition-colors shadow-sm">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>Cetak BP</span>
            </button>
        </form>

        <form id="form-auto-setor-pajak" action="{{ route('reports.bp.auto_setor') }}" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="school_id" value="{{ $selectedSchool?->id }}">
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="month" value="{{ $month }}">
        </form>
    </div>

    <!-- Tax KPI Cards (No Print) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 no-print">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-500 uppercase">Total Pajak Dipungut</span>
            <div class="text-lg font-extrabold text-slate-900 mt-1">@rupiah($totalPungut)</div>
            <div class="text-[11px] text-slate-500 mt-1">Akumulasi pemotongan belanja</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-emerald-600 uppercase">Sudah Disetor ke Kas Negara</span>
            <div class="text-lg font-extrabold text-emerald-600 mt-1">@rupiah($totalSetor)</div>
            <div class="text-[11px] text-emerald-600 mt-1">Telah memiliki NTPN valid</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-rose-600 uppercase">Saldo Pajak Belum Disetor</span>
            <div class="text-lg font-extrabold text-rose-600 mt-1">@rupiah($saldoPajak)</div>
            <div class="text-[11px] {{ $saldoPajak > 0 ? 'text-rose-600 font-bold animate-pulse' : 'text-slate-400' }} mt-1">
                {{ $saldoPajak > 0 ? 'Wajib segera disetorkan!' : 'Seluruh pajak telah disetorkan lunas' }}
            </div>
        </div>
    </div>

    <!-- Tax Breakdown Badges (No Print) -->
    <div class="p-3 bg-slate-100 rounded-xl flex flex-wrap items-center gap-3 text-xs text-slate-700 no-print">
        <span class="font-bold text-slate-500 text-[11px] uppercase">Rincian Jenis Pajak:</span>
        <span class="px-2.5 py-1 rounded-lg bg-white border border-slate-200"><strong>PPN 11%:</strong> @rupiah($totalPPN)</span>
        <span class="px-2.5 py-1 rounded-lg bg-white border border-slate-200"><strong>PPh 21:</strong> @rupiah($totalPPh21)</span>
        <span class="px-2.5 py-1 rounded-lg bg-white border border-slate-200"><strong>PPh 22:</strong> @rupiah($totalPPh22)</span>
        <span class="px-2.5 py-1 rounded-lg bg-white border border-slate-200"><strong>PPh 23:</strong> @rupiah($totalPPh23)</span>
        @if($totalPPh4_2 > 0)
        <span class="px-2.5 py-1 rounded-lg bg-white border border-slate-200"><strong>PPh 4(2):</strong> @rupiah($totalPPh4_2)</span>
        @endif
    </div>

    <!-- Printable Official Report Sheet -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-10 print-card" 
         x-data="{ 
             openSetorModal: false, 
             selectedTx: null,
             modalNtpn: '',
             modalPaymentDate: '',
             generateModalNtpn() {
                 const chars = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
                 let code = '';
                 for (let i = 0; i < 16; i++) {
                     code += chars.charAt(Math.floor(Math.random() * chars.length));
                 }
                 this.modalNtpn = code;
                 if (this.selectedTx && this.selectedTx.raw_date) {
                     this.modalPaymentDate = this.selectedTx.raw_date;
                 }
             }
         }">
        
        <div class="border-b-2 border-slate-900 pb-4 mb-6 flex items-center justify-between gap-4">
            <img src="{{ asset('images/logo-kabupaten.png') }}" alt="Logo Kabupaten Situbondo" class="w-16 h-16 sm:w-20 sm:h-20 object-contain shrink-0">
            <div class="flex-1 text-center">
                <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-800">Pemerintah Kabupaten Situbondo</h3>
                <h2 class="text-sm sm:text-base font-black uppercase tracking-wider text-slate-900">Dinas Pendidikan dan Kebudayaan</h2>
                <h1 class="text-base sm:text-lg font-black uppercase tracking-wider text-rose-800 mt-0.5">
                    {{ $selectedSchool->name ?? 'SEKOLAH KABUPATEN SITUBONDO' }}
                </h1>
                <p class="text-[11px] sm:text-xs text-slate-600 mt-0.5">
                    {{ $selectedSchool->address ?? '' }}, Kec. {{ $selectedSchool->kecamatan ?? '' }}, Kab. Situbondo — NPSN: {{ $selectedSchool->npsn ?? '' }}
                </p>
            </div>
            <div class="w-16 sm:w-20 shrink-0 hidden sm:block"></div>
        </div>

        <div class="text-center mb-6">
            <h2 class="text-lg font-black uppercase text-slate-900 tracking-wider underline decoration-slate-400 underline-offset-4">
                Buku Pembantu Pajak (BP)
            </h2>
            <p class="text-xs font-bold text-slate-700 mt-1 uppercase">
                @if($hasSpecificMonth)
                    Bulan: <span class="text-slate-900">{{ $currentMonthName }} {{ $year }}</span>
                @else
                    Tahun Anggaran: <span class="text-slate-900">{{ $year }}</span> (Semua Bulan)
                @endif
            </p>
            <p class="text-[11px] text-slate-500 font-mono mt-0.5">
                Kegiatan: Bantuan Revitalisasi Sarana Prasarana Sekolah TA {{ $year }}
            </p>
            <div class="inline-flex items-center gap-2 mt-2 px-3 py-1 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-xs font-semibold">
                <i data-lucide="receipt-tax" class="w-3.5 h-3.5"></i>
                <span>Rekening Operasional: <strong>{{ $selectedSchool->bank_name ?? 'Bank Penampung' }}</strong> &mdash; No. Rek: <strong class="font-mono">{{ $selectedSchool->bank_account_number ?? '-' }}</strong> a.n <strong>{{ $selectedSchool->bank_account_holder ?? '-' }}</strong></span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="report-table w-full text-left text-xs border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-800 font-bold uppercase text-[10px] text-center">
                        <th class="py-2.5 px-2 border border-slate-300 w-10">No</th>
                        <th class="py-2.5 px-3 border border-slate-300 w-24">Tanggal</th>
                        <th class="py-2.5 px-3 border border-slate-300 w-28">No. Bukti</th>
                        <th class="py-2.5 px-4 border border-slate-300">Uraian Belanja / Objek Pajak</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-center w-28">Jenis Pajak</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-32">Pemotongan (Rp)</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-32">Penyetoran (Rp)</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-center w-36">No. NTPN / Status</th>
                        <th class="py-2.5 px-2 border border-slate-300 text-center w-20 no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @php 
                        $no = 1; 
                        $runningSaldoPajak = 0;
                    @endphp
                    @forelse($transactions as $t)
                    @php
                        $setoran = ($t->tax_status === 'disetor') ? $t->tax_total : 0;
                        $runningSaldoPajak += ($t->tax_total - $setoran);
                    @endphp
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-2.5 px-2 border border-slate-300 text-center">{{ $no++ }}</td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-mono whitespace-nowrap">
                            {{ $t->transaction_date->format('d/m/Y') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 font-mono text-center font-semibold text-slate-700">
                            {{ $t->transaction_number }}
                        </td>
                        <td class="py-2.5 px-4 border border-slate-300 text-slate-800">
                            {{ $t->description }}
                            <div class="text-[10px] text-slate-500 mt-0.5">
                                Bruto: @rupiah($t->amount) | Penerima: {{ $t->recipient_name ?? '-' }}
                            </div>
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-semibold text-slate-700">
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 text-[10px]">
                                {{ $t->tax_type ?? 'Pajak' }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono font-bold text-slate-900">
                            {{ number_format($t->tax_total, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono font-bold text-emerald-700">
                            {{ $setoran > 0 ? number_format($setoran, 0, ',', '.') : '-' }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-mono">
                            @if($t->tax_status === 'disetor')
                                <div class="font-bold text-emerald-700 text-[11px]">{{ $t->tax_ntpn }}</div>
                                <div class="text-[10px] text-slate-500">{{ $t->tax_payment_date ? $t->tax_payment_date->format('d/m/Y') : '' }}</div>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-bold text-[10px]">
                                    Belum Setor
                                </span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 border border-slate-300 text-center no-print">
                            @if($t->tax_status !== 'disetor')
                                @role('root,admin')
                                <button type="button" 
                                        @click="openSetorModal = true; selectedTx = { id: {{ $t->id }}, number: '{{ $t->transaction_number }}', tax_total: '{{ number_format($t->tax_total, 0, ',', '.') }}', raw_date: '{{ $t->transaction_date->format('Y-m-d') }}' }; generateModalNtpn();"
                                        class="px-2 py-1 rounded bg-rose-600 hover:bg-rose-700 text-white font-bold text-[10px] transition-colors">
                                    Setor
                                </button>
                                @endrole
                            @else
                                <span class="text-emerald-600 font-bold text-xs"><i data-lucide="check" class="w-4 h-4 mx-auto"></i></span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-6 text-center text-slate-400 italic border border-slate-300">
                            Tidak ada catatan pemotongan pajak pada periode ini.
                        </td>
                    </tr>
                    @endforelse

                    <tr class="bg-slate-100 font-bold text-slate-900">
                        <td colspan="5" class="py-2.5 px-4 border border-slate-300 text-right uppercase">
                            Jumlah Pajak
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ number_format($totalPungut, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ number_format($totalSetor, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-mono font-black text-rose-700">
                            Sisa: {{ number_format($saldoPajak, 0, ',', '.') }}
                        </td>
                        <td class="border border-slate-300 no-print"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Official Signatures -->
        <div class="mt-12 grid grid-cols-2 gap-8 text-center text-xs text-slate-900 signature-block avoid-break">
            <div>
                <p>Mengetahui,</p>
                <p class="font-bold">Kepala Sekolah</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->principal_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->principal_nip ?? '...................................' }}</p>
            </div>
            <div>
                <p>Situbondo, {{ \Carbon\Carbon::create($year, 12, 31)->translatedFormat('d F Y') }}</p>
                <p class="font-bold">Bendahara Pengeluaran Revitalisasi</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->treasurer_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->treasurer_nip ?? '...................................' }}</p>
            </div>
        </div>

        <!-- Modal Setor Pajak (Interactive) -->
        <div x-show="openSetorModal" 
             x-transition 
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 no-print" 
             style="display: none;">
            <div @click.away="openSetorModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                        <i data-lucide="receipt-tax" class="w-4 h-4 text-rose-600"></i>
                        <span>Input Penyetoran Pajak ke Kas Negara</span>
                    </h3>
                    <button @click="openSetorModal = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <form :action="'{{ url('/transactions') }}/' + (selectedTx ? selectedTx.id : '') + '/setor-pajak'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <div class="text-xs text-slate-500 mb-1">No. Bukti Transaksi:</div>
                        <div class="font-mono font-bold text-slate-800" x-text="selectedTx ? selectedTx.number : ''"></div>
                    </div>

                    <div>
                        <div class="text-xs text-slate-500 mb-1">Nominal Pajak yang Disetorkan:</div>
                        <div class="text-base font-extrabold text-rose-600">
                            Rp <span x-text="selectedTx ? selectedTx.tax_total : ''"></span>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-700">Nomor Transaksi Penerimaan Negara (NTPN)</label>
                            <button type="button" @click="generateModalNtpn()" class="text-[11px] font-bold text-purple-600 hover:text-purple-800 flex items-center gap-1 transition-colors">
                                <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-500"></i>
                                <span>Generate AI</span>
                            </button>
                        </div>
                        <input type="text" name="tax_ntpn" required x-model="modalNtpn" placeholder="16 karakter alfanumerik resmi DJP (contoh: 8F3C91A2E05B4D76)"
                               class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold tracking-wider uppercase focus:outline-none focus:border-rose-500">
                        <p class="text-[10px] text-slate-400 mt-1">Standar resmi MPN G3 / DJP Kemenkeu (16 digit alfanumerik).</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Penyetoran ke {{ $selectedSchool->bank_name ?? 'Bank Persepsi' }} / Kas Negara</label>
                        <input type="date" name="tax_payment_date" required x-model="modalPaymentDate"
                               class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-rose-500">
                        <p class="text-[10px] text-slate-400 mt-1">Sinkron dengan tanggal transaksi belanja bahan/upah (tidak hari Minggu).</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="openSetorModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition-colors">
                            Simpan Penyetoran
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

</div>
@endsection

