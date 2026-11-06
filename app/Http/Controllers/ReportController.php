<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use App\Models\RevitalizationProject;
use App\Models\School;
use App\Models\Transaction;
use App\Services\GeminiRpdService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function bku(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $schools = School::orderBy('name')->get();
        $selectedSchool = School::find($schoolId) ?? $schools->first();

        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));

        if ($month === 'all') {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
        } else {
            $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, (int)$month, 1)->endOfMonth();
        }

        $pastTransactions = Transaction::where('school_id', $selectedSchool?->id)
            ->where('transaction_date', '<', $startDate)
            ->get();

        $saldoAwalPenerimaan = 0;
        $saldoAwalPengeluaran = 0;
        foreach ($pastTransactions as $t) {
            if (in_array($t->type, ['penerimaan_dana', 'bunga_bank'])) {
                $saldoAwalPenerimaan += $t->amount;
            } elseif (in_array($t->type, ['belanja_tunai', 'belanja_transfer', 'biaya_bank'])) {
                $saldoAwalPengeluaran += $t->amount;
            }
        }
        $saldoAwal = $saldoAwalPenerimaan - $saldoAwalPengeluaran;

        $transactions = Transaction::where('school_id', $selectedSchool?->id)
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

        $runningBalance = $saldoAwal;
        $totalPenerimaanBulan = 0;
        $totalPengeluaranBulan = 0;
        $bkuRows = [];

        foreach ($transactions as $t) {
            $penerimaan = 0;
            $pengeluaran = 0;

            if (in_array($t->type, ['penerimaan_dana', 'bunga_bank'])) {
                $penerimaan = $t->amount;
                $runningBalance += $penerimaan;
                $totalPenerimaanBulan += $penerimaan;
            } elseif (in_array($t->type, ['belanja_tunai', 'belanja_transfer', 'biaya_bank'])) {
                $pengeluaran = $t->amount;
                $runningBalance -= $pengeluaran;
                $totalPengeluaranBulan += $pengeluaran;
            }

            $bkuRows[] = [
                'transaction' => $t,
                'penerimaan' => $penerimaan,
                'pengeluaran' => $pengeluaran,
                'saldo' => $runningBalance,
            ];
        }

        $allTxUpToNow = Transaction::where('school_id', $selectedSchool?->id)
            ->where('transaction_date', '<=', $endDate)
            ->get();

        $kasMasuk = $allTxUpToNow->where('type', 'tarik_tunai')->sum('amount');
        $kasKeluar = $allTxUpToNow->where('type', 'belanja_tunai')->sum('amount') + $allTxUpToNow->where('type', 'setor_tunai')->sum('amount');
        $rincianKasTunai = max(0, $kasMasuk - $kasKeluar);

        $bankMasuk = $allTxUpToNow->whereIn('type', ['penerimaan_dana', 'bunga_bank', 'setor_tunai'])->sum('amount');
        $bankKeluar = $allTxUpToNow->whereIn('type', ['belanja_transfer', 'tarik_tunai', 'biaya_bank'])->sum('amount');
        $rincianBank = max(0, $bankMasuk - $bankKeluar);

        $namaBulan = ($month === 'all') ? 'Semua Bulan' : self::getIndonesianMonthName($month);

        return view('reports.bku', compact(
            'schools',
            'selectedSchool',
            'month',
            'year',
            'namaBulan',
            'saldoAwal',
            'bkuRows',
            'totalPenerimaanBulan',
            'totalPengeluaranBulan',
            'runningBalance',
            'rincianKasTunai',
            'rincianBank'
        ));
    }

    public function bpk(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $schools = School::orderBy('name')->get();
        $selectedSchool = School::find($schoolId) ?? $schools->first();

        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));

        if ($month === 'all') {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
        } else {
            $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, (int)$month, 1)->endOfMonth();
        }

        $pastTransactions = Transaction::where('school_id', $selectedSchool?->id)
            ->where('transaction_date', '<', $startDate)
            ->get();

        $saldoAwalKas = $pastTransactions->where('type', 'tarik_tunai')->sum('amount')
            - ($pastTransactions->where('type', 'belanja_tunai')->sum('amount') + $pastTransactions->where('type', 'setor_tunai')->sum('amount'));

        $transactions = Transaction::where('school_id', $selectedSchool?->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('type', ['tarik_tunai', 'belanja_tunai', 'setor_tunai'])
            ->orderBy('transaction_date')
            ->orderByRaw("CASE 
                WHEN type = 'tarik_tunai' THEN 1 
                WHEN type = 'setor_tunai' THEN 2 
                ELSE 3 
            END ASC")
            ->orderBy('id')
            ->get();

        $runningBalance = $saldoAwalKas;
        $totalPenerimaanKas = 0;
        $totalPengeluaranKas = 0;
        $bpkRows = [];

        foreach ($transactions as $t) {
            $penerimaan = 0;
            $pengeluaran = 0;

            if ($t->type === 'tarik_tunai') {
                $penerimaan = $t->amount;
                $runningBalance += $penerimaan;
                $totalPenerimaanKas += $penerimaan;
            } elseif (in_array($t->type, ['belanja_tunai', 'setor_tunai'])) {
                $pengeluaran = $t->amount;
                $runningBalance -= $pengeluaran;
                $totalPengeluaranKas += $pengeluaran;
            }

            $bpkRows[] = [
                'transaction' => $t,
                'penerimaan' => $penerimaan,
                'pengeluaran' => $pengeluaran,
                'saldo' => $runningBalance,
            ];
        }

        $namaBulan = ($month === 'all') ? 'Semua Bulan' : self::getIndonesianMonthName($month);

        return view('reports.bpk', compact(
            'schools',
            'selectedSchool',
            'month',
            'year',
            'namaBulan',
            'saldoAwalKas',
            'bpkRows',
            'totalPenerimaanKas',
            'totalPengeluaranKas',
            'runningBalance'
        ));
    }

    public function bb(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $schools = School::orderBy('name')->get();
        $selectedSchool = School::find($schoolId) ?? $schools->first();

        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));

        if ($month === 'all') {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
        } else {
            $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, (int)$month, 1)->endOfMonth();
        }

        $pastTransactions = Transaction::where('school_id', $selectedSchool?->id)
            ->where('transaction_date', '<', $startDate)
            ->get();

        $pastBankMasuk = $pastTransactions->whereIn('type', ['penerimaan_dana', 'bunga_bank', 'setor_tunai'])->sum('amount');
        $pastBankKeluar = $pastTransactions->whereIn('type', ['belanja_transfer', 'tarik_tunai', 'biaya_bank'])->sum('amount');
        $saldoAwalBank = $pastBankMasuk - $pastBankKeluar;

        $transactions = Transaction::where('school_id', $selectedSchool?->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('type', ['penerimaan_dana', 'bunga_bank', 'setor_tunai', 'belanja_transfer', 'tarik_tunai', 'biaya_bank'])
            ->orderBy('transaction_date')
            ->orderByRaw("CASE 
                WHEN type = 'penerimaan_dana' THEN 1 
                WHEN type = 'bunga_bank' THEN 2 
                WHEN type = 'tarik_tunai' THEN 3 
                WHEN type = 'setor_tunai' THEN 4 
                ELSE 5 
            END ASC")
            ->orderBy('id')
            ->get();

        $runningBalance = $saldoAwalBank;
        $totalSetoranBank = 0;
        $totalPenarikanBank = 0;
        $bbRows = [];

        foreach ($transactions as $t) {
            $setoran = 0;
            $penarikan = 0;

            if (in_array($t->type, ['penerimaan_dana', 'bunga_bank', 'setor_tunai'])) {
                $setoran = $t->amount;
                $runningBalance += $setoran;
                $totalSetoranBank += $setoran;
            } elseif (in_array($t->type, ['belanja_transfer', 'tarik_tunai', 'biaya_bank'])) {
                $penarikan = $t->amount;
                $runningBalance -= $penarikan;
                $totalPenarikanBank += $penarikan;
            }

            $bbRows[] = [
                'transaction' => $t,
                'setoran' => $setoran,
                'penarikan' => $penarikan,
                'saldo' => $runningBalance,
            ];
        }

        $namaBulan = ($month === 'all') ? 'Semua Bulan' : self::getIndonesianMonthName($month);

        return view('reports.bb', compact(
            'schools',
            'selectedSchool',
            'month',
            'year',
            'namaBulan',
            'saldoAwalBank',
            'bbRows',
            'totalSetoranBank',
            'totalPenarikanBank',
            'runningBalance'
        ));
    }

    public function bp(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $schools = School::orderBy('name')->get();
        $selectedSchool = School::find($schoolId) ?? $schools->first();

        $taxType = $request->get('tax_type', 'all');
        $status = $request->get('status', 'all');
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', 'all');

        $query = Transaction::where('school_id', $selectedSchool?->id)
            ->where('has_tax', true)
            ->whereYear('transaction_date', $year);

        if ($month !== 'all' && is_numeric($month)) {
            $query->whereMonth('transaction_date', (int)$month);
        }

        if ($taxType !== 'all') {
            $query->where('tax_type', 'like', "%{$taxType}%");
        }

        if ($status !== 'all') {
            $query->where('tax_status', $status);
        }

        $transactions = $query->orderBy('transaction_date')->orderBy('id')->get();

        $totalPungut = $transactions->sum('tax_total');
        $totalSetor = $transactions->where('tax_status', 'disetor')->sum('tax_total');
        $saldoPajak = $totalPungut - $totalSetor;

        $totalPPN = $transactions->sum('tax_ppn');
        $totalPPh21 = $transactions->sum('tax_pph21');
        $totalPPh22 = $transactions->sum('tax_pph22');
        $totalPPh23 = $transactions->sum('tax_pph23');
        $totalPPh4_2 = $transactions->sum('tax_pph4_2');

        $namaBulan = ($month !== 'all' && is_numeric($month)) ? self::getIndonesianMonthName($month) : 'Semua Bulan';

        return view('reports.bp', compact(
            'schools',
            'selectedSchool',
            'taxType',
            'status',
            'year',
            'month',
            'namaBulan',
            'transactions',
            'totalPungut',
            'totalSetor',
            'saldoPajak',
            'totalPPN',
            'totalPPh21',
            'totalPPh22',
            'totalPPh23',
            'totalPPh4_2'
        ));
    }

    /**
     * Setor otomatis massal seluruh transaksi pajak dengan nomor NTPN dan tanggal sinkron AI.
     */
    public function autoSetorPajak(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $schools = School::orderBy('name')->get();
        $selectedSchool = School::find($schoolId) ?? $schools->first();

        $year = (int)$request->get('year', date('Y'));
        $month = $request->get('month', 'all');

        $query = Transaction::where('school_id', $selectedSchool?->id)
            ->where('has_tax', true)
            ->where('tax_total', '>', 0)
            ->whereYear('transaction_date', $year);

        if ($month !== 'all' && is_numeric($month)) {
            $query->whereMonth('transaction_date', (int)$month);
        }

        $transactions = $query->get();
        $gemini = app(GeminiRpdService::class);
        $updatedCount = 0;

        foreach ($transactions as $tx) {
            $ntpn = $tx->tax_ntpn ?: $gemini->generateNtpn();
            $paymentDate = $gemini->calculateTaxPaymentDate($tx->transaction_date);

            $tx->update([
                'tax_status' => 'disetor',
                'tax_ntpn' => $ntpn,
                'tax_payment_date' => $paymentDate,
            ]);
            $updatedCount++;
        }

        return back()->with('success', "Alhamdulillah! Sebanyak {$updatedCount} transaksi pajak pada Buku Pembantu Pajak berhasil disetorkan lunas ke Kas Negara dengan No. NTPN dan tanggal realistis sinkron AI.");
    }

    /**
     * Audit Kepatuhan Keuangan dan Deteksi Anomali SPJ Sekolah menggunakan AI & Smart Engine.
     */
    public function aiAudit(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $schools = School::orderBy('name')->get();
        $selectedSchool = School::find($schoolId) ?? $schools->first();

        $year = (int)$request->get('year', date('Y'));
        $month = $request->get('month', 'all');

        $gemini = app(GeminiRpdService::class);
        $auditResult = $gemini->auditFinancialCompliance($selectedSchool, $year, $month);

        if ($request->wantsJson()) {
            return response()->json($auditResult);
        }

        return view('reports.ai_audit', compact('schools', 'selectedSchool', 'year', 'month', 'auditResult'));
    }

    public function realisasi(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $schools = School::orderBy('name')->get();
        $selectedSchool = School::find($schoolId) ?? $schools->first();

        $projects = RevitalizationProject::where('school_id', $selectedSchool?->id)->get();
        $projectId = $request->get('project_id', $projects->first()?->id);
        $selectedProject = RevitalizationProject::with('budgetItems')->find($projectId) ?? $projects->first();
        $selectedMenu = $request->get('menu_pekerjaan', 'all');

        $budgetItems = collect();
        $availableMenus = [];
        $totalPagu = 0;
        $totalRealisasi = 0;

        if ($selectedProject) {
            $availableMenus = BudgetItem::where('project_id', $selectedProject->id)
                ->whereNotNull('menu_pekerjaan')
                ->where('menu_pekerjaan', '!=', '')
                ->distinct()
                ->pluck('menu_pekerjaan')
                ->toArray();

            $query = BudgetItem::where('project_id', $selectedProject->id)
                ->with(['transactions']);

            if ($selectedMenu && $selectedMenu !== 'all') {
                $query->where('menu_pekerjaan', $selectedMenu);
            }

            $budgetItems = $query->get()
                ->map(function ($item) {
                    $realisasi = $item->transactions
                        ->whereIn('type', ['belanja_tunai', 'belanja_transfer'])
                        ->sum('amount');
                    $sisa = $item->total_price - $realisasi;
                    $persen = $item->total_price > 0 ? round(($realisasi / $item->total_price) * 100, 2) : 0;

                    return [
                        'code' => $item->code,
                        'category' => $item->category,
                        'menu_pekerjaan' => $item->menu_pekerjaan,
                        'name' => $item->name,
                        'volume' => $item->volume,
                        'unit' => $item->unit,
                        'unit_price' => $item->unit_price,
                        'pagu' => $item->total_price,
                        'realisasi' => $realisasi,
                        'sisa' => $sisa,
                        'persen' => $persen,
                    ];
                });

            if ($selectedMenu && $selectedMenu !== 'all') {
                $totalPagu = $budgetItems->sum('pagu');
                $totalRealisasi = $budgetItems->sum('realisasi');
            } else {
                $totalPagu = $selectedProject->contract_amount;
                $totalRealisasi = $selectedProject->total_realization;
            }
        }

        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));
        $namaBulan = self::getIndonesianMonthName($month);

        $sisaTotal = $totalPagu - $totalRealisasi;
        $persenTotal = $totalPagu > 0 ? round(($totalRealisasi / $totalPagu) * 100, 2) : 0;

        return view('reports.realisasi', compact(
            'schools',
            'selectedSchool',
            'projects',
            'selectedProject',
            'availableMenus',
            'selectedMenu',
            'budgetItems',
            'totalPagu',
            'totalRealisasi',
            'sisaTotal',
            'persenTotal',
            'month',
            'year',
            'namaBulan'
        ));
    }

    public function kuitansi(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $schools = School::orderBy('name')->get();
        $selectedSchool = School::find($schoolId) ?? $schools->first();

        $query = Transaction::where('school_id', $selectedSchool?->id)
            ->whereIn('type', ['belanja_tunai', 'belanja_transfer'])
            ->with(['school', 'project', 'budgetItem']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%");
            });
        }

        $transactions = $query->latest('transaction_date')->paginate(15);

        return view('reports.kuitansi_index', compact('schools', 'selectedSchool', 'transactions'));
    }

    public function printKuitansi($id)
    {
        $transaction = Transaction::with(['school', 'project', 'budgetItem', 'creator'])->findOrFail($id);
        return view('reports.print_kuitansi', compact('transaction'));
    }

    public function exportCsv(Request $request, string $type)
    {
        $format = strtolower($request->get('format', 'xlsx'));
        if ($format === 'csv') {
            return $this->exportCsvFile($request, $type);
        }

        return $this->exportExcelFile($request, $type);
    }

    public function exportExcelFile(Request $request, string $type)
    {
        $schoolId = $this->resolveSchoolId($request);
        $school = School::find($schoolId) ?? School::first();
        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));
        $namaBulan = ($month === 'all') ? 'Semua_Bulan' : self::getIndonesianMonthName($month);
        $schoolName = $school ? $school->name : 'Satuan Pendidikan';
        $schoolSlug = str_replace([' ', '/', '\\'], '_', $schoolName);
        $fileName = "{$namaBulan}_{$year}_SiRevita_{$type}_{$schoolSlug}_" . date('Ymd_His') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($type === 'bku') {
            $sheet->setTitle('BKU ' . substr($namaBulan, 0, 15));

            $sheet->setCellValue('A1', 'PEMERINTAH KABUPATEN SITUBONDO');
            $sheet->setCellValue('A2', 'DINAS PENDIDIKAN DAN KEBUDAYAAN');
            $sheet->setCellValue('A3', "BUKU KAS UMUM (BKU) - {$schoolName}");
            $sheet->setCellValue('A4', "Rekening Operasional: {$school->bank_name} | No. Rek: {$school->bank_account_number} a.n {$school->bank_account_holder}");
            $sheet->setCellValue('A5', "Periode: " . ($month === 'all' ? "Tahun Anggaran {$year}" : "Bulan {$namaBulan} {$year}"));

            $sheet->mergeCells('A1:G1');
            $sheet->mergeCells('A2:G2');
            $sheet->mergeCells('A3:G3');
            $sheet->mergeCells('A4:G4');
            $sheet->mergeCells('A5:G5');

            $sheet->getStyle('A1:A5')->getFont()->setBold(true);
            $sheet->getStyle('A3')->getFont()->setSize(12);

            $headers = [
                'A7' => 'No',
                'B7' => 'Tanggal',
                'C7' => 'No. Bukti',
                'D7' => 'Uraian Transaksi',
                'E7' => 'Penerimaan (Rp)',
                'F7' => 'Pengeluaran (Rp)',
                'G7' => 'Saldo Kumulatif (Rp)',
            ];
            foreach ($headers as $cell => $val) {
                $sheet->setCellValue($cell, $val);
            }
            $sheet->getStyle('A7:G7')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $sheet->getStyle('A7:G7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF047857');
            $sheet->getStyle('A7:G7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(7)->setRowHeight(26);

            if ($month === 'all') {
                $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
            } else {
                $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
                $endDate = Carbon::createFromDate($year, (int)$month, 1)->endOfMonth();
            }

            $pastTransactions = Transaction::where('school_id', $school->id)
                ->where('transaction_date', '<', $startDate)
                ->get();
            $saldoAwalPenerimaan = $pastTransactions->whereIn('type', ['penerimaan_dana', 'bunga_bank'])->sum('amount');
            $saldoAwalPengeluaran = $pastTransactions->whereIn('type', ['belanja_tunai', 'belanja_transfer', 'biaya_bank'])->sum('amount');
            $saldoAwal = $saldoAwalPenerimaan - $saldoAwalPengeluaran;

            $sheet->setCellValue('A8', '-');
            $sheet->setCellValue('B8', $month === 'all' ? "01/01/{$year}" : '01/' . sprintf('%02d', (int)$month) . "/{$year}");
            $sheet->setCellValue('C8', '-');
            $sheet->setCellValue('D8', $month === 'all' ? "Saldo Awal Tahun Anggaran {$year}" : 'Saldo Awal Bulan');
            $sheet->setCellValue('E8', 0);
            $sheet->setCellValue('F8', 0);
            $sheet->setCellValue('G8', $saldoAwal);

            $sheet->getStyle('A8:C8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E8:G8')->getNumberFormat()->setFormatCode('#,##0');

            $txs = Transaction::where('school_id', $school->id)
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

            $bal = $saldoAwal;
            $rowIdx = 9;
            $no = 1;
            foreach ($txs as $t) {
                $p = in_array($t->type, ['penerimaan_dana', 'bunga_bank']) ? $t->amount : 0;
                $k = in_array($t->type, ['belanja_tunai', 'belanja_transfer', 'biaya_bank']) ? $t->amount : 0;
                $bal += ($p - $k);

                $sheet->setCellValue('A' . $rowIdx, $no++);
                $sheet->setCellValue('B' . $rowIdx, $t->transaction_date->format('d/m/Y'));
                $sheet->setCellValue('C' . $rowIdx, $t->transaction_number);
                $sheet->setCellValue('D' . $rowIdx, $t->description);
                $sheet->setCellValue('E' . $rowIdx, $p);
                $sheet->setCellValue('F' . $rowIdx, $k);
                $sheet->setCellValue('G' . $rowIdx, $bal);

                $sheet->getStyle('A' . $rowIdx . ':C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E' . $rowIdx . ':G' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');

                $rowIdx++;
            }

            $sheet->setCellValue('A' . $rowIdx, 'TOTAL TRANSAKSI');
            $sheet->mergeCells("A{$rowIdx}:D{$rowIdx}");
            $sheet->setCellValue('E' . $rowIdx, "=SUM(E8:E" . ($rowIdx - 1) . ")");
            $sheet->setCellValue('F' . $rowIdx, "=SUM(F8:F" . ($rowIdx - 1) . ")");
            $sheet->setCellValue('G' . $rowIdx, $bal);

            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->getFont()->setBold(true);
            $sheet->getStyle("A{$rowIdx}:D{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$rowIdx}:G{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
            $sheet->getStyle("A7:G{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');

            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        } elseif ($type === 'bpk') {
            $sheet->setTitle('BPK ' . substr($namaBulan, 0, 15));

            $sheet->setCellValue('A1', 'PEMERINTAH KABUPATEN SITUBONDO');
            $sheet->setCellValue('A2', 'DINAS PENDIDIKAN DAN KEBUDAYAAN');
            $sheet->setCellValue('A3', "BUKU PEMBANTU KAS (BPK) - {$schoolName}");
            $sheet->setCellValue('A4', "Sumber Penarikan Kas: {$school->bank_name} | No. Rek: {$school->bank_account_number} a.n {$school->bank_account_holder}");
            $sheet->setCellValue('A5', "Periode: " . ($month === 'all' ? "Tahun Anggaran {$year}" : "Bulan {$namaBulan} {$year}"));

            $sheet->mergeCells('A1:G1');
            $sheet->mergeCells('A2:G2');
            $sheet->mergeCells('A3:G3');
            $sheet->mergeCells('A4:G4');
            $sheet->mergeCells('A5:G5');

            $sheet->getStyle('A1:A5')->getFont()->setBold(true);
            $sheet->getStyle('A3')->getFont()->setSize(12);

            $headers = [
                'A7' => 'No',
                'B7' => 'Tanggal',
                'C7' => 'No. Bukti',
                'D7' => 'Uraian',
                'E7' => 'Penerimaan Kas (Rp)',
                'F7' => 'Pengeluaran Kas (Rp)',
                'G7' => 'Saldo Kas (Rp)',
            ];
            foreach ($headers as $cell => $val) {
                $sheet->setCellValue($cell, $val);
            }
            $sheet->getStyle('A7:G7')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $sheet->getStyle('A7:G7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF047857');
            $sheet->getStyle('A7:G7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(7)->setRowHeight(26);

            if ($month === 'all') {
                $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
            } else {
                $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
                $endDate = Carbon::createFromDate($year, (int)$month, 1)->endOfMonth();
            }

            $pastTransactions = Transaction::where('school_id', $school->id)
                ->where('transaction_date', '<', $startDate)
                ->get();
            $saldoAwalKas = $pastTransactions->where('type', 'tarik_tunai')->sum('amount')
                - ($pastTransactions->where('type', 'belanja_tunai')->sum('amount') + $pastTransactions->where('type', 'setor_tunai')->sum('amount'));

            $sheet->setCellValue('A8', '-');
            $sheet->setCellValue('B8', $month === 'all' ? "01/01/{$year}" : '01/' . sprintf('%02d', (int)$month) . "/{$year}");
            $sheet->setCellValue('C8', '-');
            $sheet->setCellValue('D8', $month === 'all' ? "Saldo Awal Kas Tunai (Awal Tahun {$year})" : 'Saldo Awal Kas Tunai');
            $sheet->setCellValue('E8', 0);
            $sheet->setCellValue('F8', 0);
            $sheet->setCellValue('G8', $saldoAwalKas);

            $sheet->getStyle('A8:C8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E8:G8')->getNumberFormat()->setFormatCode('#,##0');

            $txs = Transaction::where('school_id', $school->id)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->whereIn('type', ['tarik_tunai', 'belanja_tunai', 'setor_tunai'])
                ->orderBy('transaction_date')
                ->orderByRaw("CASE 
                    WHEN type = 'tarik_tunai' THEN 1 
                    WHEN type = 'setor_tunai' THEN 2 
                    ELSE 3 
                END ASC")
                ->orderBy('id')
                ->get();

            $bal = $saldoAwalKas;
            $rowIdx = 9;
            $no = 1;
            foreach ($txs as $t) {
                $p = ($t->type === 'tarik_tunai') ? $t->amount : 0;
                $k = in_array($t->type, ['belanja_tunai', 'setor_tunai']) ? $t->amount : 0;
                $bal += ($p - $k);

                $sheet->setCellValue('A' . $rowIdx, $no++);
                $sheet->setCellValue('B' . $rowIdx, $t->transaction_date->format('d/m/Y'));
                $sheet->setCellValue('C' . $rowIdx, $t->transaction_number);
                $sheet->setCellValue('D' . $rowIdx, $t->description);
                $sheet->setCellValue('E' . $rowIdx, $p);
                $sheet->setCellValue('F' . $rowIdx, $k);
                $sheet->setCellValue('G' . $rowIdx, $bal);

                $sheet->getStyle('A' . $rowIdx . ':C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E' . $rowIdx . ':G' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');

                $rowIdx++;
            }

            $sheet->setCellValue('A' . $rowIdx, 'TOTAL KAS TUNAI');
            $sheet->mergeCells("A{$rowIdx}:D{$rowIdx}");
            $sheet->setCellValue('E' . $rowIdx, "=SUM(E8:E" . ($rowIdx - 1) . ")");
            $sheet->setCellValue('F' . $rowIdx, "=SUM(F8:F" . ($rowIdx - 1) . ")");
            $sheet->setCellValue('G' . $rowIdx, $bal);

            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->getFont()->setBold(true);
            $sheet->getStyle("A{$rowIdx}:D{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$rowIdx}:G{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
            $sheet->getStyle("A7:G{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');

            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        } elseif ($type === 'bb') {
            $sheet->setTitle('BB ' . substr($namaBulan, 0, 15));

            $sheet->setCellValue('A1', 'PEMERINTAH KABUPATEN SITUBONDO');
            $sheet->setCellValue('A2', 'DINAS PENDIDIKAN DAN KEBUDAYAAN');
            $sheet->setCellValue('A3', "BUKU PEMBANTU BANK (BB) - {$schoolName}");
            $sheet->setCellValue('A4', "Bank: {$school->bank_name} | No. Rekening: {$school->bank_account_number} a.n {$school->bank_account_holder}");
            $sheet->setCellValue('A5', "Periode: " . ($month === 'all' ? "Tahun Anggaran {$year}" : "Bulan {$namaBulan} {$year}"));

            $sheet->mergeCells('A1:G1');
            $sheet->mergeCells('A2:G2');
            $sheet->mergeCells('A3:G3');
            $sheet->mergeCells('A4:G4');
            $sheet->mergeCells('A5:G5');

            $sheet->getStyle('A1:A5')->getFont()->setBold(true);
            $sheet->getStyle('A3')->getFont()->setSize(12);

            $headers = [
                'A7' => 'No',
                'B7' => 'Tanggal',
                'C7' => 'No. Bukti',
                'D7' => 'Uraian',
                'E7' => 'Setoran Bank (Rp)',
                'F7' => 'Penarikan Bank (Rp)',
                'G7' => 'Saldo Bank (Rp)',
            ];
            foreach ($headers as $cell => $val) {
                $sheet->setCellValue($cell, $val);
            }
            $sheet->getStyle('A7:G7')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $sheet->getStyle('A7:G7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF047857');
            $sheet->getStyle('A7:G7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(7)->setRowHeight(26);

            if ($month === 'all') {
                $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
            } else {
                $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
                $endDate = Carbon::createFromDate($year, (int)$month, 1)->endOfMonth();
            }

            $pastTransactions = Transaction::where('school_id', $school->id)
                ->where('transaction_date', '<', $startDate)
                ->get();
            $pastBankMasuk = $pastTransactions->whereIn('type', ['penerimaan_dana', 'bunga_bank', 'setor_tunai'])->sum('amount');
            $pastBankKeluar = $pastTransactions->whereIn('type', ['belanja_transfer', 'tarik_tunai', 'biaya_bank'])->sum('amount');
            $saldoAwalBank = $pastBankMasuk - $pastBankKeluar;

            $sheet->setCellValue('A8', '-');
            $sheet->setCellValue('B8', $month === 'all' ? "01/01/{$year}" : '01/' . sprintf('%02d', (int)$month) . "/{$year}");
            $sheet->setCellValue('C8', '-');
            $sheet->setCellValue('D8', $month === 'all' ? "Saldo Awal Rekening Bank (Awal Tahun {$year})" : 'Saldo Awal Rekening Bank');
            $sheet->setCellValue('E8', 0);
            $sheet->setCellValue('F8', 0);
            $sheet->setCellValue('G8', $saldoAwalBank);

            $sheet->getStyle('A8:C8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E8:G8')->getNumberFormat()->setFormatCode('#,##0');

            $txs = Transaction::where('school_id', $school->id)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->whereIn('type', ['penerimaan_dana', 'bunga_bank', 'setor_tunai', 'belanja_transfer', 'tarik_tunai', 'biaya_bank'])
                ->orderBy('transaction_date')
                ->orderByRaw("CASE 
                    WHEN type = 'penerimaan_dana' THEN 1 
                    WHEN type = 'bunga_bank' THEN 2 
                    WHEN type = 'tarik_tunai' THEN 3 
                    WHEN type = 'setor_tunai' THEN 4 
                    ELSE 5 
                END ASC")
                ->orderBy('id')
                ->get();

            $bal = $saldoAwalBank;
            $rowIdx = 9;
            $no = 1;
            foreach ($txs as $t) {
                $s = in_array($t->type, ['penerimaan_dana', 'bunga_bank', 'setor_tunai']) ? $t->amount : 0;
                $tKeluar = in_array($t->type, ['belanja_transfer', 'tarik_tunai', 'biaya_bank']) ? $t->amount : 0;
                $bal += ($s - $tKeluar);

                $sheet->setCellValue('A' . $rowIdx, $no++);
                $sheet->setCellValue('B' . $rowIdx, $t->transaction_date->format('d/m/Y'));
                $sheet->setCellValue('C' . $rowIdx, $t->transaction_number);
                $sheet->setCellValue('D' . $rowIdx, $t->description);
                $sheet->setCellValue('E' . $rowIdx, $s);
                $sheet->setCellValue('F' . $rowIdx, $tKeluar);
                $sheet->setCellValue('G' . $rowIdx, $bal);

                $sheet->getStyle('A' . $rowIdx . ':C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E' . $rowIdx . ':G' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');

                $rowIdx++;
            }

            $sheet->setCellValue('A' . $rowIdx, 'TOTAL BANK');
            $sheet->mergeCells("A{$rowIdx}:D{$rowIdx}");
            $sheet->setCellValue('E' . $rowIdx, "=SUM(E8:E" . ($rowIdx - 1) . ")");
            $sheet->setCellValue('F' . $rowIdx, "=SUM(F8:F" . ($rowIdx - 1) . ")");
            $sheet->setCellValue('G' . $rowIdx, $bal);

            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->getFont()->setBold(true);
            $sheet->getStyle("A{$rowIdx}:D{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$rowIdx}:G{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
            $sheet->getStyle("A7:G{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');

            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        } elseif ($type === 'bp') {
            $sheet->setTitle('BP Pajak ' . $year);

            $sheet->setCellValue('A1', 'PEMERINTAH KABUPATEN SITUBONDO');
            $sheet->setCellValue('A2', 'DINAS PENDIDIKAN DAN KEBUDAYAAN');
            $sheet->setCellValue('A3', "BUKU PEMBANTU PAJAK (BP) - {$schoolName}");
            $sheet->setCellValue('A4', "Rekening Operasional: {$school->bank_name} | No. Rek: {$school->bank_account_number} a.n {$school->bank_account_holder}");
            $sheet->setCellValue('A5', "Tahun Anggaran: {$year}");

            $sheet->mergeCells('A1:I1');
            $sheet->mergeCells('A2:I2');
            $sheet->mergeCells('A3:I3');
            $sheet->mergeCells('A4:I4');
            $sheet->mergeCells('A5:I5');

            $sheet->getStyle('A1:A5')->getFont()->setBold(true);
            $sheet->getStyle('A3')->getFont()->setSize(12);

            $headers = [
                'A7' => 'No',
                'B7' => 'Tanggal',
                'C7' => 'No. Bukti',
                'D7' => 'Uraian',
                'E7' => 'Jenis Pajak',
                'F7' => 'Pungutan (Rp)',
                'G7' => 'Setoran (Rp)',
                'H7' => 'Status',
                'I7' => 'No NTPN',
            ];
            foreach ($headers as $cell => $val) {
                $sheet->setCellValue($cell, $val);
            }
            $sheet->getStyle('A7:I7')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $sheet->getStyle('A7:I7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF047857');
            $sheet->getStyle('A7:I7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(7)->setRowHeight(26);

            $txs = Transaction::where('school_id', $school->id)->where('has_tax', true)->get();

            $rowIdx = 8;
            $no = 1;
            foreach ($txs as $t) {
                $setor = ($t->tax_status === 'disetor') ? $t->tax_total : 0;

                $sheet->setCellValue('A' . $rowIdx, $no++);
                $sheet->setCellValue('B' . $rowIdx, $t->transaction_date->format('d/m/Y'));
                $sheet->setCellValue('C' . $rowIdx, $t->transaction_number);
                $sheet->setCellValue('D' . $rowIdx, $t->description);
                $sheet->setCellValue('E' . $rowIdx, $t->tax_type);
                $sheet->setCellValue('F' . $rowIdx, $t->tax_total);
                $sheet->setCellValue('G' . $rowIdx, $setor);
                $sheet->setCellValue('H' . $rowIdx, ucfirst($t->tax_status));
                $sheet->setCellValue('I' . $rowIdx, $t->tax_ntpn ?? '-');

                $sheet->getStyle('A' . $rowIdx . ':C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('F' . $rowIdx . ':G' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('H' . $rowIdx . ':I' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $rowIdx++;
            }

            $sheet->setCellValue('A' . $rowIdx, 'TOTAL PAJAK');
            $sheet->mergeCells("A{$rowIdx}:E{$rowIdx}");
            if ($rowIdx > 8) {
                $sheet->setCellValue('F' . $rowIdx, "=SUM(F8:F" . ($rowIdx - 1) . ")");
                $sheet->setCellValue('G' . $rowIdx, "=SUM(G8:G" . ($rowIdx - 1) . ")");
            } else {
                $sheet->setCellValue('F' . $rowIdx, 0);
                $sheet->setCellValue('G' . $rowIdx, 0);
            }
            $sheet->setCellValue('H' . $rowIdx, '-');
            $sheet->setCellValue('I' . $rowIdx, '-');

            $sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getFont()->setBold(true);
            $sheet->getStyle("A{$rowIdx}:E{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("F{$rowIdx}:G{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("H{$rowIdx}:I{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
            $sheet->getStyle("A7:I{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');

            foreach (range('A', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportCsvFile(Request $request, string $type)
    {
        $schoolId = $this->resolveSchoolId($request);
        $school = School::find($schoolId) ?? School::first();
        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));
        $namaBulan = ($month === 'all') ? 'Semua_Bulan' : self::getIndonesianMonthName($month);
        $schoolSlug = str_replace([' ', '/', '\\'], '_', $school->name);
        $fileName = "{$namaBulan}_{$year}_SiRevita_{$type}_{$schoolSlug}_" . date('Ymd_His') . '.csv';

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$fileName}",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($type, $school, $request) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            if ($type === 'bku') {
                fputcsv($file, ['PEMERINTAH KABUPATEN SITUBONDO']);
                fputcsv($file, ['DINAS PENDIDIKAN DAN KEBUDAYAAN']);
                fputcsv($file, ["BUKU KAS UMUM (BKU) - {$school->name}"]);
                fputcsv($file, ["Rekening Operasional: {$school->bank_name} | No. Rek: {$school->bank_account_number} a.n {$school->bank_account_holder}"]);
                fputcsv($file, []);
                fputcsv($file, ['No', 'Tanggal', 'No. Bukti', 'Uraian Transaksi', 'Penerimaan (Rp)', 'Pengeluaran (Rp)', 'Saldo Kumulatif (Rp)']);

                $month = $request->get('month', date('n'));
                $year = $request->get('year', date('Y'));
                if ($month === 'all') {
                    $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                    $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
                } else {
                    $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
                    $endDate = Carbon::createFromDate($year, (int)$month, 1)->endOfMonth();
                }

                $pastTransactions = Transaction::where('school_id', $school->id)
                    ->where('transaction_date', '<', $startDate)
                    ->get();
                $saldoAwalPenerimaan = $pastTransactions->whereIn('type', ['penerimaan_dana', 'bunga_bank'])->sum('amount');
                $saldoAwalPengeluaran = $pastTransactions->whereIn('type', ['belanja_tunai', 'belanja_transfer', 'biaya_bank'])->sum('amount');
                $saldoAwal = $saldoAwalPenerimaan - $saldoAwalPengeluaran;

                $txs = Transaction::where('school_id', $school->id)
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

                $bal = $saldoAwal;
                fputcsv($file, [
                    '-',
                    $month === 'all' ? "01/01/{$year}" : '01/' . sprintf('%02d', (int)$month) . "/{$year}",
                    '-',
                    $month === 'all' ? "Saldo Awal Tahun Anggaran {$year}" : 'Saldo Awal Bulan',
                    0,
                    0,
                    $bal
                ]);

                $no = 1;
                foreach ($txs as $t) {
                    $p = in_array($t->type, ['penerimaan_dana', 'bunga_bank']) ? $t->amount : 0;
                    $k = in_array($t->type, ['belanja_tunai', 'belanja_transfer', 'biaya_bank']) ? $t->amount : 0;
                    $bal += ($p - $k);

                    fputcsv($file, [
                        $no++,
                        $t->transaction_date->format('d/m/Y'),
                        $t->transaction_number,
                        $t->description,
                        $p,
                        $k,
                        $bal
                    ]);
                }
            } elseif ($type === 'bpk') {
                fputcsv($file, ['PEMERINTAH KABUPATEN SITUBONDO']);
                fputcsv($file, ['DINAS PENDIDIKAN DAN KEBUDAYAAN']);
                fputcsv($file, ["BUKU PEMBANTU KAS (BPK) - {$school->name}"]);
                fputcsv($file, ["Sumber Penarikan Kas: {$school->bank_name} | No. Rek: {$school->bank_account_number} a.n {$school->bank_account_holder}"]);
                fputcsv($file, []);
                fputcsv($file, ['No', 'Tanggal', 'No. Bukti', 'Uraian', 'Penerimaan Kas (Rp)', 'Pengeluaran Kas (Rp)', 'Saldo Kas (Rp)']);

                $month = $request->get('month', date('n'));
                $year = $request->get('year', date('Y'));
                if ($month === 'all') {
                    $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                    $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
                } else {
                    $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
                    $endDate = Carbon::createFromDate($year, (int)$month, 1)->endOfMonth();
                }

                $pastTransactions = Transaction::where('school_id', $school->id)
                    ->where('transaction_date', '<', $startDate)
                    ->get();
                $saldoAwalKas = $pastTransactions->where('type', 'tarik_tunai')->sum('amount')
                    - ($pastTransactions->where('type', 'belanja_tunai')->sum('amount') + $pastTransactions->where('type', 'setor_tunai')->sum('amount'));

                $txs = Transaction::where('school_id', $school->id)
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->whereIn('type', ['tarik_tunai', 'belanja_tunai', 'setor_tunai'])
                    ->orderBy('transaction_date')
                    ->orderByRaw("CASE 
                        WHEN type = 'tarik_tunai' THEN 1 
                        WHEN type = 'setor_tunai' THEN 2 
                        ELSE 3 
                    END ASC")
                    ->orderBy('id')
                    ->get();

                $bal = $saldoAwalKas;
                fputcsv($file, [
                    '-',
                    $month === 'all' ? "01/01/{$year}" : '01/' . sprintf('%02d', (int)$month) . "/{$year}",
                    '-',
                    $month === 'all' ? "Saldo Awal Kas Tunai (Awal Tahun {$year})" : 'Saldo Awal Kas Tunai',
                    0,
                    0,
                    $bal
                ]);

                $no = 1;
                foreach ($txs as $t) {
                    $p = ($t->type === 'tarik_tunai') ? $t->amount : 0;
                    $k = in_array($t->type, ['belanja_tunai', 'setor_tunai']) ? $t->amount : 0;
                    $bal += ($p - $k);

                    fputcsv($file, [
                        $no++,
                        $t->transaction_date->format('d/m/Y'),
                        $t->transaction_number,
                        $t->description,
                        $p,
                        $k,
                        $bal
                    ]);
                }
            } elseif ($type === 'bb') {
                fputcsv($file, ['PEMERINTAH KABUPATEN SITUBONDO']);
                fputcsv($file, ['DINAS PENDIDIKAN DAN KEBUDAYAAN']);
                fputcsv($file, ["BUKU PEMBANTU BANK (BB) - {$school->name}"]);
                fputcsv($file, ["Bank: {$school->bank_name} | No. Rekening: {$school->bank_account_number} a.n {$school->bank_account_holder}"]);
                fputcsv($file, []);
                fputcsv($file, ['No', 'Tanggal', 'No. Bukti', 'Uraian', 'Setoran Bank (Rp)', 'Penarikan Bank (Rp)', 'Saldo Bank (Rp)']);

                $month = $request->get('month', date('n'));
                $year = $request->get('year', date('Y'));
                if ($month === 'all') {
                    $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                    $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
                } else {
                    $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
                    $endDate = Carbon::createFromDate($year, (int)$month, 1)->endOfMonth();
                }

                $pastTransactions = Transaction::where('school_id', $school->id)
                    ->where('transaction_date', '<', $startDate)
                    ->get();
                $pastBankMasuk = $pastTransactions->whereIn('type', ['penerimaan_dana', 'bunga_bank', 'setor_tunai'])->sum('amount');
                $pastBankKeluar = $pastTransactions->whereIn('type', ['belanja_transfer', 'tarik_tunai', 'biaya_bank'])->sum('amount');
                $saldoAwalBank = $pastBankMasuk - $pastBankKeluar;

                $txs = Transaction::where('school_id', $school->id)
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->whereIn('type', ['penerimaan_dana', 'bunga_bank', 'setor_tunai', 'belanja_transfer', 'tarik_tunai', 'biaya_bank'])
                    ->orderBy('transaction_date')
                    ->orderByRaw("CASE 
                        WHEN type = 'penerimaan_dana' THEN 1 
                        WHEN type = 'bunga_bank' THEN 2 
                        WHEN type = 'tarik_tunai' THEN 3 
                        WHEN type = 'setor_tunai' THEN 4 
                        ELSE 5 
                    END ASC")
                    ->orderBy('id')
                    ->get();

                $bal = $saldoAwalBank;
                fputcsv($file, [
                    '-',
                    $month === 'all' ? "01/01/{$year}" : '01/' . sprintf('%02d', (int)$month) . "/{$year}",
                    '-',
                    $month === 'all' ? "Saldo Awal Rekening Bank (Awal Tahun {$year})" : 'Saldo Awal Rekening Bank',
                    0,
                    0,
                    $bal
                ]);

                $no = 1;
                foreach ($txs as $t) {
                    $s = in_array($t->type, ['penerimaan_dana', 'bunga_bank', 'setor_tunai']) ? $t->amount : 0;
                    $tKeluar = in_array($t->type, ['belanja_transfer', 'tarik_tunai', 'biaya_bank']) ? $t->amount : 0;
                    $bal += ($s - $tKeluar);

                    fputcsv($file, [
                        $no++,
                        $t->transaction_date->format('d/m/Y'),
                        $t->transaction_number,
                        $t->description,
                        $s,
                        $tKeluar,
                        $bal
                    ]);
                }
            } elseif ($type === 'bp') {
                fputcsv($file, ['PEMERINTAH KABUPATEN SITUBONDO']);
                fputcsv($file, ['DINAS PENDIDIKAN DAN KEBUDAYAAN']);
                fputcsv($file, ["BUKU PEMBANTU PAJAK (BP) - {$school->name}"]);
                fputcsv($file, ["Rekening Operasional: {$school->bank_name} | No. Rek: {$school->bank_account_number} a.n {$school->bank_account_holder}"]);
                fputcsv($file, []);
                fputcsv($file, ['No', 'Tanggal', 'No. Bukti', 'Uraian', 'Jenis Pajak', 'Pungutan (Rp)', 'Setoran (Rp)', 'Status', 'No NTPN']);

                $txs = Transaction::where('school_id', $school->id)->where('has_tax', true)->get();
                $no = 1;
                foreach ($txs as $t) {
                    $setor = ($t->tax_status === 'disetor') ? $t->tax_total : 0;
                    fputcsv($file, [
                        $no++,
                        $t->transaction_date->format('d/m/Y'),
                        $t->transaction_number,
                        $t->description,
                        $t->tax_type,
                        $t->tax_total,
                        $setor,
                        ucfirst($t->tax_status),
                        $t->tax_ntpn ?? '-'
                    ]);
                }
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    public static function getIndonesianMonthName(int|string|null $month): string
    {
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        if ($month === 'all' || empty($month)) {
            return 'Semua Bulan';
        }
        $m = (int)$month;
        return $bulanIndo[$m] ?? Carbon::create(null, $m, 1)->locale('id')->translatedFormat('F');
    }

    private function resolveSchoolId(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->school_id && !$user->isRoot()) {
            return $user->school_id;
        }

        return $request->get('school_id', School::first()?->id);
    }
}
