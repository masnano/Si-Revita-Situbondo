@extends('layouts.app')

@section('title', 'Daftar Transaksi Keuangan')

@section('content')
<div class="space-y-6">

    <!-- Top Action & Filter Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
                <span>Buku Kas & Bank</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Pencatatan Transaksi Keuangan
            </h1>
            <p class="text-xs text-slate-500">Seluruh mutasi otomatis terhubung ke Buku Kas Umum (BKU), BPK, BB, dan Buku Pajak.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @permission('transaksi.create')
            <a href="{{ route('transactions.create') }}" 
               class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-emerald-600/20 transition-all">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Catat Transaksi Baru</span>
            </a>
            @endpermission
        </div>
    </div>

    <!-- Filter Bar -->
    <form action="{{ route('transactions.index') }}" method="GET" class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex flex-wrap items-center gap-3 text-xs">
        @role('root,user')
        <div class="w-full sm:w-auto">
            <select name="school_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="">-- Semua Sekolah --</option>
                @foreach($schools as $sch)
                    <option value="{{ $sch->id }}" {{ $selectedSchoolId == $sch->id ? 'selected' : '' }}>
                        {{ $sch->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @endrole

        <div class="w-full sm:w-auto">
            <select name="type" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="">-- Semua Jenis Mutasi --</option>
                <option value="penerimaan_dana" {{ request('type') == 'penerimaan_dana' ? 'selected' : '' }}>Penerimaan Dana (Bank)</option>
                <option value="tarik_tunai" {{ request('type') == 'tarik_tunai' ? 'selected' : '' }}>Penarikan Tunai Kas</option>
                <option value="belanja_tunai" {{ request('type') == 'belanja_tunai' ? 'selected' : '' }}>Belanja Tunai (Kas)</option>
                <option value="belanja_transfer" {{ request('type') == 'belanja_transfer' ? 'selected' : '' }}>Belanja Transfer (Bank)</option>
                <option value="bunga_bank" {{ request('type') == 'bunga_bank' ? 'selected' : '' }}>Bunga Bank</option>
                <option value="biaya_bank" {{ request('type') == 'biaya_bank' ? 'selected' : '' }}>Biaya Adm Bank</option>
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <select name="tax_status" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="">-- Status Pajak --</option>
                <option value="none" {{ request('tax_status') == 'none' ? 'selected' : '' }}>Tanpa Pajak</option>
                <option value="dipungut" {{ request('tax_status') == 'dipungut' ? 'selected' : '' }}>Dipungut (Belum Setor)</option>
                <option value="disetor" {{ request('tax_status') == 'disetor' ? 'selected' : '' }}>Sudah Disetor (NTPN)</option>
            </select>
        </div>

        <div class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor bukti, toko, uraian, NTPN..."
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:outline-none focus:border-emerald-500">
        </div>

        <button type="submit" class="px-4 py-2 bg-slate-800 text-white font-semibold rounded-lg hover:bg-slate-900 transition-colors">
            Terapkan Filter
        </button>
        <a href="{{ route('transactions.index') }}" class="px-3 py-2 text-slate-500 hover:text-slate-800 font-medium">
            Reset
        </a>
    </form>

    <!-- Transactions Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                        <th class="py-3 px-3">Tanggal / No Bukti</th>
                        <th class="py-3 px-3">Sekolah</th>
                        <th class="py-3 px-4">Uraian Transaksi</th>
                        <th class="py-3 px-3">Jenis & Metode</th>
                        <th class="py-3 px-3 text-right">Nominal (Rp)</th>
                        <th class="py-3 px-3 text-center">Status Pajak</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $tx)
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="py-3 px-3 whitespace-nowrap">
                            <div class="font-bold text-slate-900">{{ $tx->transaction_date->format('d/m/Y') }}</div>
                            <div class="text-[11px] font-mono text-slate-500">{{ $tx->transaction_number }}</div>
                        </td>
                        <td class="py-3 px-3 whitespace-nowrap font-medium text-slate-800">
                            {{ $tx->school->name }}
                        </td>
                        <td class="py-3 px-4 max-w-xs">
                            <div class="font-semibold text-slate-800 truncate">{{ $tx->description }}</div>
                            @if($tx->recipient_name)
                                <div class="text-[10px] text-slate-500 truncate">Kepada: {{ $tx->recipient_name }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-3 whitespace-nowrap">
                            <div class="font-medium text-slate-700">{{ ucfirst(str_replace('_', ' ', $tx->type)) }}</div>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-mono">
                                {{ $tx->payment_method === 'kas_tunai' ? 'Tunai (Kas)' : 'Bank Transfer' }}
                            </span>
                        </td>
                        <td class="py-3 px-3 text-right whitespace-nowrap font-bold {{ in_array($tx->type, ['penerimaan_dana', 'bunga_bank']) ? 'text-emerald-600' : 'text-slate-900' }}">
                            {{ in_array($tx->type, ['penerimaan_dana', 'bunga_bank']) ? '+' : '-' }} @rupiah($tx->amount)
                        </td>
                        <td class="py-3 px-3 text-center whitespace-nowrap">
                            @if($tx->has_tax)
                                @if($tx->tax_status === 'disetor')
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">
                                        Disetor
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-bold text-[10px]">
                                        Dipungut
                                    </span>
                                @endif
                                <div class="text-[9px] text-slate-500 font-mono mt-0.5">@rupiah($tx->tax_total)</div>
                            @else
                                <span class="text-slate-400 text-[11px]">-</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-center whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                @if(in_array($tx->type, ['belanja_tunai', 'belanja_transfer']))
                                <a href="{{ route('reports.kuitansi.print', $tx->id) }}" target="_blank" title="Cetak Kuitansi"
                                   class="p-1.5 text-teal-600 hover:bg-teal-50 rounded-md">
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                </a>
                                @endif

                                @permission('transaksi.update')
                                <a href="{{ route('transactions.edit', $tx->id) }}" title="Edit Transaksi"
                                   class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-md">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                </a>
                                @endpermission

                                @permission('transaksi.delete')
                                <form action="{{ route('transactions.destroy', $tx->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Hapus transaksi ini? Data di BKU/BPK/BB/BP akan ikut terhapus.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Hapus Transaksi" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                @endpermission
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                            Tidak ada transaksi ditemukan.
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

