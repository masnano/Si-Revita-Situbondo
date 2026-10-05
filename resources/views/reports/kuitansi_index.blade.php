@extends('layouts.app')

@section('title', 'Daftar Kuitansi & Bukti Pengeluaran')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-teal-600 mb-1">
                <i data-lucide="file-check" class="w-4 h-4"></i>
                <span>Bukti Pengeluaran Resmi</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Kuitansi Pengeluaran Revitalisasi
            </h1>
            <p class="text-xs text-slate-500">Cetak lembar kuitansi resmi bertanda tangan bendahara, kepala sekolah, dan penerima dana.</p>
        </div>

        <form action="{{ route('reports.kuitansi') }}" method="GET" class="flex flex-wrap items-center gap-2">
            @role('root,user')
            <select name="school_id" onchange="this.form.submit()" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800">
                @foreach($schools as $sch)
                    <option value="{{ $sch->id }}" {{ $selectedSchool?->id == $sch->id ? 'selected' : '' }}>
                        {{ $sch->name }}
                    </option>
                @endforeach
            </select>
            @endrole

            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor bukti / toko..."
                   class="text-xs px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 focus:outline-none focus:border-teal-500">

            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 text-white font-semibold text-xs hover:bg-slate-900">
                Cari
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">No. Kuitansi</th>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Sekolah</th>
                        <th class="py-3 px-4">Uraian Pembayaran</th>
                        <th class="py-3 px-4">Penerima Uang</th>
                        <th class="py-3 px-4 text-right">Nominal Bruto</th>
                        <th class="py-3 px-4 text-center">Cetak</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $t)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-3 px-4 font-mono font-bold text-slate-800">
                            {{ $t->transaction_number }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                            {{ $t->transaction_date->format('d/m/Y') }}
                        </td>
                        <td class="py-3 px-4 font-semibold text-slate-800">
                            {{ $t->school->name }}
                        </td>
                        <td class="py-3 px-4 text-slate-700 max-w-sm">
                            <div class="truncate">{{ $t->description }}</div>
                            @if($t->budgetItem)
                                <span class="text-[10px] text-teal-600 font-medium">Pos: {{ $t->budgetItem->name }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-semibold text-slate-800 whitespace-nowrap">
                            {{ $t->recipient_name ?? '-' }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                            @rupiah($t->amount)
                        </td>
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            <a href="{{ route('reports.kuitansi.print', $t->id) }}" target="_blank"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-700 font-bold text-xs transition-colors border border-teal-200">
                                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                <span>Cetak Kuitansi</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                            Tidak ada transaksi belanja kuitansi ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $transactions->links() }}
        </div>
        @endif
    </div>

</div>
@endsection

