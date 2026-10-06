<?php

namespace App\Http\Controllers;

use App\Models\RevitalizationProject;
use App\Models\RpdDocument;
use App\Models\RpdItem;
use App\Models\School;
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
     * Unduh Template Format RPD (Excel .xlsx atau CSV).
     */
    public function template(Request $request)
    {
        $format = $request->get('format', 'xlsx');

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

        // Default Excel format
        $spreadsheet = $this->breakdownService->generateTemplateSpreadsheet();
        $fileName = 'Template_RPD_Revitalisasi_Situbondo_2026.xlsx';

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
     * Memproses upload file RPD dan menghasilkan output Pecah Bahan BKU.
     */
    public function store(Request $request)
    {
        $request->validate([
            'school_id' => 'required|exists:schools,id',
            'project_id' => 'required|exists:revitalization_projects,id',
            'term_stage' => 'required|string|max:100',
            'rpd_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'title' => 'nullable|string|max:255',
            'default_date' => 'nullable|date',
            'default_store' => 'nullable|string|max:255',
        ], [
            'rpd_file.required' => 'Pilih file RPD yang akan diunggah.',
            'rpd_file.mimes' => 'Format file harus berupa Excel (.xlsx, .xls) atau CSV (.csv).',
            'project_id.required' => 'Pilih proyek revitalisasi yang dituju.',
        ]);

        $project = RevitalizationProject::findOrFail($request->project_id);

        try {
            $document = $this->breakdownService->processUpload($request->file('rpd_file'), $project, [
                'term_stage' => $request->term_stage,
                'title' => $request->title ?: ('RPD ' . $request->term_stage . ' - ' . $project->title),
                'default_date' => $request->default_date ?: now()->format('Y-m-d'),
                'default_store' => $request->default_store ?: 'Toko Bangunan Berkah Situbondo',
                'notes' => $request->notes,
            ]);

            return redirect()->route('rpd.show', $document->id)
                ->with('success', "File RPD berhasil diproses! {$document->total_items_count} item berhasil dipecah menjadi kandidat isian BKU.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses file RPD: ' . $e->getMessage());
        }
    }

    /**
     * Pratinjau Output Pecah Bahan & Tabel Isian BKU.
     */
    public function show(RpdDocument $rpd)
    {
        $rpd->load(['school', 'project', 'items.budgetItem', 'items.transaction']);

        $items = $rpd->items;

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
        ];

        return view('rpd.show', compact('rpd', 'items', 'summary'));
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
                $taxPph21 = round($totalPrice * 0.05, 2);
            }
        }

        $taxTotal = $taxPpn + $taxPph22 + $taxPph21;
        $netAmount = $totalPrice - ($taxPph22 + $taxPph21);

        $item->update([
            'category' => $category,
            'item_name' => $request->item_name,
            'volume' => $request->volume,
            'unit' => $request->unit,
            'unit_price' => $request->unit_price,
            'total_price' => $totalPrice,
            'planned_date' => $request->planned_date,
            'supplier_name' => $request->supplier_name,
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
     */
    public function postToBku(RpdDocument $rpd)
    {
        try {
            $count = $this->breakdownService->postToBku($rpd);

            if ($count === 0) {
                return back()->with('warning', 'Semua item dalam dokumen RPD ini sudah pernah diposting ke BKU.');
            }

            return redirect()->route('rpd.show', $rpd->id)
                ->with('success', "Alhamdulillah! Sebanyak {$count} transaksi berhasil dibukukan ke Buku Kas Umum (BKU), BPK, BB, dan Buku Pembantu Pajak!");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memposting ke BKU: ' . $e->getMessage());
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
     */
    public function destroy(RpdDocument $rpd)
    {
        if ($rpd->status === 'posted_to_bku') {
            return back()->with('error', 'Dokumen RPD yang transaksinya sudah diposting ke BKU tidak dapat dihapus langsung untuk menjaga integritas pembukuan.');
        }

        $rpd->delete();

        return redirect()->route('rpd.index')->with('success', 'Dokumen RPD berhasil dihapus.');
    }
}

