<?php

namespace App\Http\Controllers;

use App\Models\RevitalizationProject;
use App\Models\RpdDocument;
use App\Models\RpdItem;
use App\Models\School;
use App\Models\SystemNotification;
use App\Services\GeminiRpdService;
use App\Services\RpdBreakdownService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RpdBreakdownController extends Controller
{
    protected RpdBreakdownService $breakdownService;

    public function __construct(RpdBreakdownService $breakdownService)
    {
        $this->breakdownService = $breakdownService;
    }

    /**
     * Menampilkan daftar dokumen RPD yang diunggah dan status pecah bahan.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = RpdDocument::with(['school', 'project', 'creator'])
            ->withCount('items');

        // Filter sekolah sesuai user
        if ($user->school_id && !$user->hasRole('root')) {
            $query->where('school_id', $user->school_id);
        } elseif ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $documents = $query->latest()->paginate(10)->withQueryString();

        // Rekapitulasi Statistik
        $stats = [
            'total_docs' => RpdDocument::count(),
            'total_budget' => RpdDocument::sum('total_budget'),
            'posted_count' => RpdDocument::where('status', 'posted_to_bku')->count(),
            'ready_count' => RpdDocument::where('status', 'analyzed')->count(),
        ];

        $schools = School::orderBy('name')->get();
        $projects = RevitalizationProject::orderBy('title')->get();

        return view('rpd.index', compact('documents', 'stats', 'schools', 'projects'));
    }

    /**
     * Form unggah file RPD baru.
     */
    public function create(Request $request)
    {
        $user = auth()->user();
        $schoolsQuery = School::orderBy('name');
        if ($user->school_id && !$user->hasRole('root')) {
            $schoolsQuery->where('id', $user->school_id);
        }
        $schools = $schoolsQuery->get();

        $selectedSchoolId = $request->get('school_id', $user->school_id ?? $schools->first()?->id);
        $projects = RevitalizationProject::where('school_id', $selectedSchoolId)->get();

        return view('rpd.create', compact('schools', 'projects', 'selectedSchoolId'));
    }

    /**
     * Unduh Template Format RPD (Excel .xlsx, PDF, atau CSV).
     */
    public function template(Request $request)
    {
        $format = strtolower($request->get('format', 'xlsx'));
        $type = strtolower($request->get('type', 'official'));

        // 1. Ekspor Format PDF Resmi Dinas
        if ($format === 'pdf') {
            $pdfContent = $this->breakdownService->generateOfficialTemplatePdf();
            $fileName = 'Template_Resmi_RPD_Revitalisasi_Situbondo.pdf';
            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        }

        // 2. Ekspor Format CSV
        if ($format === 'csv') {
            $fileName = 'Template_RPD_Revitalisasi_Situbondo.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ];

            return new StreamedResponse(function () {
                $handle = fopen('php://output', 'w');
                // UTF-8 BOM
                fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

                fputcsv($handle, [
                    'No',
                    'Kode Rekening',
                    'Kategori',
                    'Uraian Barang / Kegiatan / Bahan',
                    'Spesifikasi',
                    'Volume',
                    'Satuan',
                    'Harga Satuan',
                    'Total Biaya',
                    'Tanggal Rencana Belanja',
                    'Toko / Rekanan',
                    'Metode Bayar'
                ]);

                $sampleData = [
                    [1, '5.1.02.01', 'Bahan', 'Semen Gresik 50 Kg', 'SNI PCC Type 1', 100, 'Zak', 68000, 6800000, '2026-03-05', 'Toko Bangunan Berkah Situbondo', 'Transfer Bank'],
                    [2, '5.1.02.01', 'Bahan', 'Pasir Pasang Kali Sampean', 'Kualitas Kasar Bersih', 8, 'M3', 250000, 2000000, '2026-03-06', 'Pemasok Pasir Kali Situbondo', 'Kas Tunai'],
                    [3, '5.1.02.01', 'Bahan', 'Bata Merah Bakar Panji', 'Standar 5x11x22 cm', 5000, 'Bh', 950, 4750000, '2026-03-08', 'Pengrajin Bata Panji Situbondo', 'Kas Tunai'],
                    [4, '5.1.02.01', 'Bahan', 'Besi Beton Polos Dia. 10 mm', 'SNI Panjang 12 Meter', 50, 'Batang', 85000, 4250000, '2026-03-10', 'Toko Besi Abadi Situbondo', 'Kas Tunai'],
                    [5, '5.1.02.01', 'Bahan', 'Cat Tembok Eksterior Dulux Weathershield', 'Warna Putih Salju 20L', 4, 'Pail', 1450000, 5800000, '2026-03-15', 'Toko Cat Citra Warna Situbondo', 'Transfer Bank'],
                    [6, '5.1.02.02', 'Upah', 'Upah Tukang Batu & Plesteran (Minggu I)', 'HOK Tukang', 24, 'OH', 125000, 3000000, '2026-03-12', 'Kelompok Tukang Pak Slamet', 'Kas Tunai'],
                    [7, '5.1.02.02', 'Upah', 'Upah Pekerja / Pembantu Tukang (Minggu I)', 'HOK Tenaga Lokal', 36, 'OH', 90000, 3240000, '2026-03-12', 'Kelompok Pekerja Swakelola', 'Kas Tunai'],
                    [8, '5.1.02.03', 'Alat', 'Sewa Mesin Molen Pengaduk Semen', 'Kapasitas 350L per Hari', 5, 'Hari', 180000, 900000, '2026-03-07', 'Sewa Alat Proyek Jaya', 'Kas Tunai'],
                    [9, '5.1.02.04', 'Operasional', 'Belanja ATK dan Laporan SPJ', 'Kertas A4 & Jilid', 1, 'Paket', 650000, 650000, '2026-03-20', 'Toko ATK Grafika Situbondo', 'Kas Tunai'],
                ];

                foreach ($sampleData as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }, 200, $headers);
        }

        // 3. Format Excel (.xlsx)
        if ($type === 'itemized') {
            $spreadsheet = $this->breakdownService->generateItemizedTemplateSpreadsheet();
            $fileName = 'Template_RPD_Rincian_Item_Situbondo_2026.xlsx';
        } else {
            $spreadsheet = $this->breakdownService->generateOfficialTemplateSpreadsheet();
            $fileName = 'Template_Resmi_RPD_Revitalisasi_Situbondo_2026.xlsx';
        }

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Memproses upload file RPD (Excel / PDF / CSV) dan menghasilkan output Pecah Bahan BKU.
     */
    public function store(Request $request)
    {
        $request->validate([
            'school_id' => 'required|exists:schools,id',
            'project_id' => 'required|exists:revitalization_projects,id',
            'term_stage' => 'required|string|max:100',
            'target_column' => 'nullable|string|in:auto,termin_1,termin_2,termin_3,total',
            'breakdown_mode' => 'nullable|string|in:detailed,summary',
            'rpd_file' => 'required|file|max:20480',
            'title' => 'nullable|string|max:255',
            'default_date' => 'nullable|date',
            'default_store' => 'nullable|string|max:255',
            'use_ai' => 'nullable|boolean',
            'gemini_api_key' => 'nullable|string|max:255',
        ], [
            'rpd_file.required' => 'Pilih berkas file RPD yang akan diunggah.',
            'project_id.required' => 'Pilih paket proyek revitalisasi yang dituju.',
        ]);

        $file = $request->file('rpd_file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'xls', 'csv', 'txt', 'pdf'])) {
            return back()->withInput()->with('error', 'Format berkas tidak didukung. Harap unggah berkas Excel (.xlsx, .xls), PDF (.pdf), atau CSV (.csv).');
        }

        $project = RevitalizationProject::findOrFail($request->project_id);

        $rawStore = trim((string) $request->input('default_store', ''));
        $defaultStore = $rawStore !== '' ? $rawStore : '....................';

        try {
            $useAi = $request->boolean('use_ai', false);
            $document = $this->breakdownService->processUpload($file, $project, [
                'term_stage' => $request->term_stage,
                'target_column' => $request->target_column ?: 'auto',
                'breakdown_mode' => $request->input('breakdown_mode', 'detailed'),
                'title' => $request->title ?: ('RPD ' . $request->term_stage . ' - ' . $project->title),
                'default_date' => $request->default_date ?: now()->format('Y-m-d'),
                'default_store' => $defaultStore,
                'notes' => $request->notes,
                'use_ai' => $useAi,
                'api_key' => $request->input('gemini_api_key'),
            ]);

            $aiNotice = $useAi ? ' (✨ Variasi Jadwal & Narasi Cerdas Aktif)' : '';
            $formattedBudget = number_format($document->total_budget, 0, ',', '.');

            SystemNotification::send(
                type: 'rpd',
                title: 'Pecah Bahan RPD Berhasil Diproses',
                message: "Dokumen RPD '{$document->title}' ({$document->term_stage}) berhasil dipecah menjadi {$document->total_items_count} item draf isian BKU (Total Rp {$formattedBudget}).",
                url: route('rpd.show', $document->id),
                icon: 'file-spreadsheet',
                color: 'purple',
                data: ['rpd_id' => $document->id, 'title' => $document->title, 'total_items' => $document->total_items_count, 'total_budget' => $document->total_budget]
            );

            return redirect()->route('rpd.show', $document->id)
                ->with('success', "Alhamdulillah! Berkas RPD (" . strtoupper($ext) . ") berhasil diproses{$aiNotice}. Sebanyak {$document->total_items_count} item berhasil dipecah menjadi draf isian BKU.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses berkas RPD: ' . $e->getMessage());
        }
    }

    /**
     * Pratinjau Output Pecah Bahan & Tabel Isian BKU.
     */
    public function show(RpdDocument $rpd)
    {
        $rpd->load(['school', 'project', 'items.budgetItem', 'items.transaction']);

        $items = $rpd->items;
        $unpostedItems = $items->where('is_posted', false);
        $postedItems = $items->where('is_posted', true);
        $unpostedCount = $unpostedItems->count();
        $postedCount = $postedItems->count();

        $summary = [
            'total_items' => $items->count(),
            'total_budget' => $items->sum('total_price'),
            'total_materials' => $items->where('category', 'bahan')->sum('total_price'),
            'total_wages' => $items->where('category', 'upah')->sum('total_price'),
            'total_equipment' => $items->where('category', 'alat')->sum('total_price'),
            'total_operational' => $items->where('category', 'operasional')->sum('total_price'),
            'total_tax_ppn' => $items->sum('tax_ppn'),
            'total_tax_pph22' => $items->sum('tax_pph22'),
            'total_tax_pph21' => $items->sum('tax_pph21'),
            'total_tax' => $items->sum('tax_total'),
            'total_net' => $items->sum('net_amount'),
            'cash_count' => $items->where('payment_method', 'belanja_tunai')->count(),
            'bank_count' => $items->where('payment_method', 'belanja_transfer')->count(),
            'cash_amount' => $items->where('payment_method', 'belanja_tunai')->sum('total_price'),
            'bank_amount' => $items->where('payment_method', 'belanja_transfer')->sum('total_price'),
            'unposted_count' => $unpostedCount,
            'posted_count' => $postedCount,
            'unposted_budget' => $unpostedItems->sum('total_price'),
            'posted_budget' => $postedItems->sum('total_price'),
        ];

        return view('rpd.show', compact('rpd', 'items', 'summary', 'unpostedCount', 'postedCount'));
    }

    /**
     * Memperbarui satu item pecahan bahan sebelum diposting.
     */
    public function updateItem(Request $request, RpdItem $item)
    {
        $request->validate([
            'category' => 'required|in:bahan,upah,alat,operasional',
            'item_name' => 'required|string|max:255',
            'volume' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'planned_date' => 'required|date',
            'supplier_name' => 'nullable|string|max:255',
            'payment_method' => 'required|in:belanja_tunai,belanja_transfer',
            'has_tax' => 'nullable|boolean',
        ]);

        $totalPrice = $request->volume * $request->unit_price;
        $category = $request->category;
        $hasTax = $request->boolean('has_tax');

        $taxPpn = 0;
        $taxPph22 = 0;
        $taxPph21 = 0;

        if ($hasTax) {
            if ($category === 'bahan' || $category === 'alat' || $category === 'operasional') {
                $dpp = round($totalPrice / 1.11, 2);
                $taxPpn = round($totalPrice - $dpp, 2);
                $taxPph22 = round($dpp * 0.015, 2);
            } elseif ($category === 'upah') {
                $unit = strtoupper(trim($request->unit ?? ''));
                $unitPrice = (float) $request->unit_price;
                $isHok = in_array($unit, ['OH', 'HOK', 'HARI']);
                $dailyRate = $isHok ? $unitPrice : 0;

                if ($dailyRate > 2500000) {
                    $taxPph21 = round($totalPrice * 0.5 * 0.05, 2);
                } elseif ($dailyRate > 450000) {
                    $taxPph21 = round($totalPrice * 0.005, 2);
                } else {
                    $taxPph21 = 0;
                    $hasTax = false;
                }
            }
        }

        $taxTotal = $taxPpn + $taxPph22 + $taxPph21;
        $netAmount = $totalPrice - ($taxPph22 + $taxPph21);

        $rawSupplier = trim((string) $request->input('supplier_name', ''));
        $supplierName = $rawSupplier !== '' ? $rawSupplier : '....................';

        $item->update([
            'category' => $category,
            'item_name' => $request->item_name,
            'volume' => $request->volume,
            'unit' => $request->unit,
            'unit_price' => $request->unit_price,
            'total_price' => $totalPrice,
            'planned_date' => $request->planned_date,
            'supplier_name' => $supplierName,
            'payment_method' => $request->payment_method,
            'has_tax' => $hasTax,
            'tax_ppn' => $taxPpn,
            'tax_pph22' => $taxPph22,
            'tax_pph21' => $taxPph21,
            'tax_total' => $taxTotal,
            'net_amount' => $netAmount,
            'bku_description' => $request->bku_description ?: $item->bku_description,
        ]);

        // Rekalkulasi dokumen induk
        $doc = $item->document;
        $doc->update([
            'total_budget' => $doc->items->sum('total_price'),
            'total_materials' => $doc->items->where('category', 'bahan')->sum('total_price'),
            'total_wages' => $doc->items->where('category', 'upah')->sum('total_price'),
            'total_equipment' => $doc->items->where('category', 'alat')->sum('total_price'),
            'total_operational' => $doc->items->where('category', 'operasional')->sum('total_price'),
            'total_tax_estimated' => $doc->items->sum('tax_total'),
            'total_net_estimated' => $doc->items->sum('net_amount'),
        ]);

        return back()->with('success', 'Baris item pecahan bahan berhasil diperbarui.');
    }

    /**
     * Eksekusi "Posting ke BKU" - Menjadikan seluruh pecahan bahan transaksi resmi BKU.
     * Otomatis menyertakan pencatatan saldo masuk pencairan dana dan penarikan kas tunai.
     */
    public function postToBku(RpdDocument $rpd)
    {
        try {
            $count = $this->breakdownService->postToBku($rpd);

            if ($count === 0) {
                return back()->with('warning', 'Semua item dalam dokumen RPD ini dan transaksi saldo masuk pencairan sudah pernah diposting ke BKU.');
            }

            SystemNotification::send(
                type: 'bku',
                title: 'Posting RPD ke BKU Berhasil',
                message: "Sebanyak {$count} transaksi resmi dari dokumen RPD '{$rpd->title}' telah berhasil diposting ke Buku Kas Umum (BKU), BPK, BB, dan Buku Pembantu Pajak.",
                url: route('reports.bku', ['project_id' => $rpd->project_id]),
                icon: 'book-open',
                color: 'sky',
                data: ['rpd_id' => $rpd->id, 'project_id' => $rpd->project_id, 'posted_count' => $count]
            );

            return redirect()->route('rpd.show', $rpd->id)
                ->with('success', "Alhamdulillah! Sebanyak {$count} transaksi resmi (termasuk Saldo Masuk Pencairan & Penarikan Tunai Kas) berhasil dibukukan ke Buku Kas Umum (BKU), BPK, BB, dan Buku Pembantu Pajak!");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memposting ke BKU: ' . $e->getMessage());
        }
    }

    /**
     * Catat / Sinkronkan Otomatis Saldo Masuk Pencairan Termin & Tarik Tunai Kas.
     */
    public function syncDisbursement(RpdDocument $rpd)
    {
        try {
            $created = $this->breakdownService->recordDisbursementTransactions($rpd);

            if (empty($created)) {
                return back()->with('info', 'Transaksi Saldo Masuk (Pencairan Termin) dan Penarikan Tunai Kas untuk dokumen ini sudah tercatat lengkap di BKU.');
            }

            SystemNotification::send(
                type: 'bku',
                title: 'Sinkronisasi Saldo Masuk & Tarik Tunai BKU',
                message: "Sebanyak " . count($created) . " transaksi Saldo Masuk & Tarik Tunai dari dokumen RPD '{$rpd->title}' berhasil dicatatkan ke BKU.",
                url: route('reports.bku', ['project_id' => $rpd->project_id]),
                icon: 'book-open',
                color: 'sky'
            );

            return back()->with('success', 'Alhamdulillah! Berhasil mencatat otomatis ' . count($created) . ' transaksi Saldo Masuk Pencairan Termin & Penarikan Tunai Kas ke BKU.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mencatat saldo masuk: ' . $e->getMessage());
        }
    }

    /**
     * Ekspor Output Pecah Bahan yang siap menjadi Isian BKU (Excel .xlsx atau CSV).
     */
    public function exportBku(Request $request, RpdDocument $rpd)
    {
        $format = $request->get('format', 'xlsx');
        $rpd->load(['school', 'project', 'items']);
        $items = $rpd->items;

        $fileName = 'Isian_BKU_' . str_replace(' ', '_', $rpd->title) . '_' . date('Ymd');

        if ($format === 'csv') {
            return new StreamedResponse(function () use ($items) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM

                fputcsv($handle, [
                    'No',
                    'Tanggal Transaksi BKU',
                    'No Bukti / Kuitansi',
                    'Jenis Buku Pembantu',
                    'Uraian Transaksi BKU',
                    'Penerima / Toko',
                    'Pengeluaran Bruto (Rp)',
                    'Potongan PPN (Rp)',
                    'Potongan PPh 22 (Rp)',
                    'Potongan PPh 21 (Rp)',
                    'Total Pajak (Rp)',
                    'Dibayarkan Bersih (Rp)',
                    'Status Posting'
                ]);

                foreach ($items as $idx => $it) {
                    fputcsv($handle, [
                        $idx + 1,
                        Carbon::parse($it->planned_date)->format('d/m/Y'),
                        $it->bku_number ?: ('DRAFT-' . ($idx + 1)),
                        $it->payment_method === 'belanja_transfer' ? 'Bank Jatim (BB)' : 'Kas Tunai (BPK)',
                        $it->bku_description,
                        $it->supplier_name,
                        $it->total_price,
                        $it->tax_ppn,
                        $it->tax_pph22,
                        $it->tax_pph21,
                        $it->tax_total,
                        $it->net_amount,
                        $it->is_posted ? 'Sudah di BKU' : 'Draft Siap BKU',
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}.csv\"",
            ]);
        }

        // Excel Export via PhpSpreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Isian BKU Revitalisasi');

        // Kop Laporan
        $sheet->setCellValue('A1', 'OUTPUT PECAH BAHAN DOKUMEN RPD SEBAGAI ISIAN BUKU KAS UMUM (BKU)');
        $sheet->setCellValue('A2', 'SATUAN PENDIDIKAN: ' . strtoupper($rpd->school->name));
        $sheet->setCellValue('A3', 'PROYEK: ' . strtoupper($rpd->project->title) . ' | TAHAP: ' . $rpd->term_stage);
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');
        $sheet->mergeCells('A3:L3');

        $sheet->getStyle('A1:A3')->getFont()->setBold(true);
        $sheet->getStyle('A1')->setSize(12);

        // Header
        $headers = [
            'A5' => 'No',
            'B5' => 'Tgl BKU',
            'C5' => 'No Bukti',
            'D5' => 'Buku Kas',
            'E5' => 'Uraian Transaksi BKU',
            'F5' => 'Penerima / Toko',
            'G5' => 'Pengeluaran Bruto (Rp)',
            'H5' => 'PPN 11%',
            'I5' => 'PPh 22',
            'J5' => 'PPh 21',
            'K5' => 'Total Pajak',
            'L5' => 'Jumlah Bersih (Rp)',
        ];

        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $sheet->getStyle('A5:L5')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $sheet->getStyle('A5:L5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF047857');
        $sheet->getStyle('A5:L5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(5)->setRowHeight(26);

        $rowIdx = 6;
        foreach ($items as $idx => $it) {
            $sheet->setCellValue('A' . $rowIdx, $idx + 1);
            $sheet->setCellValue('B' . $rowIdx, Carbon::parse($it->planned_date)->format('d/m/Y'));
            $sheet->setCellValue('C' . $rowIdx, $it->bku_number ?: ('DRAFT-' . ($idx + 1)));
            $sheet->setCellValue('D' . $rowIdx, $it->payment_method === 'belanja_transfer' ? 'Bank (BB)' : 'Kas (BPK)');
            $sheet->setCellValue('E' . $rowIdx, $it->bku_description);
            $sheet->setCellValue('F' . $rowIdx, $it->supplier_name);
            $sheet->setCellValue('G' . $rowIdx, $it->total_price);
            $sheet->setCellValue('H' . $rowIdx, $it->tax_ppn);
            $sheet->setCellValue('I' . $rowIdx, $it->tax_pph22);
            $sheet->setCellValue('J' . $rowIdx, $it->tax_pph21);
            $sheet->setCellValue('K' . $rowIdx, $it->tax_total);
            $sheet->setCellValue('L' . $rowIdx, $it->net_amount);

            $sheet->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle('G' . $rowIdx . ':L' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');

            $rowIdx++;
        }

        // Total
        $sheet->setCellValue('A' . $rowIdx, 'TOTAL KESELURUHAN');
        $sheet->mergeCells("A{$rowIdx}:F{$rowIdx}");
        $sheet->setCellValue('G' . $rowIdx, "=SUM(G6:G" . ($rowIdx - 1) . ")");
        $sheet->setCellValue('H' . $rowIdx, "=SUM(H6:H" . ($rowIdx - 1) . ")");
        $sheet->setCellValue('I' . $rowIdx, "=SUM(I6:I" . ($rowIdx - 1) . ")");
        $sheet->setCellValue('J' . $rowIdx, "=SUM(J6:J" . ($rowIdx - 1) . ")");
        $sheet->setCellValue('K' . $rowIdx, "=SUM(K6:K" . ($rowIdx - 1) . ")");
        $sheet->setCellValue('L' . $rowIdx, "=SUM(L6:L" . ($rowIdx - 1) . ")");

        $sheet->getStyle("A{$rowIdx}:L{$rowIdx}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G{$rowIdx}:L{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A{$rowIdx}:L{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');

        $sheet->getStyle("A5:L{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}.xlsx\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Hapus Dokumen RPD & Item-itemnya.
     * Jika dokumen sudah diposting ke BKU, otomatis membatalkan posting dan menghapus seluruh transaksi terkait di BKU.
     */
    public function destroy(RpdDocument $rpd)
    {
        try {
            $isPosted = $rpd->status === 'posted_to_bku' || $rpd->items()->where('is_posted', true)->exists();

            $this->breakdownService->deleteDocumentWithTransactions($rpd);

            $message = $isPosted
                ? 'Dokumen RPD berhasil dihapus. Seluruh pembukuan transaksinya di BKU, BPK, BB, dan Buku Pembantu Pajak telah otomatis dibatalkan dan dibersihkan.'
                : 'Dokumen RPD berhasil dihapus.';

            return redirect()->route('rpd.index')->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus dokumen RPD: ' . $e->getMessage());
        }
    }

    /**
     * Batalkan posting BKU dari dokumen RPD tanpa menghapus dokumen itu sendiri.
     */
    public function unpost(RpdDocument $rpd)
    {
        try {
            $deletedCount = $this->breakdownService->unpostFromBku($rpd);

            return redirect()->route('rpd.show', $rpd->id)->with('success', "Posting ke BKU berhasil dibatalkan. Sebanyak {$deletedCount} transaksi terkait di Buku Kas Umum (BKU) telah dihapus. Status dokumen dikembalikan menjadi Draf Siap Posting.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membatalkan posting BKU: ' . $e->getMessage());
        }
    }

    /**
     * Memvariasikan dan menyempurnakan draf isian BKU dengan AI Google Gemini atau Mesin Cerdas Lokal.
     */
    public function enhanceWithAi(Request $request, RpdDocument $rpd)
    {
        try {
            $apiKey = $request->input('gemini_api_key');
            $overwriteSupplier = $request->boolean('overwrite_supplier', false);

            $result = $this->breakdownService->enhanceDocumentWithAi($rpd, [
                'api_key' => $apiKey,
                'overwrite_supplier' => $overwriteSupplier,
            ]);

            if (!$result['success']) {
                return back()->with('warning', $result['message']);
            }

            $prefix = $result['used_ai'] ? "✨ Gemini AI ({$result['model']})" : "⚡ Mesin Cerdas Lokal";
            return back()->with('success', "{$prefix}: {$result['message']} ({$result['items_count']} baris item berhasil divariasikan).");
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses variasi AI: ' . $e->getMessage());
        }
    }

    /**
     * AJAX endpoint: Merekomendasikan rujukan harga satuan, volume, dan toko bangunan Situbondo terdekat.
     */
    public function suggestSitubondoPrice(Request $request)
    {
        $request->validate([
            'item_name' => 'required|string',
            'category' => 'nullable|string',
            'total_price' => 'nullable|numeric',
            'unit' => 'nullable|string',
            'school_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'kecamatan' => 'nullable|string',
        ]);

        $itemName = $request->input('item_name');
        $category = $request->input('category', 'bahan');
        $totalPrice = (float)($request->input('total_price') ?? 0);
        $unit = $request->input('unit');
        $kecamatan = $request->input('kecamatan');
        $address = null;

        if (!$kecamatan) {
            $school = null;
            if ($request->filled('school_id')) {
                $school = School::find($request->school_id);
            } elseif ($request->filled('project_id')) {
                $project = RevitalizationProject::with('school')->find($request->project_id);
                $school = $project?->school;
            } elseif (auth()->user()?->school_id) {
                $school = School::find(auth()->user()->school_id);
            }
            if ($school) {
                $kecamatan = $school->kecamatan;
                $address = $school->address;
            }
        }

        $gemini = app(GeminiRpdService::class);
        $priceData = $gemini->determineSitubondoPrice($itemName, $category, $totalPrice, $unit);
        $nearestStores = $gemini->getNearestStoresForSchool($kecamatan, $address, $category, $itemName);

        $recommendedStore = 'Toko Bangunan Terdekat Situbondo';
        if (!empty($nearestStores)) {
            $firstStore = $nearestStores[0];
            $recommendedStore = is_array($firstStore) ? ($firstStore['name'] ?? $recommendedStore) : $firstStore;
        }

        return response()->json([
            'success' => true,
            'kecamatan' => $kecamatan ?: 'Kabupaten Situbondo',
            'price_data' => $priceData,
            'nearest_stores' => $nearestStores,
            'recommended_store' => $recommendedStore,
        ]);
    }
}

