@extends('layouts.app')

@section('title', 'Edit Transaksi Keuangan')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="transactionEditCalculator()">

    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('transactions.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1 mb-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Kembali ke Daftar Transaksi</span>
            </a>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Edit Transaksi Keuangan {{ $transaction->transaction_number }}
            </h1>
        </div>
    </div>

    @if($errors->any())
    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs space-y-1">
        <div class="font-bold">Terdapat kesalahan pengisian formulir:</div>
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('transactions.update', $transaction->id) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nomor Bukti Transaksi *</label>
                <input type="text" name="transaction_number" required value="{{ old('transaction_number', $transaction->transaction_number) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold bg-slate-50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tanggal Transaksi *</label>
                <input type="date" name="transaction_date" required value="{{ old('transaction_date', $transaction->transaction_date->format('Y-m-d')) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Jenis Mutasi Keuangan *</label>
                <select name="type" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    <option value="belanja_tunai" {{ $transaction->type == 'belanja_tunai' ? 'selected' : '' }}>Belanja Tunai (Kas)</option>
                    <option value="belanja_transfer" {{ $transaction->type == 'belanja_transfer' ? 'selected' : '' }}>Belanja Transfer (Bank)</option>
                    <option value="tarik_tunai" {{ $transaction->type == 'tarik_tunai' ? 'selected' : '' }}>Penarikan Tunai (Bank ke Kas)</option>
                    <option value="setor_tunai" {{ $transaction->type == 'setor_tunai' ? 'selected' : '' }}>Penyetoran Kas ke Bank</option>
                    <option value="penerimaan_dana" {{ $transaction->type == 'penerimaan_dana' ? 'selected' : '' }}>Penerimaan Dana Masuk</option>
                    <option value="bunga_bank" {{ $transaction->type == 'bunga_bank' ? 'selected' : '' }}>Bunga Bank</option>
                    <option value="biaya_bank" {{ $transaction->type == 'biaya_bank' ? 'selected' : '' }}>Biaya Adm Bank</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Metode Pembayaran *</label>
                <select name="payment_method" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50">
                    <option value="kas_tunai" {{ $transaction->payment_method == 'kas_tunai' ? 'selected' : '' }}>Kas Tunai</option>
                    <option value="bank_transfer" {{ $transaction->payment_method == 'bank_transfer' ? 'selected' : '' }}>Transfer Bank</option>
                </select>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Uraian Transaksi *</label>
                <textarea name="description" rows="2" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">{{ old('description', $transaction->description) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nama Toko / Penerima</label>
                <input type="text" name="recipient_name" value="{{ old('recipient_name', $transaction->recipient_name) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            </div>
        </div>

        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
            <label class="block text-xs font-bold text-slate-800 mb-1">Nominal Transaksi Bruto (Rp) *</label>
            <input type="number" step="any" name="amount" required x-model.number="amount"
                   class="w-full px-3 py-2 border border-slate-300 rounded-xl text-base font-mono font-bold text-slate-900 bg-white">
        </div>

        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="has_tax" name="has_tax" value="1" x-model="hasTax"
                           class="rounded border-slate-300 text-rose-600 focus:ring-0 w-4 h-4">
                    <label for="has_tax" class="text-xs font-bold text-slate-800 cursor-pointer">
                        Ada Pemotongan Pajak pada Belanja Ini? (PPN / PPh)
                    </label>
                </div>
            </div>

            <div x-show="hasTax" class="space-y-4 pt-2">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPN 11% (Rp)</label>
                        <input type="number" step="any" name="tax_ppn" x-model.number="taxPpn" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPh 21 (Upah) (Rp)</label>
                        <input type="number" step="any" name="tax_pph21" x-model.number="taxPph21" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPh 22 (Barang) (Rp)</label>
                        <input type="number" step="any" name="tax_pph22" x-model.number="taxPph22" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPh 23 (Sewa) (Rp)</label>
                        <input type="number" step="any" name="tax_pph23" x-model.number="taxPph23" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPh 4(2) Konstruksi (Rp)</label>
                        <input type="number" step="any" name="tax_pph4_2" x-model.number="taxPph4_2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Keterangan Jenis Pajak</label>
                        <input type="text" name="tax_type" value="{{ old('tax_type', $transaction->tax_type) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Nomor NTPN</label>
                        <input type="text" name="tax_ntpn" value="{{ old('tax_ntpn', $transaction->tax_ntpn) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tanggal Penyetoran Pajak</label>
                        <input type="date" name="tax_payment_date" value="{{ old('tax_payment_date', $transaction->tax_payment_date ? $transaction->tax_payment_date->format('Y-m-d') : '') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs">
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
            <a href="{{ route('transactions.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md transition-all">
                Simpan Perubahan
            </button>
        </div>
    </form>

</div>

<script>
    function transactionEditCalculator() {
        return {
            amount: {{ (float) $transaction->amount }},
            hasTax: {{ $transaction->has_tax ? 'true' : 'false' }},
            taxPpn: {{ (float) $transaction->tax_ppn }},
            taxPph21: {{ (float) $transaction->tax_pph21 }},
            taxPph22: {{ (float) $transaction->tax_pph22 }},
            taxPph23: {{ (float) $transaction->tax_pph23 }},
            taxPph4_2: {{ (float) $transaction->tax_pph4_2 }},
        };
    }
</script>
@endsection

