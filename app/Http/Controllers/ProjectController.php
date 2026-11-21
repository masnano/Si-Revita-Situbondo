<?php

namespace App\Http\Controllers;

use App\Models\RevitalizationProject;
use App\Models\School;
use App\Models\SystemNotification;
use App\Services\KurvaSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    protected KurvaSService $kurvaSService;

    public function __construct(KurvaSService $kurvaSService)
    {
        $this->kurvaSService = $kurvaSService;
    }

    public function index(Request $request)
    {
        $query = RevitalizationProject::with(['school', 'budgetItems', 'transactions']);

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year', $request->fiscal_year);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $projects = $query->latest()->paginate(10)->withQueryString();
        $schools = School::orderBy('name')->get();

        return view('projects.index', compact('projects', 'schools'));
    }

    public function create()
    {
        $schools = School::orderBy('name')->get();
        return view('projects.create', compact('schools'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'fiscal_year' => 'required|integer|min:2020|max:2035',
            'title' => 'required|string|max:255',
            'funding_source' => 'required|string|max:255',
            'spk_number' => 'required|string|max:100',
            'spk_date' => 'required|date',
            'contract_amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:perencanaan,pelaksanaan,selesai,evaluasi',
            'physical_progress' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'kurva_s' => 'nullable|file|mimes:pdf,xlsx,xls|max:20480',
        ]);

        if ($request->hasFile('kurva_s')) {
            $file = $request->file('kurva_s');
            $originalName = $file->getClientOriginalName();
            $path = $file->store('kurva_s', 'public');

            try {
                $parsedData = $this->kurvaSService->parseFile($file, $originalName);
                $validated['kurva_s_path'] = $path;
                $validated['kurva_s_filename'] = $originalName;
                $validated['kurva_s_data'] = $parsedData;
            } catch (\Throwable $e) {
                // Tetap simpan path file meski parsing gagal dengan fallback
                $validated['kurva_s_path'] = $path;
                $validated['kurva_s_filename'] = $originalName;
                $validated['kurva_s_data'] = [
                    'filename' => $originalName,
                    'error' => $e->getMessage(),
                    'total_weeks' => 13,
                ];
            }
        }

        unset($validated['kurva_s']);
        $project = RevitalizationProject::create($validated);
        $project->load('school');
        $formattedAmount = number_format($project->contract_amount, 0, ',', '.');
        $schoolName = $project->school->name ?? 'Sekolah';

        $kurvaNotice = !empty($project->kurva_s_path) ? ' (Kurva S berhasil diproses)' : '';

        SystemNotification::send(
            type: 'project',
            title: 'Penambahan Paket Revitalisasi Baru',
            message: "Paket '{$project->title}' untuk {$schoolName} (TA {$project->fiscal_year}, Pagu Rp {$formattedAmount}){$kurvaNotice} berhasil didaftarkan.",
            url: route('projects.show', $project->id),
            icon: 'building-2',
            color: 'indigo',
            data: ['project_id' => $project->id, 'title' => $project->title, 'amount' => $project->contract_amount]
        );

        return redirect()->route('projects.index')->with('success', 'Paket Revitalisasi berhasil ditambahkan!' . $kurvaNotice);
    }

    public function show(RevitalizationProject $project)
    {
        $project->load(['school', 'budgetItems.transactions', 'transactions' => function ($q) {
            $q->latest('transaction_date');
        }]);

        return view('projects.show', compact('project'));
    }

    public function edit(RevitalizationProject $project)
    {
        $schools = School::orderBy('name')->get();
        return view('projects.edit', compact('project', 'schools'));
    }

    public function update(Request $request, RevitalizationProject $project)
    {
        $validated = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'fiscal_year' => 'required|integer|min:2020|max:2035',
            'title' => 'required|string|max:255',
            'funding_source' => 'required|string|max:255',
            'spk_number' => 'required|string|max:100',
            'spk_date' => 'required|date',
            'contract_amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:perencanaan,pelaksanaan,selesai,evaluasi',
            'physical_progress' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'kurva_s' => 'nullable|file|mimes:pdf,xlsx,xls|max:20480',
            'delete_kurva_s' => 'nullable|boolean',
        ]);

        // Cek hapus Kurva S
        if ($request->boolean('delete_kurva_s')) {
            if ($project->kurva_s_path && Storage::disk('public')->exists($project->kurva_s_path)) {
                Storage::disk('public')->delete($project->kurva_s_path);
            }
            $validated['kurva_s_path'] = null;
            $validated['kurva_s_filename'] = null;
            $validated['kurva_s_data'] = null;
        }

        // Cek upload Kurva S baru
        if ($request->hasFile('kurva_s')) {
            if ($project->kurva_s_path && Storage::disk('public')->exists($project->kurva_s_path)) {
                Storage::disk('public')->delete($project->kurva_s_path);
            }

            $file = $request->file('kurva_s');
            $originalName = $file->getClientOriginalName();
            $path = $file->store('kurva_s', 'public');

            try {
                $parsedData = $this->kurvaSService->parseFile($file, $originalName);
                $validated['kurva_s_path'] = $path;
                $validated['kurva_s_filename'] = $originalName;
                $validated['kurva_s_data'] = $parsedData;
            } catch (\Throwable $e) {
                $validated['kurva_s_path'] = $path;
                $validated['kurva_s_filename'] = $originalName;
                $validated['kurva_s_data'] = [
                    'filename' => $originalName,
                    'error' => $e->getMessage(),
                    'total_weeks' => 13,
                ];
            }
        }

        unset($validated['kurva_s'], $validated['delete_kurva_s']);
        $project->update($validated);

        // Jika Kurva S baru diupload, selaraskan tanggal pada dokumen RPD terkait yang belum diposting
        $alignMessage = '';
        if ($request->hasFile('kurva_s') && !empty($project->kurva_s_data['work_items'])) {
            $rpdDocs = \App\Models\RpdDocument::where('project_id', $project->id)->get();
            $totalUpdated = 0;
            foreach ($rpdDocs as $doc) {
                $alignRes = $this->kurvaSService->alignRpdDocumentToKurvaS($doc);
                $totalUpdated += ($alignRes['updated_count'] ?? 0);
            }
            if ($totalUpdated > 0) {
                $alignMessage = " Sebanyak {$totalUpdated} item RPD diselaraskan tanggalnya secara otomatis.";
            }
        }

        return redirect()->route('projects.index')->with('success', 'Paket Revitalisasi berhasil diperbarui!' . $alignMessage);
    }

    public function destroy(RevitalizationProject $project)
    {
        if ($project->kurva_s_path && Storage::disk('public')->exists($project->kurva_s_path)) {
            Storage::disk('public')->delete($project->kurva_s_path);
        }

        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Paket Revitalisasi berhasil dihapus!');
    }

    /**
     * Unduh atau pratinjau berkas Kurva S proyek.
     */
    public function downloadKurvaS(RevitalizationProject $project)
    {
        if (!$project->kurva_s_path || !Storage::disk('public')->exists($project->kurva_s_path)) {
            return back()->with('error', 'Berkas Kurva S tidak ditemukan di penyimpanan server.');
        }

        $fullPath = Storage::disk('public')->path($project->kurva_s_path);
        $fileName = $project->kurva_s_filename ?: basename($project->kurva_s_path);
        $mime = Storage::disk('public')->mimeType($project->kurva_s_path);

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => "inline; filename=\"{$fileName}\"",
        ]);
    }
}
