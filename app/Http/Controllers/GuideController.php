<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GuideController extends Controller
{
    /**
     * Tampilkan halaman viewer buku panduan dan embed PDF.
     */
    public function index()
    {
        $pdfPath = public_path('docs/Buku_Panduan_Penggunaan_Si_Revita.pdf');
        $pdfExists = file_exists($pdfPath);
        $fileSize = $pdfExists ? round(filesize($pdfPath) / 1024 / 1024, 2) : 0;

        return view('guide.index', compact('pdfExists', 'fileSize'));
    }

    /**
     * Unduh file PDF buku panduan penggunaan.
     */
    public function download(): BinaryFileResponse
    {
        $path = public_path('docs/Buku_Panduan_Penggunaan_Si_Revita.pdf');

        if (!file_exists($path)) {
            abort(404, 'File buku panduan tidak ditemukan.');
        }

        return response()->download($path, 'Buku_Panduan_Penggunaan_Si_Revita_Situbondo_2026.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Preview inline file PDF.
     */
    public function preview(): BinaryFileResponse
    {
        $path = public_path('docs/Buku_Panduan_Penggunaan_Si_Revita.pdf');

        if (!file_exists($path)) {
            abort(404, 'File buku panduan tidak ditemukan.');
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Buku_Panduan_Penggunaan_Si_Revita.pdf"',
        ]);
    }
}

