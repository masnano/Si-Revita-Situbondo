@extends('layouts.app')

@section('title', 'Buku Kas Umum (BKU) - Si Revita Situbondo')

@section('content')
<div class="space-y-6">

    <!-- Filter & Action Controls (No Print) -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4 no-print">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                <i data-lucide="book-open" class="w-4 h-4"></i>
                <span>Laporan Keuangan Utama (Output BKU)</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Buku Kas Umum (BKU)
            </h1>
            <p class="text-xs text-slate-500">Mencatat seluruh transaksi penerimaan dan pengeluaran secara kronologis.</p>
        </div>

        <!-- Filter Form -->
        <form action="{{ route('reports.bku') }}" method="GET" class="flex flex-wrap items-center gap-2">
            @role('root,user')
            <select name="school_id" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-emerald-500">
                @foreach($schools as $sch)
                    <option value="{{ $sch->id }}" {{ $selectedSchool?->id == $sch->id ? 'selected' : '' }}>
                        {{ $sch->name }}
                    </option>
                @endforeach
            </select>
            @endrole

            <select name="month" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-emerald-500">
                @for($m = 1; $m <= 12; $m++)
                    @php $monthName = \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F'); @endphp
                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ $monthName }}</option>
                @endfor
            </select>

            <select name="year" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-emerald-500">
                @for($y = date('Y') - 1; $y <= date('Y') + 2; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>

            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 text-white font-semibold text-xs hover:bg-slate-900 transition-colors">
                Filter
            </button>

            <!-- Export Buttons -->
            <a href="{{ route('reports.export', ['type' => 'bku', 'school_id' => $selectedSchool?->id, 'month' => $month, 'year' => $year]) }}" 
               class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs flex items-center gap-1.5 transition-colors shadow-sm">
                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                <span>Excel/CSV</span>
            </a>

            <button type="button" onclick="window.print()" 
                    class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs flex items-center gap-1.5 transition-colors shadow-sm">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>Cetak BKU</span>
            </button>
        </form>
    </div>

    <!-- Summary KPI Cards (No Print) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 no-print">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-500 uppercase">Saldo Awal Bulan</span>
            <div class="text-base font-bold text-slate-900 mt-1">@rupiah($saldoAwal)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-emerald-600 uppercase">Penerimaan Bulan Ini</span>
            <div class="text-base font-bold text-emerald-600 mt-1">+ @rupiah($totalPenerimaanBulan)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-rose-600 uppercase">Pengeluaran Bulan Ini</span>
            <div class="text-base font-bold text-rose-600 mt-1">- @rupiah($totalPengeluaranBulan)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-blue-600 uppercase">Saldo Akhir Kumulatif</span>
            <div class="text-base font-bold text-blue-600 mt-1">@rupiah($runningBalance)</div>
        </div>
    </div>

    <!-- Printable Official Report Sheet -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-10 print-card">
        
        <!-- Official Government Kop Surat -->
        <div class="border-b-2 border-slate-900 pb-4 mb-6 flex items-center justify-between gap-4">
            <img src="{{ asset('images/logo.png') }}" alt="Logo Si Revita Situbondo" class="w-16 h-16 sm:w-20 sm:h-20 object-contain shrink-0">
            <div class="flex-1 text-center">
                <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-800">
                    Pemerintah Kabupaten Situbondo
                </h3>
                <h2 class="text-sm sm:text-base font-black uppercase tracking-wider text-slate-900">
                    Dinas Pendidikan dan Kebudayaan
                </h2>
                <h1 class="text-base sm:text-lg font-black uppercase tracking-wider text-emerald-800 mt-0.5">
                    {{ $selectedSchool->name ?? 'SEKOLAH KABUPATEN SITUBONDO' }}
                </h1>
                <p class="text-[11px] sm:text-xs text-slate-600 mt-0.5">
                    {{ $selectedSchool->address ?? '' }}, Kec. {{ $selectedSchool->kecamatan ?? '' }}, Kab. Situbondo — NPSN: {{ $selectedSchool->npsn ?? '' }}
                </p>
            </div>
            <div class="w-16 sm:w-20 shrink-0 hidden sm:block"></div>
        </div>

        <!-- Document Title -->
        <div class="text-center mb-6">
            <h2 class="text-lg font-extrabold uppercase text-slate-900 underline decoration-slate-400 underline-offset-4">
                Buku Kas Umum (BKU)
            </h2>
            <p class="text-xs font-semibold text-slate-600 mt-1">
                Bulan: <span class="text-slate-900 uppercase">{{ \Carbon\Carbon::create(null, (int)$month, 1)->translatedFormat('F') }} {{ $year }}</span>
            </p>
            <p class="text-[11px] text-slate-500 font-mono mt-0.5">
                Kegiatan: Bantuan Revitalisasi Sarana Prasarana Sekolah TA {{ $year }}
            </p>
        </div>

        <!-- BKU Main Table -->
        <div class="overflow-x-auto">
            <table class="report-table w-full text-left text-xs border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-800 font-bold uppercase text-[10px] text-center">
                        <th class="py-2.5 px-2 border border-slate-300 w-10">No</th>
                        <th class="py-2.5 px-3 border border-slate-300 w-24">Tanggal</th>
                        <th class="py-2.5 px-3 border border-slate-300 w-28">No. Bukti</th>
                        <th class="py-2.5 px-4 border border-slate-300">Uraian Transaksi</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-32">Penerimaan (Rp)</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-32">Pengeluaran (Rp)</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-36">Saldo (Rp)</th>
                    </tr>
                    <tr class="bg-slate-50 text-slate-400 text-[9px] text-center font-mono">
                        <th class="border border-slate-300 py-1">1</th>
                        <th class="border border-slate-300 py-1">2</th>
                        <th class="border border-slate-300 py-1">3</th>
                        <th class="border border-slate-300 py-1">4</th>
                        <th class="border border-slate-300 py-1">5</th>
                        <th class="border border-slate-300 py-1">6</th>
                        <th class="border border-slate-300 py-1">7</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <!-- Row Saldo Awal -->
                    <tr class="bg-slate-50/70 font-semibold text-slate-700">
                        <td class="py-2 px-2 border border-slate-300 text-center">-</td>
                        <td class="py-2 px-3 border border-slate-300 text-center">01/{{ sprintf('%02d', $month) }}/{{ $year }}</td>
                        <td class="py-2 px-3 border border-slate-300 font-mono text-center">-</td>
                        <td class="py-2 px-4 border border-slate-300 font-bold">Saldo Awal Bulan</td>
                        <td class="py-2 px-3 border border-slate-300 text-right font-mono">-</td>
                        <td class="py-2 px-3 border border-slate-300 text-right font-mono">-</td>
                        <td class="py-2 px-3 border border-slate-300 text-right font-mono font-bold text-slate-900">
                            {{ number_format($saldoAwal, 0, ',', '.') }}
                        </td>
                    </tr>

                    <!-- Transaction Rows -->
                    @php $no = 1; @endphp
                    @forelse($bkuRows as $row)
                    @php $t = $row['transaction']; @endphp
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-2.5 px-2 border border-slate-300 text-center">{{ $no++ }}</td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-mono whitespace-nowrap">
                            {{ $t->transaction_date->format('d/m/Y') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 font-mono text-center font-semibold text-slate-700">
                            {{ $t->transaction_number }}
                        </td>
                        <td class="py-2.5 px-4 border border-slate-300 text-slate-800 leading-snug">
                            {{ $t->description }}
                            @if($t->recipient_name)
                                <div class="text-[10px] text-slate-500 mt-0.5">Kepada: {{ $t->recipient_name }}</div>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ $row['penerimaan'] > 0 ? number_format($row['penerimaan'], 0, ',', '.') : '-' }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ $row['pengeluaran'] > 0 ? number_format($row['pengeluaran'], 0, ',', '.') : '-' }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono font-bold text-slate-900">
                            {{ number_format($row['saldo'], 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-slate-400 italic border border-slate-300">
                            Tidak ada transaksi keuangan pada periode bulan ini.
                        </td>
                    </tr>
                    @endforelse

                    <!-- Total Bulan Ini -->
                    <tr class="bg-slate-100 font-bold text-slate-900">
                        <td colspan="4" class="py-2.5 px-4 border border-slate-300 text-right uppercase">
                            Jumlah Transaksi Bulan Ini
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ number_format($totalPenerimaanBulan, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ number_format($totalPengeluaranBulan, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono font-black text-emerald-800">
                            {{ number_format($runningBalance, 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Official Closing & Reconciliation Statement -->
        <div class="mt-8 p-4 rounded-xl border border-slate-300 bg-slate-50/70 text-xs text-slate-800 space-y-2">
            <p class="font-bold">
                Pada hari ini, <span class="underline">{{ \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->translatedFormat('l, d F Y') }}</span>, Buku Kas Umum ditutup dengan keadaan kas sebagai berikut:
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                <div class="space-y-1">
                    <div class="flex justify-between border-b border-slate-200 pb-1">
                        <span>Saldo Buku Kas Umum (BKU):</span>
                        <span class="font-bold font-mono">@rupiah($runningBalance)</span>
                    </div>
                    <div class="text-[11px] text-slate-500 italic">
                        Terbilang: {{ \App\Models\Transaction::penyebut($runningBalance) }} Rupiah
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="font-semibold text-slate-700">Rincian Posisi Saldo Kas:</div>
                    <div class="flex justify-between text-slate-600">
                        <span>a. Saldo Kas Tunai (Brankas Bendahara):</span>
                        <span class="font-mono font-semibold">@rupiah($rincianKasTunai)</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>b. Saldo Rekening Bank Jatim (No. {{ $selectedSchool->bank_account_number ?? '-' }}):</span>
                        <span class="font-mono font-semibold">@rupiah($rincianBank)</span>
                    </div>
                    <div class="flex justify-between font-bold text-slate-900 border-t border-slate-300 pt-1">
                        <span>Total Keadaan Kas Fisik + Bank:</span>
                        <span class="font-mono">@rupiah($rincianKasTunai + $rincianBank)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Official 2-Column Signatures -->
        <div class="mt-12 grid grid-cols-2 gap-8 text-center text-xs text-slate-900">
            <div>
                <p>Mengetahui,</p>
                <p class="font-bold">Kepala Sekolah / Penanggung Jawab Kegiatan</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->principal_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->principal_nip ?? '...................................' }}</p>
            </div>
            <div>
                <p>Situbondo, {{ \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->translatedFormat('d F Y') }}</p>
                <p class="font-bold">Bendahara Pengeluaran Revitalisasi</p>
                <div class="h-24"></div>
                <p class="font-bold text-sm underline">{{ $selectedSchool->treasurer_name ?? '................................................' }}</p>
                <p class="text-slate-600">NIP. {{ $selectedSchool->treasurer_nip ?? '...................................' }}</p>
            </div>
        </div>

    </div>

</div>
@endsection

