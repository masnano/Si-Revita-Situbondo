<?php

namespace App\Http\Controllers;

use App\Services\HpsConversionService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HpsConversionController extends Controller
{
    protected HpsConversionService $hpsConversionService;

    public function __construct(HpsConversionService $hpsConversionService)
    {
        $this->hpsConversionService = $hpsConversionService;
    }

    /**
     * Menampilkan halaman konversi data sheet HPS ke template keluaran.
     */
    public function index()
    {
        return view('hps.index');
    }

    /**
     * Memproses berkas HPS yang diunggah dan mengembalikan berkas template hasil konversi.
     */
    public function convert(Request $request): StreamedResponse
    {
        $request->validate([
            'hps_file' => 'required|file|max:20480',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'divide_ppn' => 'nullable|boolean',
        ], [
            'hps_file.required' => 'Pilih berkas HPS yang akan dikonversi.',
            'hps_file.max' => 'Ukuran berkas maksimal 20 MB.',
        ]);

        $file = $request->file('hps_file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['xlsx', 'xls', 'csv', 'txt', 'pdf'], true)) {
            return back()->withInput()->with('error', 'Format berkas tidak didukung. Gunakan berkas Excel (.xlsx, .xls), PDF (.pdf), atau CSV (.csv).');
        }
        try {
            $items = $this->hpsConversionService->convertFileToRows($file->getRealPath(), $ext, [
                'tax_percent' => $request->filled('tax_percent') ? (float) $request->input('tax_percent') : 11,
                'divide_ppn' => $this->submittedFlag($request, 'divide_ppn', true),
                'include_tax_label' => $this->submittedFlag($request, 'include_tax_label', true),
                'match_hps_total' => $this->submittedFlag($request, 'match_hps_total', false),
            ]);

            $spreadsheet = $this->hpsConversionService->buildSpreadsheet($items);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Gagal memproses berkas HPS: ' . $e->getMessage());
        }

        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = 'rincian-hps-' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
            'X-Converted-Rows' => (string) count($items),
            'X-Source-File' => $baseName,
        ]);
    }

    /**
     * Membaca nilai checkbox yang dapat terkirim sebagai array (hidden 0 + checkbox 1).
     * Nilai "1" pada posisi mana pun dianggap dicentang.
     */
    protected function submittedFlag(Request $request, string $key, bool $default): bool
    {
        $value = $request->input($key, $default);

        if (is_array($value)) {
            return in_array('1', array_map('strval', $value), true);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
