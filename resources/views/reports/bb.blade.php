@extends('layouts.app')

@section('title', 'Buku Pembantu Bank (BB) - Si Revita Situbondo')

@section('content')
<div class="space-y-6">

    <!-- Filter & Controls (No Print) -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4 no-print">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-sky-600 mb-1">
                <i data-lucide="building-2" class="w-4 h-4"></i>
                <span>Laporan Mutasi Rekening Bank (Output BB)</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Buku Pembantu Bank (BB)
            </h1>
            <p class="text-xs text-slate-500">Mencatat mutasi setoran dan penarikan pada Rekening Bank Penampung Sekolah.</p>
        </div>

        <form action="{{ route('reports.bb') }}" method="GET" class="flex flex-wrap items-center gap-2">
            @role('root,user')
            <select name="school_id" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-sky-500">
                @foreach($schools as $sch)
                    <option value="{{ $sch->id }}" {{ $selectedSchool?->id == $sch->id ? 'selected' : '' }}>
                        {{ $sch->name }}
                    </option>
                @endforeach
            </select>
            @endrole

            <select name="month" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-sky-500">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                    </option>
                @endfor
            </select>

            <select name="year" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:border-sky-500">
                @for($y = date('Y') - 1; $y <= date('Y') + 2; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>

            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 text-white font-semibold text-xs hover:bg-slate-900 transition-colors">
                Filter
            </button>

            <a href="{{ route('reports.export', ['type' => 'bb', 'school_id' => $selectedSchool?->id, 'month' => $month, 'year' => $year]) }}" 
               class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs flex items-center gap-1.5 transition-colors shadow-sm">
                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                <span>Excel/CSV</span>
            </a>

            <button type="button" onclick="window.print()" 
                    class="px-3.5 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs flex items-center gap-1.5 transition-colors shadow-sm">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>Cetak BB</span>
            </button>
        </form>
    </div>

    <!-- Summary KPI Banner (No Print) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 no-print">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-500 uppercase">Saldo Awal Bank</span>
            <div class="text-base font-bold text-slate-900 mt-1">@rupiah($saldoAwalBank)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-emerald-600 uppercase">Total Setoran / Masuk</span>
            <div class="text-base font-bold text-emerald-600 mt-1">+ @rupiah($totalSetoranBank)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-rose-600 uppercase">Total Penarikan / Transfer</span>
            <div class="text-base font-bold text-rose-600 mt-1">- @rupiah($totalPenarikanBank)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-sky-600 uppercase">Saldo Akhir Rekening</span>
            <div class="text-base font-bold text-sky-600 mt-1">@rupiah($runningBalance)</div>
        </div>
    </div>

    <!-- Printable Official Report Sheet -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-10 print-card">
        
        <div class="border-b-2 border-slate-900 pb-4 mb-6 text-center">
            <h3 class="text-base font-bold uppercase tracking-wider text-slate-800">Pemerintah Kabupaten Situbondo</h3>
            <h2 class="text-lg font-black uppercase tracking-wider text-slate-900">Dinas Pendidikan dan Kebudayaan</h2>
            <h1 class="text-xl font-black uppercase tracking-wider text-sky-800 mt-0.5">
                {{ $selectedSchool->name ?? 'SEKOLAH KABUPATEN SITUBONDO' }}
            </h1>
            <p class="text-xs text-slate-600 mt-1">
                {{ $selectedSchool->address ?? '' }}, Kec. {{ $selectedSchool->kecamatan ?? '' }}, Kab. Situbondo
            </p>
        </div>

        <div class="text-center mb-6">
            <h2 class="text-lg font-extrabold uppercase text-slate-900 underline decoration-slate-400 underline-offset-4">
                Buku Pembantu Bank (BB)
            </h2>
            <p class="text-xs font-semibold text-slate-600 mt-1">
                Bulan: <span class="text-slate-900 uppercase">{{ \Carbon\Carbon::create(null, (int)$month, 1)->translatedFormat('F') }} {{ $year }}</span>
            </p>
            <div class="inline-flex items-center gap-2 mt-2 px-3 py-1 rounded-lg bg-sky-50 border border-sky-200 text-sky-900 text-xs font-semibold">
                <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
                <span>{{ $selectedSchool->bank_name ?? 'Bank Jatim' }} — No. Rek: {{ $selectedSchool->bank_account_number ?? '-' }} a.n {{ $selectedSchool->bank_account_holder ?? '-' }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="report-table w-full text-left text-xs border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-800 font-bold uppercase text-[10px] text-center">
                        <th class="py-2.5 px-2 border border-slate-300 w-10">No</th>
                        <th class="py-2.5 px-3 border border-slate-300 w-24">Tanggal</th>
                        <th class="py-2.5 px-3 border border-slate-300 w-28">No. Bukti</th>
                        <th class="py-2.5 px-4 border border-slate-300">Uraian Transaksi Bank</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-32">Setoran / Masuk (Rp)</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-32">Penarikan / Keluar (Rp)</th>
                        <th class="py-2.5 px-3 border border-slate-300 text-right w-36">Saldo Bank (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr class="bg-slate-50/70 font-semibold text-slate-700">
                        <td class="py-2 px-2 border border-slate-300 text-center">-</td>
                        <td class="py-2 px-3 border border-slate-300 text-center">01/{{ sprintf('%02d', $month) }}/{{ $year }}</td>
                        <td class="py-2 px-3 border border-slate-300 text-center">-</td>
                        <td class="py-2 px-4 border border-slate-300 font-bold">Saldo Awal Rekening Bank</td>
                        <td class="py-2 px-3 border border-slate-300 text-right font-mono">-</td>
                        <td class="py-2 px-3 border border-slate-300 text-right font-mono">-</td>
                        <td class="py-2 px-3 border border-slate-300 text-right font-mono font-bold text-slate-900">
                            {{ number_format($saldoAwalBank, 0, ',', '.') }}
                        </td>
                    </tr>

                    @php $no = 1; @endphp
                    @forelse($bbRows as $row)
                    @php $t = $row['transaction']; @endphp
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
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ $row['setoran'] > 0 ? number_format($row['setoran'], 0, ',', '.') : '-' }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ $row['penarikan'] > 0 ? number_format($row['penarikan'], 0, ',', '.') : '-' }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono font-bold text-slate-900">
                            {{ number_format($row['saldo'], 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-slate-400 italic border border-slate-300">
                            Tidak ada mutasi rekening bank pada bulan ini.
                        </td>
                    </tr>
                    @endforelse

                    <tr class="bg-slate-100 font-bold text-slate-900">
                        <td colspan="4" class="py-2.5 px-4 border border-slate-300 text-right uppercase">
                            Total Mutasi Bank Bulan Ini
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ number_format($totalSetoranBank, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono">
                            {{ number_format($totalPenarikanBank, 0, ',', '.') }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-mono font-black text-sky-800">
                            {{ number_format($runningBalance, 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-12 grid grid-cols-2 gap-8 text-center text-xs text-slate-900">
            <div>
                <p>Mengetahui,</p>
                <p class="font-bold">Kepala Sekolah</p>
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

