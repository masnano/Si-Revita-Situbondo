<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\KemendikdasmenReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KemendikdasmenReportController extends Controller
{
    protected KemendikdasmenReportService $reportService;

    public function __construct(KemendikdasmenReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Tampilan utama Output Laporan Format Kemendikdasmen 2026.
     * Menyajikan pratinjau interaktif 4 Buku: BKU, BPKT, BB, BPP (Alt 1 & Alt 2)
     * lengkap dengan rekonsiliasi keseimbangan dan petunjuk teknis.
     */
    public function index(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $schools = School::orderBy('name')->get();
        $selectedSchool = School::find($schoolId) ?? $schools->first();

        if (!$selectedSchool) {
            return redirect()->route('dashboard')->with('error', 'Belum ada data sekolah yang terdaftar.');
        }

        $availableMonths = $this->reportService->getAvailableProjectMonths($selectedSchool);
        $selectedMonthIndex = (int)$request->get('month_index', 1);

        if (!isset($availableMonths[$selectedMonthIndex])) {
            $selectedMonthIndex = array_key_first($availableMonths) ?? 1;
        }

        $reportData = $this->reportService->getMonthlyReportData($selectedSchool, $selectedMonthIndex);

        return view('reports.kemendikdasmen', compact(
            'schools',
            'selectedSchool',
            'availableMonths',
            'selectedMonthIndex',
            'reportData'
        ));
    }

    /**
     * Unduh Output Laporan Excel (.xlsx) Resmi Format Kemendikdasmen
     * dengan rumus-rumus aktif dan susunan sheet persis sesuai Bimtek.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $school = School::findOrFail($schoolId);

        $targetMonth = $request->filled('month_index') && $request->month_index !== 'all' 
            ? (int)$request->month_index 
            : null;

        return $this->reportService->exportExcel($school, $targetMonth);
    }

    /**
     * Unduh Template Master Kosong Kemendikdasmen asli dari Bimtek.
     */
    public function downloadMasterTemplate(): BinaryFileResponse
    {
        $templatePath = storage_path('app/templates/kemendikdasmen/template_kemendikdasmen_kosong.xlsx');
        if (!file_exists($templatePath)) {
            $src = 'D:/RAN/SARPRAS FILE/SPT/BIMTEK PELAPORAN KEU SD/Materi Bimtek Percepatan NEW/Materi 3_ Laporan Keuangan dan Perpajakan/261030 - Format Kosong BKU BPKT BB BPP - Revitalisasi SD 2026.xlsx';
            if (file_exists($src)) {
                @mkdir(dirname($templatePath), 0777, true);
                copy($src, $templatePath);
            } else {
                abort(404, 'File template kosong Kemendikdasmen tidak ditemukan.');
            }
        }

        return response()->download(
            $templatePath,
            '261030_Format_Kosong_BKU_BPKT_BB_BPP_Revitalisasi_SD_2026.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Resolves school ID depending on user's role.
     */
    protected function resolveSchoolId(Request $request): ?int
    {
        $user = auth()->user();
        if ($user && $user->school_id && !$user->isRoot()) {
            return (int)$user->school_id;
        }
        return $request->filled('school_id') ? (int)$request->school_id : null;
    }
}
