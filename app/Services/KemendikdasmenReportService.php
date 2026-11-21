<?php

namespace App\Services;

use App\Models\School;
use App\Models\Transaction;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KemendikdasmenReportService
{
    /**
     * Nama-nama hari dalam bahasa Indonesia.
     */
    public static array $days = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    /**
     * Nama-nama bulan dalam bahasa Indonesia.
     */
    public static array $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * Mengambil daftar bulan proyek yang tersedia untuk sekolah tertentu.
     * Mengelompokkan transaksi menjadi Bulan ke-1, Bulan ke-2, dst.
     */
    public function getAvailableProjectMonths(School $school): array
    {
        $transactions = Transaction::where('school_id', $school->id)
            ->orderBy('transaction_date')
            ->get();

        if ($transactions->isEmpty()) {
            $now = Carbon::now();
            return [
                1 => [
                    'index' => 1,
                    'year' => (int)$now->year,
                    'month' => (int)$now->month,
                    'key' => $now->format('Y-m'),
                    'label' => "Bulan ke-1 (" . self::$months[(int)$now->month] . " {$now->year})",
                    'month_name' => self::$months[(int)$now->month],
                    'count' => 0,
                ]
            ];
        }

        $byMonth = $transactions->groupBy(fn($t) => $t->transaction_date->format('Y-m'));
        $projectMonths = [];
        $index = 1;

        foreach ($byMonth as $ym => $txGroup) {
            [$y, $m] = explode('-', $ym);
            $y = (int)$y;
            $m = (int)$m;
            $projectMonths[$index] = [
                'index' => $index,
                'year' => $y,
                'month' => $m,
                'key' => $ym,
                'label' => "Bulan ke-{$index} (" . self::$months[$m] . " {$y})",
                'month_name' => self::$months[$m],
                'count' => $txGroup->count(),
            ];
            $index++;
        }

        return $projectMonths;
    }

    /**
     * Menghasilkan struktur data lengkap untuk 4 Buku Kemendikdasmen pada bulan tertentu:
     * 1. BKU (Buku Kas Umum)
     * 2. BPKT (Buku Pembantu Kas Tunai)
     * 3. BB (Buku Bank)
     * 4. BPP (Buku Pembantu Pajak - Alt 1 dan Alt 2)
     * Beserta rekonsiliasi dan penutupan akhir bulan.
     */
    public function getMonthlyReportData(School $school, int $monthIndex = 1): array
    {
        $projectMonths = $this->getAvailableProjectMonths($school);
        $currentMonthInfo = $projectMonths[$monthIndex] ?? reset($projectMonths);
        $year = $currentMonthInfo['year'];
        $month = $currentMonthInfo['month'];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // 1. Hitung Saldo Awal Kumulatif sebelum bulan berjalan
        $pastTxs = Transaction::where('school_id', $school->id)
            ->where('transaction_date', '<', $startDate)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $saldoAwalBku = 0;
        $saldoAwalBb = 0;
        $saldoAwalBpkt = 0;
        $saldoAwalPajak = 0;

        foreach ($pastTxs as $pt) {
            if ($pt->type === 'penerimaan_dana' || $pt->type === 'bunga_bank') {
                $saldoAwalBku += (float)$pt->amount;
                $saldoAwalBb += (float)$pt->amount;
            } elseif ($pt->type === 'tarik_tunai') {
                $saldoAwalBb -= (float)$pt->amount;
                $saldoAwalBpkt += (float)$pt->amount;
            } elseif ($pt->type === 'setor_tunai') {
                $saldoAwalBpkt -= (float)$pt->amount;
                $saldoAwalBb += (float)$pt->amount;
            } elseif ($pt->type === 'belanja_tunai') {
                $saldoAwalBku -= (float)$pt->amount;
                $saldoAwalBpkt -= (float)$pt->amount;
                if ($pt->has_tax && $pt->tax_total > 0) {
                    $saldoAwalBku += (float)$pt->tax_total;
                    $saldoAwalBpkt += (float)$pt->tax_total;
                    $saldoAwalPajak += (float)$pt->tax_total;
                    if ($pt->tax_status === 'disetor' || !empty($pt->tax_ntpn)) {
                        $saldoAwalBku -= (float)$pt->tax_total;
                        $saldoAwalBpkt -= (float)$pt->tax_total;
                        $saldoAwalPajak -= (float)$pt->tax_total;
                    }
                }
            } elseif ($pt->type === 'belanja_transfer') {
                $saldoAwalBku -= (float)$pt->amount;
                $saldoAwalBb -= (float)$pt->amount;
                if ($pt->has_tax && $pt->tax_total > 0) {
                    $saldoAwalBku += (float)$pt->tax_total;
                    $saldoAwalBb += (float)$pt->tax_total;
                    $saldoAwalPajak += (float)$pt->tax_total;
                    if ($pt->tax_status === 'disetor' || !empty($pt->tax_ntpn)) {
                        $saldoAwalBku -= (float)$pt->tax_total;
                        $saldoAwalBb -= (float)$pt->tax_total;
                        $saldoAwalPajak -= (float)$pt->tax_total;
                    }
                }
            } elseif ($pt->type === 'biaya_bank') {
                $saldoAwalBku -= (float)$pt->amount;
                $saldoAwalBb -= (float)$pt->amount;
            }
        }

        // 2. Ambil transaksi bulan berjalan
        $currentTxs = Transaction::where('school_id', $school->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->orderByRaw("CASE 
                WHEN type = 'penerimaan_dana' THEN 1 
                WHEN type = 'tarik_tunai' THEN 2 
                WHEN type = 'bunga_bank' THEN 3 
                WHEN type = 'setor_tunai' THEN 4 
                ELSE 5 
            END ASC")
            ->orderBy('id')
            ->get();

        // Counter untuk koding nomor bukti Kemendikdasmen (KW-BB-NNN, NT-BB-NNN, BPN-BB-NNN, dsb)
        $monthStr = sprintf('%02d', $month);
        $seqKW = 1;
        $seqNT = 1;
        $seqTF = 1;
        $seqCEK = 1;
        $seqBPN = 1;
        $seqBPD = 1;
        $seqSPL = 1;

        $bkuRows = [];
        $bpktRows = [];
        $bbRows = [];
        $bppAlt1Rows = [];
        $bppAlt2Rows = [];
        $dtRows = [];

        // Inisialisasi saldo running
        $bkuBalance = $saldoAwalBku;
        $bbBalance = $saldoAwalBb;
        $bpktBalance = $saldoAwalBpkt;
        $bppBalance = $saldoAwalPajak;

        // Baris Saldo Awal jika bulan > 1
        if ($monthIndex > 1) {
            $bkuRows[] = [
                'tanggal' => $startDate->copy(),
                'uraian' => 'Saldo awal (saldo akhir bulan sebelumnya)',
                'no_bukti' => '-',
                'penerimaan' => $saldoAwalBku,
                'pengeluaran' => 0,
                'saldo' => $bkuBalance,
                'tgl_setor_pajak' => null,
                'uraian_setor_pajak' => null,
                'is_initial' => true,
            ];

            $bbRows[] = [
                'tanggal' => $startDate->copy(),
                'uraian' => 'Saldo awal (saldo akhir bulan sebelumnya)',
                'no_bukti' => '-',
                'penerimaan' => $saldoAwalBb,
                'pengeluaran' => 0,
                'saldo' => $bbBalance,
                'is_initial' => true,
            ];

            $bpktRows[] = [
                'tanggal' => $startDate->copy(),
                'uraian' => 'Saldo awal (saldo akhir bulan sebelumnya)',
                'no_bukti' => '-',
                'penerimaan' => $saldoAwalBpkt,
                'pengeluaran' => 0,
                'saldo' => $bpktBalance,
                'is_initial' => true,
            ];

            if ($saldoAwalPajak > 0) {
                $bppAlt1Rows[] = [
                    'tanggal' => $startDate->copy(),
                    'uraian' => 'Saldo awal pajak belum disetor',
                    'no_bukti' => '-',
                    'jenis_bukti' => '-',
                    'nomor_bukti_pajak' => '-',
                    'jenis_pajak' => 'Saldo Awal',
                    'penerimaan' => $saldoAwalPajak,
                    'pengeluaran' => 0,
                    'saldo' => $bppBalance,
                    'is_initial' => true,
                ];

                $bppAlt2Rows[] = [
                    'tanggal' => $startDate->copy(),
                    'uraian' => 'Saldo awal pajak belum disetor',
                    'no_bukti' => '-',
                    'jenis_bukti' => '-',
                    'nomor_bukti_pajak' => '-',
                    'ppn' => 0,
                    'pph4_2' => 0,
                    'pph21' => $saldoAwalPajak, // Default grouping jika ada
                    'pph22' => 0,
                    'pph23' => 0,
                    'pajak_daerah' => 0,
                    'is_initial' => true,
                ];
            }
        }

        // 3. Proses setiap transaksi ke dalam 4 Buku
        foreach ($currentTxs as $t) {
            $amt = (float)$t->amount;
            $tDate = $t->transaction_date;
            $isCash = ($t->payment_method === 'kas_tunai' || $t->type === 'belanja_tunai');

            if ($t->type === 'penerimaan_dana') {
                $bukti = sprintf("TF-%s-%03d", $monthStr, $seqTF++);
                $uraian = $monthIndex === 1 
                    ? ($t->description ?: "Penerimaan dana tahap 1 (70%)") 
                    : ($t->description ?: "Penerimaan dana bantuan revitalisasi");

                $bkuBalance += $amt;
                $bbBalance += $amt;

                $bkuRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => $uraian,
                    'no_bukti' => $bukti,
                    'penerimaan' => $amt,
                    'pengeluaran' => 0,
                    'saldo' => $bkuBalance,
                    'tgl_setor_pajak' => null,
                    'uraian_setor_pajak' => null,
                ];

                $bbRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => $uraian,
                    'no_bukti' => $bukti,
                    'penerimaan' => $amt,
                    'pengeluaran' => 0,
                    'saldo' => $bbBalance,
                ];

                $dtRows[] = [
                    'is_main' => true,
                    'tanggal' => $tDate,
                    'info' => $t->description ?: "Dana Revitalisasi SD tahap {$monthIndex} masuk ke rekening bank sekolah.",
                    'uraian' => $uraian,
                    'nilai' => $amt,
                    'bpkt' => '–',
                    'bb' => 'Penerimaan',
                    'bpp_alt1' => '–',
                    'bpp_alt2' => '–',
                    'bku' => 'Penerimaan',
                    'no_bukti' => $bukti,
                ];
            } elseif ($t->type === 'tarik_tunai') {
                $bukti = sprintf("CEK-%s-%03d", $monthStr, $seqCEK++);

                // Di BKU: 2 baris (keluar dari bank, masuk ke kas tunai)
                $bkuBalance -= $amt;
                $bkuRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => 'Penarikan tunai dari rekening bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => 0,
                    'pengeluaran' => $amt,
                    'saldo' => $bkuBalance,
                    'tgl_setor_pajak' => null,
                    'uraian_setor_pajak' => null,
                ];

                $bkuBalance += $amt;
                $bkuRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => 'Penerimaan kas tunai dari penarikan rekening bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => $amt,
                    'pengeluaran' => 0,
                    'saldo' => $bkuBalance,
                    'tgl_setor_pajak' => null,
                    'uraian_setor_pajak' => null,
                ];

                // Di BB: 1 baris Pengeluaran
                $bbBalance -= $amt;
                $bbRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => 'Penarikan tunai dari rekening bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => 0,
                    'pengeluaran' => $amt,
                    'saldo' => $bbBalance,
                ];

                // Di BPKT: 1 baris Penerimaan
                $bpktBalance += $amt;
                $bpktRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => 'Penerimaan kas tunai dari penarikan rekening bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => $amt,
                    'pengeluaran' => 0,
                    'saldo' => $bpktBalance,
                ];

                $dtRows[] = [
                    'is_main' => true,
                    'tanggal' => $tDate,
                    'info' => "Bendahara menarik uang tunai Rp " . number_format($amt, 0, ',', '.') . " dari rekening bank sekolah untuk kebutuhan belanja operasional/swakelola.",
                    'uraian' => 'Penarikan tunai dari rekening bank',
                    'nilai' => $amt,
                    'bpkt' => '–',
                    'bb' => 'Pengeluaran',
                    'bpp_alt1' => '–',
                    'bpp_alt2' => '–',
                    'bku' => 'Pengeluaran',
                    'no_bukti' => $bukti,
                ];
                $dtRows[] = [
                    'is_main' => false,
                    'tanggal' => null,
                    'info' => null,
                    'uraian' => 'Penerimaan kas tunai dari penarikan rekening bank',
                    'nilai' => $amt,
                    'bpkt' => 'Penerimaan',
                    'bb' => '–',
                    'bpp_alt1' => '–',
                    'bpp_alt2' => '–',
                    'bku' => 'Penerimaan',
                    'no_bukti' => $bukti,
                ];
            } elseif ($t->type === 'setor_tunai') {
                $bukti = sprintf("CEK-%s-%03d", $monthStr, $seqCEK++);

                $bkuBalance -= $amt;
                $bkuRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => 'Penyetoran kas tunai ke rekening bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => 0,
                    'pengeluaran' => $amt,
                    'saldo' => $bkuBalance,
                    'tgl_setor_pajak' => null,
                    'uraian_setor_pajak' => null,
                ];

                $bkuBalance += $amt;
                $bkuRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => 'Penerimaan setoran kas tunai di rekening bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => $amt,
                    'pengeluaran' => 0,
                    'saldo' => $bkuBalance,
                    'tgl_setor_pajak' => null,
                    'uraian_setor_pajak' => null,
                ];

                $bpktBalance -= $amt;
                $bpktRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => 'Penyetoran kas tunai ke rekening bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => 0,
                    'pengeluaran' => $amt,
                    'saldo' => $bpktBalance,
                ];

                $bbBalance += $amt;
                $bbRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => 'Penerimaan setoran kas tunai di rekening bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => $amt,
                    'pengeluaran' => 0,
                    'saldo' => $bbBalance,
                ];

                $dtRows[] = [
                    'is_main' => true,
                    'tanggal' => $tDate,
                    'info' => "Bendahara menyetor sisa kas tunai Rp " . number_format($amt, 0, ',', '.') . " ke rekening bank sekolah.",
                    'uraian' => 'Penyetoran kas tunai ke rekening bank',
                    'nilai' => $amt,
                    'bpkt' => 'Pengeluaran',
                    'bb' => '–',
                    'bpp_alt1' => '–',
                    'bpp_alt2' => '–',
                    'bku' => 'Pengeluaran',
                    'no_bukti' => $bukti,
                ];
                $dtRows[] = [
                    'is_main' => false,
                    'tanggal' => null,
                    'info' => null,
                    'uraian' => 'Penerimaan setoran kas tunai di rekening bank',
                    'nilai' => $amt,
                    'bpkt' => '–',
                    'bb' => 'Penerimaan',
                    'bpp_alt1' => '–',
                    'bpp_alt2' => '–',
                    'bku' => 'Penerimaan',
                    'no_bukti' => $bukti,
                ];
            } elseif (in_array($t->type, ['belanja_tunai', 'belanja_transfer'])) {
                // Tentukan prefix kode bukti (NT untuk nota konsumsi/atk kecil, SPL untuk siplah, KW untuk kuitansi)
                $descLower = strtolower($t->description);
                if (str_contains($descLower, 'konsumsi') || str_contains($descLower, 'snack') || str_contains($descLower, 'fotokopi') || str_contains($descLower, 'jilid') || str_contains($descLower, 'nota')) {
                    $bukti = sprintf("NT-%s-%03d", $monthStr, $seqNT++);
                } elseif (str_contains($descLower, 'siplah')) {
                    $bukti = sprintf("SPL-%s-%03d", $monthStr, $seqSPL++);
                } else {
                    $bukti = sprintf("KW-%s-%03d", $monthStr, $seqKW++);
                }

                // 1. Catat Belanja Bruto (Pengeluaran)
                $bkuBalance -= $amt;
                $belanjaRow = [
                    'tanggal' => $tDate,
                    'uraian' => $t->description,
                    'no_bukti' => $bukti,
                    'penerimaan' => 0,
                    'pengeluaran' => $amt,
                    'saldo' => $bkuBalance,
                    'tgl_setor_pajak' => null,
                    'uraian_setor_pajak' => null,
                ];
                $bkuRows[] = $belanjaRow;

                if ($isCash) {
                    $bpktBalance -= $amt;
                    $bpktRows[] = [
                        'tanggal' => $tDate,
                        'uraian' => $t->description,
                        'no_bukti' => $bukti,
                        'penerimaan' => 0,
                        'pengeluaran' => $amt,
                        'saldo' => $bpktBalance,
                    ];
                } else {
                    $bbBalance -= $amt;
                    $bbRows[] = [
                        'tanggal' => $tDate,
                        'uraian' => $t->description,
                        'no_bukti' => $bukti,
                        'penerimaan' => 0,
                        'pengeluaran' => $amt,
                        'saldo' => $bbBalance,
                    ];
                }

                $infoDesc = $t->description . ($t->has_tax && $t->tax_total > 0 ? " (" . ($isCash ? "dibayar tunai" : "transfer bank") . ", dipungut/dipotong pajak)" : ($isCash ? " (dibayar tunai)" : " (transfer bank)"));
                $dtRows[] = [
                    'is_main' => true,
                    'tanggal' => $tDate,
                    'info' => $infoDesc,
                    'uraian' => $t->description,
                    'nilai' => $amt,
                    'bpkt' => $isCash ? 'Pengeluaran' : '–',
                    'bb' => $isCash ? '–' : 'Pengeluaran',
                    'bpp_alt1' => '–',
                    'bpp_alt2' => '–',
                    'bku' => 'Pengeluaran',
                    'no_bukti' => $bukti,
                ];

                // 2. Jika transaksi memiliki potongan/pungutan pajak (Metode Bruto)
                if ($t->has_tax && $t->tax_total > 0) {
                    $taxes = [
                        'PPN' => [
                            'amount' => (float)$t->tax_ppn,
                            'title' => 'Pungutan PPN 11%',
                            'jenis_bukti' => 'Faktur Pajak',
                            'nomor_bukti' => '0400' . date('y') . sprintf('%010d', $t->id),
                            'col' => 'ppn',
                        ],
                        'PPH 22' => [
                            'amount' => (float)$t->tax_pph22,
                            'title' => 'Pungutan PPh 22 (1.5%)',
                            'jenis_bukti' => 'Bukti Pungut',
                            'nomor_bukti' => 'BPU-' . date('ym') . '-' . sprintf('%04d', $t->id),
                            'col' => 'pph22',
                        ],
                        'PPH 21' => [
                            'amount' => (float)$t->tax_pph21,
                            'title' => 'Potongan PPh 21',
                            'jenis_bukti' => 'Bukti Potong',
                            'nomor_bukti' => 'BP21-' . date('ym') . '-' . sprintf('%04d', $t->id),
                            'col' => 'pph21',
                        ],
                        'PPH 4' => [
                            'amount' => (float)$t->tax_pph4_2,
                            'title' => 'Potongan PPh 4 ayat (2)',
                            'jenis_bukti' => 'Bukti Potong',
                            'nomor_bukti' => 'BPU-' . date('ym') . '-' . sprintf('%04d', $t->id),
                            'col' => 'pph4_2',
                        ],
                        'PPH 23' => [
                            'amount' => (float)$t->tax_pph23,
                            'title' => 'Potongan PPh 23 (2%)',
                            'jenis_bukti' => 'Bukti Potong',
                            'nomor_bukti' => 'BPU-' . date('ym') . '-' . sprintf('%04d', $t->id),
                            'col' => 'pph23',
                        ],
                    ];

                    $isSetor = ($t->tax_status === 'disetor' || !empty($t->tax_ntpn));
                    $setorDate = $t->tax_payment_date ?: $tDate;
                    $ntpn = $t->tax_ntpn ?: ('NTPN' . strtoupper(substr(md5($t->id . 'TAX'), 0, 12)));

                    foreach ($taxes as $taxKey => $taxInfo) {
                        $taxVal = $taxInfo['amount'];
                        if ($taxVal <= 0) {
                            continue;
                        }

                        $uraianPungut = $taxInfo['title'] . " atas " . self::truncateString($t->description, 80);
                        $uraianSetor = "Setor " . str_replace(['Pungutan ', 'Potongan '], '', $taxInfo['title']) . " atas " . self::truncateString($t->description, 80);

                        // PUNGUTAN: Masuk sebagai Penerimaan di BKU dan Kas/Bank
                        $bkuBalance += $taxVal;
                        $bkuRows[] = [
                            'tanggal' => $tDate,
                            'uraian' => $uraianPungut,
                            'no_bukti' => $bukti,
                            'penerimaan' => $taxVal,
                            'pengeluaran' => 0,
                            'saldo' => $bkuBalance,
                            'tgl_setor_pajak' => $isSetor ? $setorDate : null,
                            'uraian_setor_pajak' => $isSetor ? $uraianSetor : null,
                        ];

                        if ($isCash) {
                            $bpktBalance += $taxVal;
                            $bpktRows[] = [
                                'tanggal' => $tDate,
                                'uraian' => $uraianPungut,
                                'no_bukti' => $bukti,
                                'penerimaan' => $taxVal,
                                'pengeluaran' => 0,
                                'saldo' => $bpktBalance,
                            ];
                        } else {
                            $bbBalance += $taxVal;
                            $bbRows[] = [
                                'tanggal' => $tDate,
                                'uraian' => $uraianPungut,
                                'no_bukti' => $bukti,
                                'penerimaan' => $taxVal,
                                'pengeluaran' => 0,
                                'saldo' => $bbBalance,
                            ];
                        }

                        // Catat ke Buku Pembantu Pajak (BPP)
                        $bppBalance += $taxVal;
                        $bppAlt1Rows[] = [
                            'tanggal' => $tDate,
                            'uraian' => $uraianPungut,
                            'no_bukti' => $bukti,
                            'jenis_bukti' => $taxInfo['jenis_bukti'],
                            'nomor_bukti_pajak' => $taxInfo['nomor_bukti'],
                            'jenis_pajak' => $taxKey,
                            'penerimaan' => $taxVal,
                            'pengeluaran' => 0,
                            'saldo' => $bppBalance,
                        ];

                        $alt2Pungut = [
                            'tanggal' => $tDate,
                            'uraian' => $uraianPungut,
                            'no_bukti' => $bukti,
                            'jenis_bukti' => $taxInfo['jenis_bukti'],
                            'nomor_bukti_pajak' => $taxInfo['nomor_bukti'],
                            'ppn' => ($taxInfo['col'] === 'ppn' ? $taxVal : 0),
                            'pph4_2' => ($taxInfo['col'] === 'pph4_2' ? $taxVal : 0),
                            'pph21' => ($taxInfo['col'] === 'pph21' ? $taxVal : 0),
                            'pph22' => ($taxInfo['col'] === 'pph22' ? $taxVal : 0),
                            'pph23' => ($taxInfo['col'] === 'pph23' ? $taxVal : 0),
                            'pajak_daerah' => 0,
                        ];
                        $bppAlt2Rows[] = $alt2Pungut;

                        $alt2Key = match ($taxKey) {
                            'PPN' => 'PPN',
                            'PPH 22' => 'PPh 22',
                            'PPH 21' => 'PPh 21',
                            'PPH 4' => 'PPh 4 Ayat 2',
                            'PPH 23' => 'PPh 23',
                            default => $taxKey,
                        };

                        $dtRows[] = [
                            'is_main' => false,
                            'tanggal' => null,
                            'info' => null,
                            'uraian' => $uraianPungut,
                            'nilai' => $taxVal,
                            'bpkt' => $isCash ? 'Penerimaan' : '–',
                            'bb' => $isCash ? '–' : 'Penerimaan',
                            'bpp_alt1' => 'Penerimaan – ' . $taxKey,
                            'bpp_alt2' => $alt2Key . ' (+)',
                            'bku' => 'Penerimaan',
                            'no_bukti' => $bukti,
                        ];

                        // PENYETORAN: Jika sudah disetor
                        if ($isSetor) {
                            $bpnBukti = sprintf("BPN-%s-%03d", $monthStr, $seqBPN++);

                            $bkuBalance -= $taxVal;
                            $bkuRows[] = [
                                'tanggal' => $setorDate,
                                'uraian' => $uraianSetor,
                                'no_bukti' => $bpnBukti,
                                'penerimaan' => 0,
                                'pengeluaran' => $taxVal,
                                'saldo' => $bkuBalance,
                                'tgl_setor_pajak' => null,
                                'uraian_setor_pajak' => null,
                            ];

                            if ($isCash) {
                                $bpktBalance -= $taxVal;
                                $bpktRows[] = [
                                    'tanggal' => $setorDate,
                                    'uraian' => $uraianSetor,
                                    'no_bukti' => $bpnBukti,
                                    'penerimaan' => 0,
                                    'pengeluaran' => $taxVal,
                                    'saldo' => $bpktBalance,
                                ];
                            } else {
                                $bbBalance -= $taxVal;
                                $bbRows[] = [
                                    'tanggal' => $setorDate,
                                    'uraian' => $uraianSetor,
                                    'no_bukti' => $bpnBukti,
                                    'penerimaan' => 0,
                                    'pengeluaran' => $taxVal,
                                    'saldo' => $bbBalance,
                                ];
                            }

                            $bppBalance -= $taxVal;
                            $bppAlt1Rows[] = [
                                'tanggal' => $setorDate,
                                'uraian' => $uraianSetor,
                                'no_bukti' => $bpnBukti,
                                'jenis_bukti' => 'NTPN',
                                'nomor_bukti_pajak' => $ntpn,
                                'jenis_pajak' => $taxKey,
                                'penerimaan' => 0,
                                'pengeluaran' => $taxVal,
                                'saldo' => $bppBalance,
                            ];

                            $alt2Setor = [
                                'tanggal' => $setorDate,
                                'uraian' => $uraianSetor,
                                'no_bukti' => $bpnBukti,
                                'jenis_bukti' => 'NTPN',
                                'nomor_bukti_pajak' => $ntpn,
                                'ppn' => ($taxInfo['col'] === 'ppn' ? -$taxVal : 0),
                                'pph4_2' => ($taxInfo['col'] === 'pph4_2' ? -$taxVal : 0),
                                'pph21' => ($taxInfo['col'] === 'pph21' ? -$taxVal : 0),
                                'pph22' => ($taxInfo['col'] === 'pph22' ? -$taxVal : 0),
                                'pph23' => ($taxInfo['col'] === 'pph23' ? -$taxVal : 0),
                                'pajak_daerah' => 0,
                            ];
                            $bppAlt2Rows[] = $alt2Setor;

                            $dtRows[] = [
                                'is_main' => true,
                                'tanggal' => $setorDate,
                                'info' => "Penyetoran " . $taxKey . " via NTPN {$ntpn} (" . ($isCash ? "tunai" : "rekening bank") . ")",
                                'uraian' => $uraianSetor,
                                'nilai' => $taxVal,
                                'bpkt' => $isCash ? 'Pengeluaran' : '–',
                                'bb' => $isCash ? '–' : 'Pengeluaran',
                                'bpp_alt1' => 'Pengeluaran – ' . $taxKey,
                                'bpp_alt2' => $alt2Key . ' (−)',
                                'bku' => 'Pengeluaran',
                                'no_bukti' => $bpnBukti,
                            ];
                        }
                    }
                }
            } elseif ($t->type === 'biaya_bank') {
                $bukti = sprintf("KW-%s-%03d", $monthStr, $seqKW++);
                $bkuBalance -= $amt;
                $bbBalance -= $amt;

                $bkuRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => $t->description ?: 'Biaya administrasi perbankan',
                    'no_bukti' => $bukti,
                    'penerimaan' => 0,
                    'pengeluaran' => $amt,
                    'saldo' => $bkuBalance,
                    'tgl_setor_pajak' => null,
                    'uraian_setor_pajak' => null,
                ];

                $bbRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => $t->description ?: 'Biaya administrasi perbankan',
                    'no_bukti' => $bukti,
                    'penerimaan' => 0,
                    'pengeluaran' => $amt,
                    'saldo' => $bbBalance,
                ];

                $dtRows[] = [
                    'is_main' => true,
                    'tanggal' => $tDate,
                    'info' => 'Biaya administrasi perbankan',
                    'uraian' => $t->description ?: 'Biaya administrasi perbankan',
                    'nilai' => $amt,
                    'bpkt' => '–',
                    'bb' => 'Pengeluaran',
                    'bpp_alt1' => '–',
                    'bpp_alt2' => '–',
                    'bku' => 'Pengeluaran',
                    'no_bukti' => $bukti,
                ];
            } elseif ($t->type === 'bunga_bank') {
                $bukti = sprintf("TF-%s-%03d", $monthStr, $seqTF++);
                $bkuBalance += $amt;
                $bbBalance += $amt;

                $bkuRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => $t->description ?: 'Penerimaan jasa giro / bunga bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => $amt,
                    'pengeluaran' => 0,
                    'saldo' => $bkuBalance,
                    'tgl_setor_pajak' => null,
                    'uraian_setor_pajak' => null,
                ];

                $bbRows[] = [
                    'tanggal' => $tDate,
                    'uraian' => $t->description ?: 'Penerimaan jasa giro / bunga bank',
                    'no_bukti' => $bukti,
                    'penerimaan' => $amt,
                    'pengeluaran' => 0,
                    'saldo' => $bbBalance,
                ];

                $dtRows[] = [
                    'is_main' => true,
                    'tanggal' => $tDate,
                    'info' => 'Penerimaan jasa giro / bunga bank',
                    'uraian' => $t->description ?: 'Penerimaan jasa giro / bunga bank',
                    'nilai' => $amt,
                    'bpkt' => '–',
                    'bb' => 'Penerimaan',
                    'bpp_alt1' => '–',
                    'bpp_alt2' => '–',
                    'bku' => 'Penerimaan',
                    'no_bukti' => $bukti,
                ];
            }
        }

        // Hitung total penerimaan dan pengeluaran per buku
        $totalBkuPenerimaan = array_sum(array_column($bkuRows, 'penerimaan'));
        $totalBkuPengeluaran = array_sum(array_column($bkuRows, 'pengeluaran'));

        $totalBbPenerimaan = array_sum(array_column($bbRows, 'penerimaan'));
        $totalBbPengeluaran = array_sum(array_column($bbRows, 'pengeluaran'));

        $totalBpktPenerimaan = array_sum(array_column($bpktRows, 'penerimaan'));
        $totalBpktPengeluaran = array_sum(array_column($bpktRows, 'pengeluaran'));

        $totalBppPenerimaan = array_sum(array_column($bppAlt1Rows, 'penerimaan'));
        $totalBppPengeluaran = array_sum(array_column($bppAlt1Rows, 'pengeluaran'));

        // Rekonsiliasi & Perbedaan (Harus Rp 0)
        $totalBankDanKas = $bbBalance + $bpktBalance;
        $perbedaan = round($bkuBalance - $totalBankDanKas, 2);
        $isBalanced = abs($perbedaan) < 0.01;

        // Tanggal & Hari Penutupan BKU
        $lastDayOfMonth = $endDate->copy();
        $hariPenutupan = self::$days[$lastDayOfMonth->format('l')] ?? $lastDayOfMonth->format('l');
        $tglPenutupanStr = $lastDayOfMonth->day . ' ' . self::$months[$lastDayOfMonth->month] . ' ' . $lastDayOfMonth->year;
        $kabupaten = $school->kabupaten ?: 'Situbondo';

        return [
            'school' => $school,
            'month_info' => $currentMonthInfo,
            'month_index' => $monthIndex,
            'all_project_months' => $projectMonths,
            'bku' => [
                'rows' => $bkuRows,
                'saldo_awal' => $saldoAwalBku,
                'total_penerimaan' => $totalBkuPenerimaan,
                'total_pengeluaran' => $totalBkuPengeluaran,
                'saldo_akhir' => $bkuBalance,
            ],
            'bpkt' => [
                'rows' => $bpktRows,
                'saldo_awal' => $saldoAwalBpkt,
                'total_penerimaan' => $totalBpktPenerimaan,
                'total_pengeluaran' => $totalBpktPengeluaran,
                'saldo_akhir' => $bpktBalance,
            ],
            'bb' => [
                'rows' => $bbRows,
                'saldo_awal' => $saldoAwalBb,
                'total_penerimaan' => $totalBbPenerimaan,
                'total_pengeluaran' => $totalBbPengeluaran,
                'saldo_akhir' => $bbBalance,
            ],
            'bpp' => [
                'alt1_rows' => $bppAlt1Rows,
                'alt2_rows' => $bppAlt2Rows,
                'saldo_awal' => $saldoAwalPajak,
                'total_dipungut' => $totalBppPenerimaan,
                'total_disetor' => $totalBppPengeluaran,
                'saldo_belum_disetor' => $bppBalance,
            ],
            'daftar_transaksi' => [
                'rows' => $dtRows,
            ],
            'reconciliation' => [
                'saldo_bku' => $bkuBalance,
                'saldo_bank' => $bbBalance,
                'saldo_kas_tunai' => $bpktBalance,
                'total_bank_tunai' => $totalBankDanKas,
                'perbedaan' => $perbedaan,
                'is_balanced' => $isBalanced,
            ],
            'closing' => [
                'hari' => $hariPenutupan,
                'tanggal_str' => $tglPenutupanStr,
                'lokasi' => $kabupaten,
                'kalimat_penutupan' => "Pada hari ini {$hariPenutupan}, tanggal {$tglPenutupanStr}, Buku Kas Umum ditutup dengan keadaan/posisi buku sebagai berikut:",
                'kepala_sekolah' => [
                    'jabatan' => "Kepala {$school->name}",
                    'nama' => $school->principal_name ?: '.........................................',
                    'nip' => $school->principal_nip ?: '-',
                ],
                'ketua_p2sp' => [
                    'jabatan' => 'Ketua P2SP',
                    'nama' => 'Hendra Gunawan', // Dapat disesuaikan atau diinput di form
                ],
                'bendahara' => [
                    'jabatan' => 'Bendahara',
                    'nama' => $school->treasurer_name ?: '.........................................',
                    'nip' => $school->treasurer_nip ?: '-',
                ],
            ]
        ];
    }

    /**
     * Memotong string dengan aman agar tidak terlalu panjang di tabel laporan.
     */
    public static function truncateString(string $text, int $limit = 80): string
    {
        return mb_strimwidth($text, 0, $limit, '...');
    }

    /**
     * Menghasilkan file Excel Output Laporan Kemendikdasmen utuh (.xlsx)
     * Mengisi template resmi Kemendikdasmen dengan rumus-rumus persis seperti Bimtek.
     */
    public function exportExcel(School $school, ?int $targetMonth = null): StreamedResponse
    {
        $templatePath = storage_path('app/templates/kemendikdasmen/template_kemendikdasmen_kosong.xlsx');
        if (!file_exists($templatePath)) {
            // Salin dari refrensi jika belum ada
            $src = 'D:/RAN/SARPRAS FILE/SPT/BIMTEK PELAPORAN KEU SD/Materi Bimtek Percepatan NEW/Materi 3_ Laporan Keuangan dan Perpajakan/261030 - Format Kosong BKU BPKT BB BPP - Revitalisasi SD 2026.xlsx';
            if (file_exists($src)) {
                @mkdir(dirname($templatePath), 0777, true);
                copy($src, $templatePath);
            } else {
                throw new \Exception("Master template Kemendikdasmen tidak ditemukan di storage.");
            }
        }

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($templatePath);

        $availableMonths = $this->getAvailableProjectMonths($school);
        $monthsToExport = $targetMonth ? [$targetMonth => $availableMonths[$targetMonth]] : $availableMonths;

        // Ambil data untuk setiap bulan aktif
        $monthsData = [];
        foreach ($monthsToExport as $mIdx => $mInfo) {
            $monthsData[$mIdx] = $this->getMonthlyReportData($school, $mIdx);
        }

        // 0. Buat sheet 'Daftar Transaksi' sebagai tab pertama (sesuai file Contoh Bimtek Kemendikdasmen)
        $this->generateDaftarTransaksiSheet($spreadsheet, $school, $monthsData);

        $prevTotals = [];

        // Isi setiap bulan ke sheet template BKU{m}, BPKT{m}, BB{m}, BPP{m} Alt1, BPP{m} Alt2
        foreach ($monthsData as $mIdx => $data) {
            if ($mIdx > 9) {
                break; // Template menyediakan sheet 1 s.d. 9
            }

            $mInfo = $data['month_info'];
            $monthTitle = "Bulan " . $mInfo['month_name'] . " " . $mInfo['year'];
            $closing = $data['closing'];

            $bkuRowCount = count($data['bku']['rows']);
            $bpktRowCount = count($data['bpkt']['rows']);
            $bbRowCount = count($data['bb']['rows']);
            $bppRowCount = count($data['bpp']['alt1_rows']);

            $bkuTotalRow = ($bkuRowCount > 100) ? (105 + ($bkuRowCount - 100)) : 105;
            $bpktTotalRow = ($bpktRowCount > 75) ? (80 + ($bpktRowCount - 75)) : 80;
            $bbTotalRow = ($bbRowCount > 75) ? (80 + ($bbRowCount - 75)) : 80;
            $bppTotalRow = ($bppRowCount > 100) ? (105 + ($bppRowCount - 100)) : 105;

            // 1. Sheet BKU{m}
            $sheetBku = $spreadsheet->getSheetByName("BKU{$mIdx}");
            if ($sheetBku) {
                $sheetBku->setCellValue('A2', $monthTitle);
                $this->populateBkuSheet($sheetBku, $data['bku']['rows'], $mIdx, $closing, $bbTotalRow, $bpktTotalRow, $bppTotalRow, $prevTotals);
            }

            // 2. Sheet BPKT{m}
            $sheetBpkt = $spreadsheet->getSheetByName("BPKT{$mIdx}");
            if ($sheetBpkt) {
                $sheetBpkt->setCellValue('A2', $monthTitle);
                $this->populateBpktSheet($sheetBpkt, $data['bpkt']['rows'], $mIdx, $closing, $prevTotals);
            }

            // 3. Sheet BB{m}
            $sheetBb = $spreadsheet->getSheetByName("BB{$mIdx}");
            if ($sheetBb) {
                $sheetBb->setCellValue('A2', $monthTitle);
                $this->populateBbSheet($sheetBb, $data['bb']['rows'], $mIdx, $closing, $prevTotals);
            }

            // 4. Sheet BPP{m} Alt1
            $sheetBppAlt1 = $spreadsheet->getSheetByName("BPP{$mIdx} Alt1");
            if ($sheetBppAlt1) {
                $sheetBppAlt1->setCellValue('A2', $monthTitle);
                $this->populateBppAlt1Sheet($sheetBppAlt1, $data['bpp']['alt1_rows'], $mIdx, $closing);
            }

            // 5. Sheet BPP{m} Alt2
            $sheetBppAlt2 = $spreadsheet->getSheetByName("BPP{$mIdx} Alt2");
            if ($sheetBppAlt2) {
                $sheetBppAlt2->setCellValue('A2', $monthTitle);
                $this->populateBppAlt2Sheet($sheetBppAlt2, $data['bpp']['alt2_rows'], $mIdx, $closing);
            }

            // Simpan info total row bulan ini untuk referensi bulan berikutnya
            $prevTotals[$mIdx] = [
                'bku' => $bkuTotalRow,
                'bb' => $bbTotalRow,
                'bpkt' => $bpktTotalRow,
                'bpp' => $bppTotalRow,
            ];
        }

        $schoolNameSlug = str_replace([' ', '/', '\\'], '_', $school->name);
        $periodSlug = $targetMonth ? "Bulan_{$targetMonth}" : "Lengkap_Semua_Bulan";
        $fileName = "Laporan_Kemendikdasmen_Revitalisasi_{$periodSlug}_{$schoolNameSlug}_" . date('Ymd_His') . ".xlsx";

        // Pastikan sheet Daftar Transaksi yang aktif terbuka saat pertama dibuka
        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Mengisi baris transaksi pada sheet BKU{m}.
     */
    /**
     * Mengisi baris transaksi pada sheet BKU{m}.
     */
    protected function populateBkuSheet($sheet, array $rows, int $mIdx, array $closing, int $bbTotalRow = 80, int $bpktTotalRow = 80, int $bppTotalRow = 105, array $prevTotals = []): void
    {
        // Template BKU menyediakan baris data mulai baris 5 s.d. 104 (100 baris). Total di baris 105.
        $startRow = 5;
        $rowCount = count($rows);

        // Jika bulan > 1, baris 5 mengambil saldo akhir BKU bulan sebelumnya
        if ($mIdx > 1 && isset($prevTotals[$mIdx - 1])) {
            $prevBkuTotal = $prevTotals[$mIdx - 1]['bku'];
            $sheet->setCellValue('E5', "='BKU" . ($mIdx - 1) . "'!G{$prevBkuTotal}");
        }

        // Jika jumlah baris melebihi 100 baris kosong bawaan template
        if ($rowCount > 100) {
            $extra = $rowCount - 100;
            $sheet->insertNewRowBefore(105, $extra);
            for ($r = 105; $r < 105 + $extra; $r++) {
                $prevR = $r - 1;
                $sheet->setCellValue("A{$r}", "=ROW()-ROW(\$A\$4)");
                $sheet->setCellValue("G{$r}", "=IF(AND(E{$r}=0,F{$r}=0),N(G{$prevR}),IF(AND(E{$r}<>0,F{$r}<>0),0,IF(AND(E{$r}<>0,F{$r}=0),N(G{$prevR})+E{$r},N(G{$prevR})-F{$r})))");
            }
        }

        // Isi data transaksi
        foreach ($rows as $i => $row) {
            $curRow = $startRow + $i;
            $sheet->setCellValue("A{$curRow}", "=ROW()-ROW(\$A\$4)");
            if ($row['tanggal'] instanceof Carbon) {
                $sheet->setCellValue("B{$curRow}", $row['tanggal']->format('d/m/y'));
            }
            $sheet->setCellValue("C{$curRow}", $row['uraian']);
            $sheet->setCellValue("D{$curRow}", $row['no_bukti']);

            // Jika bukan baris pertama pada bulan > 1 (yang sudah berisi rumus saldo awal)
            if (!($mIdx > 1 && $i === 0)) {
                if ($row['penerimaan'] > 0) {
                    $sheet->setCellValue("E{$curRow}", $row['penerimaan']);
                }
            }
            if ($row['pengeluaran'] > 0) {
                $sheet->setCellValue("F{$curRow}", $row['pengeluaran']);
            }

            // Kolom I & J (catatan setor pajak di luar tabel BKU)
            if (!empty($row['tgl_setor_pajak'])) {
                $sheet->setCellValue("I{$curRow}", $row['tgl_setor_pajak'] instanceof Carbon ? $row['tgl_setor_pajak']->format('d/m/y') : $row['tgl_setor_pajak']);
            }
            if (!empty($row['uraian_setor_pajak'])) {
                $sheet->setCellValue("J{$curRow}", $row['uraian_setor_pajak']);
            }
        }

        // Cari baris teks penutupan & tanda tangan
        $highestRow = $sheet->getHighestRow();
        for ($r = 105; $r <= $highestRow; $r++) {
            $valC = (string)$sheet->getCell("C{$r}")->getValue();
            $valD = (string)$sheet->getCell("D{$r}")->getValue();

            if (str_contains($valC, 'Pada hari ini')) {
                $sheet->setCellValue("C{$r}", $closing['kalimat_penutupan']);
            } elseif (str_contains($valC, '1. Saldo Bank')) {
                $sheet->setCellValue("E{$r}", "='BB{$mIdx}'!G{$bbTotalRow}");
            } elseif (str_contains($valC, '2. Saldo Pembantu Kas')) {
                $sheet->setCellValue("E{$r}", "=BPKT{$mIdx}!G{$bpktTotalRow}");
            } elseif (str_contains($valC, 'Pajak dipungut')) {
                $sheet->setCellValue("F{$r}", "=SUMPRODUCT(ABS(TblBPP{$mIdx}Alt2[[PPN]:[Pajak Daerah]])*NOT(ISNUMBER(MATCH(TblBPP{$mIdx}Alt2[Jenis Bukti Pajak],{\"NTPN\",\"NTPD\"},0))))+'BPP{$mIdx} Alt1'!H{$bppTotalRow}");
            } elseif (str_contains($valC, 'Pajak disetor')) {
                $sheet->setCellValue("F{$r}", "=SUMPRODUCT(ABS(TblBPP{$mIdx}Alt2[[PPN]:[Pajak Daerah]])*ISNUMBER(MATCH(TblBPP{$mIdx}Alt2[Jenis Bukti Pajak],{\"NTPN\",\"NTPD\"},0)))+'BPP{$mIdx} Alt1'!I{$bppTotalRow}");
            } elseif (str_contains($valD, '2026') && str_contains($valD, '…')) {
                $sheet->setCellValue("D{$r}", "{$closing['lokasi']}, {$closing['tanggal_str']}");
            } elseif ($valC === 'Kepala ....' || str_contains($valC, 'Kepala')) {
                $sheet->setCellValue("C{$r}", $closing['kepala_sekolah']['jabatan']);
                $sheet->setCellValue("D{$r}", $closing['ketua_p2sp']['jabatan']);
                $sheet->setCellValue("F{$r}", $closing['bendahara']['jabatan']);
            } elseif (str_contains($valC, '......')) {
                $sheet->setCellValue("C{$r}", "({$closing['kepala_sekolah']['nama']})");
                $sheet->setCellValue("D{$r}", "({$closing['ketua_p2sp']['nama']})");
                $sheet->setCellValue("F{$r}", "({$closing['bendahara']['nama']})");
            }
        }
    }

    /**
     * Mengisi baris transaksi pada sheet BPKT{m}.
     */
    protected function populateBpktSheet($sheet, array $rows, int $mIdx, array $closing, array $prevTotals = []): void
    {
        $startRow = 5;
        $rowCount = count($rows);

        // Jika bulan > 1, baris 5 mengambil saldo akhir BPKT bulan sebelumnya
        if ($mIdx > 1 && isset($prevTotals[$mIdx - 1])) {
            $prevBpktTotal = $prevTotals[$mIdx - 1]['bpkt'];
            $sheet->setCellValue('E5', "='BPKT" . ($mIdx - 1) . "'!G{$prevBpktTotal}");
        }

        if ($rowCount > 75) {
            $extra = $rowCount - 75;
            $sheet->insertNewRowBefore(80, $extra);
            for ($r = 80; $r < 80 + $extra; $r++) {
                $prevR = $r - 1;
                $sheet->setCellValue("A{$r}", "=ROW()-ROW(\$A\$4)");
                $sheet->setCellValue("G{$r}", "=IF(AND(E{$r}=0,F{$r}=0),N(G{$prevR}),IF(AND(E{$r}<>0,F{$r}<>0),0,IF(AND(E{$r}<>0,F{$r}=0),N(G{$prevR})+E{$r},N(G{$prevR})-F{$r})))");
            }
        }

        foreach ($rows as $i => $row) {
            $curRow = $startRow + $i;
            $sheet->setCellValue("A{$curRow}", "=ROW()-ROW(\$A\$4)");
            if ($row['tanggal'] instanceof Carbon) {
                $sheet->setCellValue("B{$curRow}", $row['tanggal']->format('d/m/y'));
            }
            $sheet->setCellValue("C{$curRow}", $row['uraian']);
            $sheet->setCellValue("D{$curRow}", $row['no_bukti']);

            if (!($mIdx > 1 && $i === 0)) {
                if ($row['penerimaan'] > 0) {
                    $sheet->setCellValue("E{$curRow}", $row['penerimaan']);
                }
            }
            if ($row['pengeluaran'] > 0) {
                $sheet->setCellValue("F{$curRow}", $row['pengeluaran']);
            }
        }

        // Tanda tangan
        $highestRow = $sheet->getHighestRow();
        for ($r = 80; $r <= $highestRow; $r++) {
            $valC = (string)$sheet->getCell("C{$r}")->getValue();
            $valD = (string)$sheet->getCell("D{$r}")->getValue();

            if (str_contains($valD, '2026') && str_contains($valD, '…')) {
                $sheet->setCellValue("D{$r}", "{$closing['lokasi']}, {$closing['tanggal_str']}");
            } elseif ($valC === 'Kepala ....' || str_contains($valC, 'Kepala')) {
                $sheet->setCellValue("C{$r}", $closing['kepala_sekolah']['jabatan']);
                $sheet->setCellValue("D{$r}", $closing['ketua_p2sp']['jabatan']);
                $sheet->setCellValue("F{$r}", $closing['bendahara']['jabatan']);
            } elseif (str_contains($valC, '......')) {
                $sheet->setCellValue("C{$r}", "({$closing['kepala_sekolah']['nama']})");
                $sheet->setCellValue("D{$r}", "({$closing['ketua_p2sp']['nama']})");
                $sheet->setCellValue("F{$r}", "({$closing['bendahara']['nama']})");
            }
        }
    }

    /**
     * Mengisi baris transaksi pada sheet BB{m}.
     */
    protected function populateBbSheet($sheet, array $rows, int $mIdx, array $closing, array $prevTotals = []): void
    {
        $startRow = 5;
        $rowCount = count($rows);

        // Jika bulan > 1, baris 5 mengambil saldo akhir BB bulan sebelumnya
        if ($mIdx > 1 && isset($prevTotals[$mIdx - 1])) {
            $prevBbTotal = $prevTotals[$mIdx - 1]['bb'];
            $sheet->setCellValue('E5', "='BB" . ($mIdx - 1) . "'!G{$prevBbTotal}");
        }

        if ($rowCount > 75) {
            $extra = $rowCount - 75;
            $sheet->insertNewRowBefore(80, $extra);
            for ($r = 80; $r < 80 + $extra; $r++) {
                $prevR = $r - 1;
                $sheet->setCellValue("A{$r}", "=ROW()-ROW(\$A\$4)");
                $sheet->setCellValue("G{$r}", "=IF(AND(E{$r}=0,F{$r}=0),N(G{$prevR}),IF(AND(E{$r}<>0,F{$r}<>0),0,IF(AND(E{$r}<>0,F{$r}=0),N(G{$prevR})+E{$r},N(G{$prevR})-F{$r})))");
            }
        }

        foreach ($rows as $i => $row) {
            $curRow = $startRow + $i;
            $sheet->setCellValue("A{$curRow}", "=ROW()-ROW(\$A\$4)");
            if ($row['tanggal'] instanceof Carbon) {
                $sheet->setCellValue("B{$curRow}", $row['tanggal']->format('d/m/y'));
            }
            $sheet->setCellValue("C{$curRow}", $row['uraian']);
            $sheet->setCellValue("D{$curRow}", $row['no_bukti']);

            if (!($mIdx > 1 && $i === 0)) {
                if ($row['penerimaan'] > 0) {
                    $sheet->setCellValue("E{$curRow}", $row['penerimaan']);
                }
            }
            if ($row['pengeluaran'] > 0) {
                $sheet->setCellValue("F{$curRow}", $row['pengeluaran']);
            }
        }

        // Tanda tangan
        $highestRow = $sheet->getHighestRow();
        for ($r = 80; $r <= $highestRow; $r++) {
            $valC = (string)$sheet->getCell("C{$r}")->getValue();
            $valD = (string)$sheet->getCell("D{$r}")->getValue();

            if (str_contains($valD, '2026') && str_contains($valD, '…')) {
                $sheet->setCellValue("D{$r}", "{$closing['lokasi']}, {$closing['tanggal_str']}");
            } elseif ($valC === 'Kepala ....' || str_contains($valC, 'Kepala')) {
                $sheet->setCellValue("C{$r}", $closing['kepala_sekolah']['jabatan']);
                $sheet->setCellValue("D{$r}", $closing['ketua_p2sp']['jabatan']);
                $sheet->setCellValue("F{$r}", $closing['bendahara']['jabatan']);
            } elseif (str_contains($valC, '......')) {
                $sheet->setCellValue("C{$r}", "({$closing['kepala_sekolah']['nama']})");
                $sheet->setCellValue("D{$r}", "({$closing['ketua_p2sp']['nama']})");
                $sheet->setCellValue("F{$r}", "({$closing['bendahara']['nama']})");
            }
        }
    }

    /**
     * Mengisi baris transaksi pada sheet BPP{m} Alt1.
     */
    protected function populateBppAlt1Sheet($sheet, array $rows, int $mIdx, array $closing): void
    {
        $startRow = 5;
        $rowCount = count($rows);

        if ($rowCount > 100) {
            $extra = $rowCount - 100;
            $sheet->insertNewRowBefore(105, $extra);
            for ($r = 105; $r < 105 + $extra; $r++) {
                $prevR = $r - 1;
                $sheet->setCellValue("A{$r}", "=ROW()-ROW(\$A\$4)");
                $sheet->setCellValue("J{$r}", "=IF(AND(H{$r}=0,I{$r}=0),N(J{$prevR}),IF(AND(H{$r}<>0,I{$r}<>0),0,IF(AND(H{$r}<>0,I{$r}=0),N(J{$prevR})+H{$r},N(J{$prevR})-I{$r})))");
            }
        }

        foreach ($rows as $i => $row) {
            $curRow = $startRow + $i;
            $sheet->setCellValue("A{$curRow}", "=ROW()-ROW(\$A\$4)");
            if ($row['tanggal'] instanceof Carbon) {
                $sheet->setCellValue("B{$curRow}", $row['tanggal']->format('d/m/y'));
            }
            $sheet->setCellValue("C{$curRow}", $row['uraian']);
            $sheet->setCellValue("D{$curRow}", $row['no_bukti']);
            $sheet->setCellValue("E{$curRow}", $row['jenis_bukti'] ?? '');
            $sheet->setCellValue("F{$curRow}", $row['nomor_bukti_pajak'] ?? '');
            $sheet->setCellValue("G{$curRow}", $row['jenis_pajak'] ?? '');

            if ($row['penerimaan'] > 0) {
                $sheet->setCellValue("H{$curRow}", $row['penerimaan']);
            }
            if ($row['pengeluaran'] > 0) {
                $sheet->setCellValue("I{$curRow}", $row['pengeluaran']);
            }
        }

        // Tanda tangan
        $highestRow = $sheet->getHighestRow();
        for ($r = 105; $r <= $highestRow; $r++) {
            $valB = (string)$sheet->getCell("B{$r}")->getValue();
            $valE = (string)$sheet->getCell("E{$r}")->getValue();

            if (str_contains($valE, '2026') && str_contains($valE, '…')) {
                $sheet->setCellValue("E{$r}", "{$closing['lokasi']}, {$closing['tanggal_str']}");
            } elseif ($valB === 'Kepala ....' || str_contains($valB, 'Kepala')) {
                $sheet->setCellValue("B{$r}", $closing['kepala_sekolah']['jabatan']);
                $sheet->setCellValue("E{$r}", $closing['ketua_p2sp']['jabatan']);
                $sheet->setCellValue("H{$r}", $closing['bendahara']['jabatan']);
            } elseif (str_contains($valB, '......')) {
                $sheet->setCellValue("B{$r}", "({$closing['kepala_sekolah']['nama']})");
                $sheet->setCellValue("E{$r}", "({$closing['ketua_p2sp']['nama']})");
                $sheet->setCellValue("H{$r}", "({$closing['bendahara']['nama']})");
            }
        }
    }

    /**
     * Mengisi baris transaksi pada sheet BPP{m} Alt2.
     */
    protected function populateBppAlt2Sheet($sheet, array $rows, int $mIdx, array $closing): void
    {
        $startRow = 5;
        $rowCount = count($rows);

        if ($rowCount > 100) {
            $extra = $rowCount - 100;
            $sheet->insertNewRowBefore(105, $extra);
            for ($r = 105; $r < 105 + $extra; $r++) {
                $sheet->setCellValue("A{$r}", "=ROW()-ROW(\$A\$4)");
            }
        }

        foreach ($rows as $i => $row) {
            $curRow = $startRow + $i;
            $sheet->setCellValue("A{$curRow}", "=ROW()-ROW(\$A\$4)");
            if ($row['tanggal'] instanceof Carbon) {
                $sheet->setCellValue("B{$curRow}", $row['tanggal']->format('d/m/y'));
            }
            $sheet->setCellValue("C{$curRow}", $row['uraian']);
            $sheet->setCellValue("D{$curRow}", $row['no_bukti']);
            $sheet->setCellValue("E{$curRow}", $row['jenis_bukti'] ?? '');
            $sheet->setCellValue("F{$curRow}", $row['nomor_bukti_pajak'] ?? '');

            // Kolom per jenis pajak: G=PPN, H=PPh 4(2), I=PPh 21, J=PPh 22, K=PPh 23, L=Pajak Daerah
            if (!empty($row['ppn'])) {
                $sheet->setCellValue("G{$curRow}", $row['ppn']);
            }
            if (!empty($row['pph4_2'])) {
                $sheet->setCellValue("H{$curRow}", $row['pph4_2']);
            }
            if (!empty($row['pph21'])) {
                $sheet->setCellValue("I{$curRow}", $row['pph21']);
            }
            if (!empty($row['pph22'])) {
                $sheet->setCellValue("J{$curRow}", $row['pph22']);
            }
            if (!empty($row['pph23'])) {
                $sheet->setCellValue("K{$curRow}", $row['pph23']);
            }
            if (!empty($row['pajak_daerah'])) {
                $sheet->setCellValue("L{$curRow}", $row['pajak_daerah']);
            }
        }

        // Tanda tangan
        $highestRow = $sheet->getHighestRow();
        for ($r = 105; $r <= $highestRow; $r++) {
            $valB = (string)$sheet->getCell("B{$r}")->getValue();
            $valE = (string)$sheet->getCell("E{$r}")->getValue();

            if (str_contains($valE, '2026') && str_contains($valE, '…')) {
                $sheet->setCellValue("E{$r}", "{$closing['lokasi']}, {$closing['tanggal_str']}");
            } elseif ($valB === 'Kepala ....' || str_contains($valB, 'Kepala')) {
                $sheet->setCellValue("B{$r}", $closing['kepala_sekolah']['jabatan']);
                $sheet->setCellValue("E{$r}", $closing['ketua_p2sp']['jabatan']);
            } elseif (str_contains($valB, '......')) {
                $sheet->setCellValue("B{$r}", "({$closing['kepala_sekolah']['nama']})");
                $sheet->setCellValue("E{$r}", "({$closing['ketua_p2sp']['nama']})");
            }
        }
    }

    /**
     * Membuat dan mengisi sheet 'Daftar Transaksi' sebagai tab pertama laporan.
     * Mengikuti format resmi Kemendikdasmen pada file: 261030 - (Negeri) Contoh Laporan BKU BPKT BB BPP.
     */
    protected function generateDaftarTransaksiSheet(Spreadsheet $spreadsheet, School $school, array $monthsData): void
    {
        // Buat sheet baru di index 0
        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle('Daftar Transaksi');

        // Atur lebar kolom
        $colWidths = [
            'A' => 6,
            'B' => 12,
            'C' => 48,
            'D' => 58,
            'E' => 16,
            'F' => 14,
            'G' => 14,
            'H' => 22,
            'I' => 18,
            'J' => 14,
            'K' => 20,
        ];
        foreach ($colWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Baris 1: Judul
        $sheet->setCellValue('A1', 'DAFTAR TRANSAKSI DAN PENCATATANNYA DI BUKU UTAMA');
        $sheet->getStyle('A1')->getFont()->setName('Arial')->setSize(13)->setBold(true);

        // Baris 2: Nama Sekolah & Informasi Bantuan
        $totalDana = Transaction::where('school_id', $school->id)
            ->where('type', 'penerimaan_dana')
            ->sum('amount');
        $totalDanaStr = $totalDana > 0 ? "Rp " . number_format($totalDana, 0, ',', '.') : "Revitalisasi SD 2026";
        $sheet->setCellValue('A2', "{$school->name} – Program Revitalisasi SD 2026, total dana {$totalDanaStr}. Rekapitulasi alur transaksi ke Buku Pembantu dan Buku Kas Umum.");
        $sheet->getStyle('A2')->getFont()->setName('Arial')->setSize(10);

        // Baris 3: Petunjuk Kolom
        $sheet->setCellValue('A3', 'Kolom BPKT/BB/BPP Alt1/BKU: kolom tempat nilai dicatat (Penerimaan/Pengeluaran). Kolom BPP Alt2: kolom jenis pajak, (+) = pajak dipungut/dipotong (nilai positif), (−) = pajak disetor (nilai negatif, tampil dalam tanda kurung). "–" = tidak dicatat di buku tersebut.');
        $sheet->getStyle('A3')->getFont()->setName('Arial')->setSize(9)->setItalic(true);

        // Baris 5: Table Header
        $headers = [
            'A5' => 'No.',
            'B5' => 'Tanggal',
            'C5' => 'Informasi Transaksi',
            'D5' => 'Uraian Transaksi (yang ditulis di buku utama)',
            'E5' => 'Nilai (Rp)',
            'F5' => 'BPKT',
            'G5' => 'BB',
            'H5' => 'BPP Alt1',
            'I5' => 'BPP Alt2',
            'J5' => 'BKU',
            'K5' => 'No. Bukti',
        ];
        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $headerStyle = [
            'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1F4E78'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD9D9D9'],
                ],
            ],
        ];
        $sheet->getStyle('A5:K5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(28);

        $curRow = 6;
        $globalNo = 1;

        foreach ($monthsData as $mIdx => $data) {
            $mInfo = $data['month_info'];
            $monthNameUpper = strtoupper($mInfo['month_name']);
            $year = $mInfo['year'];

            // Header Seksi per Bulan
            $sheet->setCellValue("A{$curRow}", "BULAN KE-{$mIdx}: {$monthNameUpper} {$year}  (sheet BPKT{$mIdx}, BB{$mIdx}, BPP{$mIdx} Alt1, BPP{$mIdx} Alt2, BKU{$mIdx})");
            $sheet->mergeCells("A{$curRow}:K{$curRow}");
            $sheet->getStyle("A{$curRow}:K{$curRow}")->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['argb' => 'FF1F4E78']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFDDEBF7'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'indent' => 1,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFBDD7EE'],
                    ],
                ],
            ]);
            $sheet->getRowDimension($curRow)->setRowHeight(22);
            $curRow++;

            $dtRows = $data['daftar_transaksi']['rows'] ?? [];
            foreach ($dtRows as $row) {
                if (!empty($row['is_main'])) {
                    $sheet->setCellValue("A{$curRow}", $globalNo++);
                }

                if (!empty($row['tanggal']) && $row['tanggal'] instanceof Carbon) {
                    $sheet->setCellValue("B{$curRow}", $row['tanggal']->format('d/m/Y'));
                }

                $sheet->setCellValue("C{$curRow}", $row['info'] ?? '');
                $sheet->setCellValue("D{$curRow}", $row['uraian'] ?? '');

                if (isset($row['nilai']) && $row['nilai'] !== null) {
                    $sheet->setCellValue("E{$curRow}", $row['nilai']);
                }

                $sheet->setCellValue("F{$curRow}", $row['bpkt'] ?? '–');
                $sheet->setCellValue("G{$curRow}", $row['bb'] ?? '–');
                $sheet->setCellValue("H{$curRow}", $row['bpp_alt1'] ?? '–');
                $sheet->setCellValue("I{$curRow}", $row['bpp_alt2'] ?? '–');
                $sheet->setCellValue("J{$curRow}", $row['bku'] ?? '–');
                $sheet->setCellValue("K{$curRow}", $row['no_bukti'] ?? '');

                // Format & perataan baris
                $sheet->getStyle("A{$curRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$curRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$curRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("D{$curRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("E{$curRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("E{$curRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("F{$curRow}:K{$curRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("A{$curRow}:K{$curRow}")->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 9.5],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFE0E0E0'],
                        ],
                    ],
                ]);

                $curRow++;
            }
        }
    }
}
