<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuitansi {{ $transaction->transaction_number }} - Si Revita Situbondo</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css'])

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
        }
        .font-mono-print {
            font-family: 'Courier Prime', monospace;
        }
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-box {
                border: 2px solid #000 !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 20px !important;
            }
        }
    </style>
</head>
<body class="p-4 sm:p-8">

    <!-- Top Action Bar (No Print) -->
    <div class="max-w-3xl mx-auto mb-6 flex items-center justify-between no-print">
        <a href="{{ route('reports.kuitansi') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 bg-white px-3 py-2 rounded-xl border border-slate-200">
            &larr; Kembali ke Daftar Kuitansi
        </a>
        <button onclick="window.print()" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs shadow-md transition-colors">
            Cetak Kuitansi Ini
        </button>
    </div>

    <!-- Official Government Kuitansi Form -->
    <div class="max-w-3xl mx-auto bg-white border-2 border-slate-800 rounded-2xl p-8 sm:p-10 shadow-lg receipt-box">
        
        <!-- Header Kop -->
        <div class="border-b-2 border-slate-900 pb-4 mb-6 flex items-center justify-between gap-4">
            <img src="{{ asset('images/logo.png') }}" alt="Logo Si Revita Situbondo" class="w-16 h-16 object-contain shrink-0">
            <div class="flex-1 text-center">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Pemerintah Kabupaten Situbondo</h3>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Dinas Pendidikan dan Kebudayaan</h2>
                <h1 class="text-base font-black uppercase tracking-wider text-teal-800 mt-0.5">
                    {{ $transaction->school->name }}
                </h1>
                <p class="text-[10px] text-slate-600">
                    {{ $transaction->school->address }} — NPSN: {{ $transaction->school->npsn }}
                </p>
            </div>
            <div class="w-16 shrink-0 hidden sm:block"></div>
        </div>

        <!-- Receipt Header Details -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 pb-4 mb-6 text-xs">
            <div>
                <span class="text-slate-500 font-semibold">Tahun Anggaran:</span>
                <span class="font-bold text-slate-900">{{ $transaction->transaction_date->format('Y') }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Nomor Bukti Kas:</span>
                <span class="font-bold font-mono-print text-sm text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-300">
                    {{ $transaction->transaction_number }}
                </span>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Mata Anggaran:</span>
                <span class="font-bold font-mono text-slate-900">{{ $transaction->budgetItem->code ?? '5.2.2.01' }}</span>
            </div>
        </div>

        <!-- Title -->
        <div class="text-center mb-8">
            <h2 class="text-lg font-black uppercase tracking-widest text-slate-900 underline decoration-slate-400 underline-offset-4">
                Kuitansi / Bukti Pembayaran
            </h2>
        </div>

        <!-- Receipt Body Lines -->
        <div class="space-y-4 text-xs">
            
            <div class="grid grid-cols-12 gap-2 items-start">
                <div class="col-span-3 text-slate-600 font-semibold">Sudah Terima Dari</div>
                <div class="col-span-1 text-center font-bold">:</div>
                <div class="col-span-8 font-bold text-slate-900">
                    Bendahara Revitalisasi {{ $transaction->school->name }}
                </div>
            </div>

            <div class="grid grid-cols-12 gap-2 items-start">
                <div class="col-span-3 text-slate-600 font-semibold">Uang Sejumlah</div>
                <div class="col-span-1 text-center font-bold">:</div>
                <div class="col-span-8 font-bold text-slate-900 bg-slate-50 p-2.5 rounded-lg border border-slate-200 italic leading-relaxed">
                    "{{ $transaction->terbilang }}"
                </div>
            </div>

            <div class="grid grid-cols-12 gap-2 items-start">
                <div class="col-span-3 text-slate-600 font-semibold">Untuk Pembayaran</div>
                <div class="col-span-1 text-center font-bold">:</div>
                <div class="col-span-8 text-slate-800 leading-relaxed">
                    {{ $transaction->description }}
                    @if($transaction->project)
                        <div class="text-[11px] text-slate-500 font-medium mt-1">
                            Kegiatan: {{ $transaction->project->title }} (No. SPK: {{ $transaction->project->spk_number }})
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-12 gap-2 items-center pt-2">
                <div class="col-span-3 text-slate-600 font-semibold">Jumlah Uang</div>
                <div class="col-span-1 text-center font-bold">:</div>
                <div class="col-span-8">
                    <span class="inline-block px-4 py-2 bg-slate-900 text-white font-mono-print text-base font-bold rounded-xl tracking-wider">
                        @rupiah($transaction->amount)
                    </span>
                </div>
            </div>

            <!-- Tax Deduction Breakdown if exists -->
            @if($transaction->has_tax && $transaction->tax_total > 0)
            <div class="mt-4 p-3 bg-slate-50 rounded-xl border border-slate-200 text-[11px]">
                <div class="font-bold text-slate-700 mb-1">Rincian Pemotongan Pajak:</div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-slate-600">
                    @if($transaction->tax_ppn > 0)
                        <div>PPN 11%: <strong class="font-mono">@rupiah($transaction->tax_ppn)</strong></div>
                    @endif
                    @if($transaction->tax_pph21 > 0)
                        <div>PPh 21: <strong class="font-mono">@rupiah($transaction->tax_pph21)</strong></div>
                    @endif
                    @if($transaction->tax_pph22 > 0)
                        <div>PPh 22: <strong class="font-mono">@rupiah($transaction->tax_pph22)</strong></div>
                    @endif
                    @if($transaction->tax_pph23 > 0)
                        <div>PPh 23: <strong class="font-mono">@rupiah($transaction->tax_pph23)</strong></div>
                    @endif
                    @if($transaction->tax_pph4_2 > 0)
                        <div>PPh 4(2): <strong class="font-mono">@rupiah($transaction->tax_pph4_2)</strong></div>
                    @endif
                </div>
                <div class="flex justify-between font-bold text-slate-900 border-t border-slate-200 mt-2 pt-1.5">
                    <span>Jumlah Diterima Bersih:</span>
                    <span class="font-mono">@rupiah($transaction->net_amount)</span>
                </div>
            </div>
            @endif

        </div>

        <!-- Place and Date -->
        <div class="text-right text-xs text-slate-800 mt-8">
            Situbondo, {{ $transaction->transaction_date->translatedFormat('d F Y') }}
        </div>

        <!-- 3-Column Signatory Block -->
        <div class="mt-6 grid grid-cols-3 gap-4 text-center text-xs text-slate-900">
            <!-- Left: Kepala Sekolah -->
            <div>
                <p class="font-semibold text-slate-600">Setuju Dibayar,</p>
                <p class="font-bold">Kepala Sekolah</p>
                <div class="h-24"></div>
                <p class="font-bold text-xs underline">{{ $transaction->school->principal_name }}</p>
                <p class="text-[10px] text-slate-600">NIP. {{ $transaction->school->principal_nip ?? '-' }}</p>
            </div>

            <!-- Middle: Bendahara -->
            <div>
                <p class="font-semibold text-slate-600">Lunas Dibayar,</p>
                <p class="font-bold">Bendahara Revitalisasi</p>
                <div class="h-24"></div>
                <p class="font-bold text-xs underline">{{ $transaction->school->treasurer_name }}</p>
                <p class="text-[10px] text-slate-600">NIP. {{ $transaction->school->treasurer_nip ?? '-' }}</p>
            </div>

            <!-- Right: Penerima Uang -->
            <div>
                <p class="font-semibold text-slate-600">Yang Menerima,</p>
                <p class="font-bold">Penerima Uang / Toko</p>
                <div class="h-24"></div>
                <p class="font-bold text-xs underline">{{ $transaction->recipient_name ?? '............................................' }}</p>
                <p class="text-[10px] text-slate-600">{{ $transaction->recipient_address ?? '-' }}</p>
            </div>
        </div>

    </div>

</body>
</html>

