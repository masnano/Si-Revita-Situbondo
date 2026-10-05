@extends('layouts.app')

@section('title', 'Catat Transaksi Keuangan Baru')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="transactionCalculator()">

    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('transactions.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1 mb-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Kembali ke Daftar Transaksi</span>
            </a>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Input Transaksi Keuangan Baru
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

    <form action="{{ route('transactions.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
        @csrf

        <!-- School & Project -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Sekolah Penerima Revitalisasi *</label>
                <select name="school_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-500">
                    @foreach($schools as $sch)
                        <option value="{{ $sch->id }}" {{ $selectedSchoolId == $sch->id ? 'selected' : '' }}>
                            {{ $sch->name }} ({{ $sch->jenjang }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Paket Revitalisasi Terkait *</label>
                <select name="project_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-500">
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Transaction Number & Date -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nomor Bukti Transaksi / Kuitansi *</label>
                <input type="text" name="transaction_number" required value="{{ old('transaction_number', $autoNumber) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-800 bg-slate-50 focus:outline-none focus:border-emerald-500">
                <p class="text-[10px] text-slate-400 mt-0.5">Format baku: BKU-TAHUN/NOMOR</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tanggal Transaksi *</label>
                <input type="date" name="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
            </div>
        </div>

        <!-- Type & Payment Method -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Jenis Mutasi Keuangan *</label>
                <select name="type" required x-model="transactionType" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-500">
                    <option value="belanja_tunai">Belanja Tunai (Pengeluaran dari Kas Tunai Bendahara)</option>
                    <option value="belanja_transfer">Belanja Transfer (Pengeluaran langsung dari Bank ke Toko)</option>
                    <option value="tarik_tunai">Penarikan Tunai (Bank ke Kas Fisik Bendahara)</option>
                    <option value="setor_tunai">Penyetoran Kas Tunai ke Bank</option>
                    <option value="penerimaan_dana">Penerimaan Transfer Dana (Termin DAK/Bantuan masuk ke Bank)</option>
                    <option value="bunga_bank">Bunga Rekening Bank / Jasa Giro</option>
                    <option value="biaya_bank">Biaya Administrasi & Pajak Bunga Bank</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Metode Pembayaran *</label>
                <select name="payment_method" required x-model="paymentMethod" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-500">
                    <option value="kas_tunai">Kas Tunai</option>
                    <option value="bank_transfer">Transfer Bank (Bank Jatim)</option>
                </select>
            </div>
        </div>

        <!-- Description & RAB Item -->
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Uraian Transaksi Belanja / Penerimaan *</label>
                <textarea name="description" rows="2" required placeholder="Contoh: Pembayaran belanja semen gresik 100 sak untuk renovasi ruang kelas 1 dan 2..."
                          class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-emerald-500">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kaitan Pos Rencana Anggaran (RAB)</label>
                    <select name="budget_item_id" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-500">
                        <option value="">-- Tidak Terkait Item Khusus (Umum/Penerimaan) --</option>
                        @foreach($budgetItems as $item)
                            <option value="{{ $item->id }}">
                                [{{ $item->category }}] {{ $item->name }} (Pagu: @rupiah($item->total_price))
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nama Toko / Rekanan / Penerima</label>
                    <input type="text" name="recipient_name" placeholder="Contoh: UD. Berkah Jaya Bahan Bangunan" value="{{ old('recipient_name') }}"
                           class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                </div>
            </div>
        </div>

        <!-- Nominal Bruto -->
        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
            <label class="block text-xs font-bold text-slate-800 mb-1">Nominal Transaksi Bruto (Rp) *</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-500 text-sm">Rp</span>
                <input type="number" step="any" name="amount" required x-model.number="amount"
                       placeholder="0"
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl text-base font-mono font-bold text-slate-900 bg-white focus:outline-none focus:border-emerald-500">
            </div>
        </div>

        <!-- SMART TAX CALCULATOR ACCORDION -->
        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-white shadow-sm" x-show="transactionType.includes('belanja')">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="has_tax" name="has_tax" value="1" x-model="hasTax"
                           class="rounded border-slate-300 text-rose-600 focus:ring-0 w-4 h-4">
                    <label for="has_tax" class="text-xs font-bold text-slate-800 cursor-pointer flex items-center gap-1.5">
                        <i data-lucide="receipt-tax" class="w-4 h-4 text-rose-600"></i>
                        Ada Pemotongan Pajak pada Belanja Ini? (PPN / PPh)
                    </label>
                </div>
                <span class="text-[11px] text-slate-500">Otomatis masuk ke Buku Pembantu Pajak (BP)</span>
            </div>

            <div x-show="hasTax" x-transition class="space-y-4 pt-2">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <!-- Quick Tax Presets -->
                    <button type="button" @click="applyPpn()" class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-left">
                        <div class="font-bold text-rose-700">+ PPN 11%</div>
                        <div class="text-[10px] text-slate-500">Belanja Barang > Rp 2 Juta</div>
                    </button>
                    <button type="button" @click="applyPph22()" class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-left">
                        <div class="font-bold text-rose-700">+ PPh 22 (1.5%)</div>
                        <div class="text-[10px] text-slate-500">Pengadaan Material</div>
                    </button>
                    <button type="button" @click="applyPph21()" class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-left">
                        <div class="font-bold text-rose-700">+ PPh 21 (2.5% / 5%)</div>
                        <div class="text-[10px] text-slate-500">Upah Tenaga Kerja</div>
                    </button>
                    <button type="button" @click="applyPph4_2()" class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-left">
                        <div class="font-bold text-rose-700">+ PPh 4(2) Final (2%)</div>
                        <div class="text-[10px] text-slate-500">Jasa Pelaksana Konstruksi</div>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPN 11% (Rp)</label>
                        <input type="number" step="any" name="tax_ppn" x-model.number="taxPpn" placeholder="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPh 21 (Upah) (Rp)</label>
                        <input type="number" step="any" name="tax_pph21" x-model.number="taxPph21" placeholder="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPh 22 (Barang) (Rp)</label>
                        <input type="number" step="any" name="tax_pph22" x-model.number="taxPph22" placeholder="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPh 23 (Sewa Alat) (Rp)</label>
                        <input type="number" step="any" name="tax_pph23" x-model.number="taxPph23" placeholder="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">PPh 4(2) Final Konstruksi (Rp)</label>
                        <input type="number" step="any" name="tax_pph4_2" x-model.number="taxPph4_2" placeholder="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Keterangan Jenis Pajak</label>
                        <input type="text" name="tax_type" placeholder="Contoh: PPN & PPh 22"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs">
                    </div>
                </div>

                <!-- Tax Summary Result Box -->
                <div class="p-3.5 bg-rose-50 rounded-xl border border-rose-200 flex flex-wrap items-center justify-between text-xs">
                    <div>
                        <span class="text-rose-800 font-semibold">Total Pajak Dipungut:</span>
                        <span class="font-bold font-mono text-sm text-rose-900 ml-1">Rp <span x-text="formatNumber(totalTax())"></span></span>
                    </div>
                    <div>
                        <span class="text-slate-700 font-semibold">Nominal Bersih yang Dibayarkan:</span>
                        <span class="font-bold font-mono text-sm text-emerald-800 ml-1">Rp <span x-text="formatNumber(netAmount())"></span></span>
                    </div>
                </div>

                <!-- NTPN if already deposited -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Nomor NTPN (Jika sudah disetor ke Kas Negara)</label>
                        <input type="text" name="tax_ntpn" placeholder="Kosongkan jika belum disetor"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tanggal Penyetoran Pajak</label>
                        <input type="date" name="tax_payment_date"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs">
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipt File & Notes -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Unggah Bukti Kuitansi / Faktur / Nota (Opsional)</label>
                <input type="file" name="receipt_file" accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Catatan Tambahan</label>
                <input type="text" name="notes" placeholder="Catatan internal transaksi..."
                       class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
            </div>
        </div>

        <!-- Submit Button -->
        <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
            <a href="{{ route('transactions.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-600/25 transition-all">
                Simpan & Bukukan Transaksi
            </button>
        </div>
    </form>

</div>

<script>
    function transactionCalculator() {
        return {
            transactionType: 'belanja_tunai',
            paymentMethod: 'kas_tunai',
            amount: 0,
            hasTax: false,
            taxPpn: 0,
            taxPph21: 0,
            taxPph22: 0,
            taxPph23: 0,
            taxPph4_2: 0,

            totalTax() {
                if (!this.hasTax) return 0;
                return (this.taxPpn || 0) + (this.taxPph21 || 0) + (this.taxPph22 || 0) + (this.taxPph23 || 0) + (this.taxPph4_2 || 0);
            },

            netAmount() {
                return Math.max(0, (this.amount || 0) - this.totalTax());
            },

            applyPpn() {
                this.hasTax = true;
                // PPN 11% dari DPP (11/111 * Nilai Bruto)
                this.taxPpn = Math.round((11 / 111) * (this.amount || 0));
            },

            applyPph22() {
                this.hasTax = true;
                // PPh 22 1.5% dari DPP (100/111 * Nilai Bruto * 1.5%)
                const dpp = (100 / 111) * (this.amount || 0);
                this.taxPph22 = Math.round(dpp * 0.015);
            },

            applyPph21() {
                this.hasTax = true;
                // PPh 21 tarif 5% (atau 2.5% jika PTKP/non-NPWP disesuaikan)
                this.taxPph21 = Math.round((this.amount || 0) * 0.025);
            },

            applyPph4_2() {
                this.hasTax = true;
                // PPh Final Jasa Konstruksi 2%
                this.taxPph4_2 = Math.round((this.amount || 0) * 0.02);
            },

            formatNumber(num) {
                return new Intl.NumberFormat('id-ID').format(num || 0);
            }
        };
    }
</script>
@endsection

