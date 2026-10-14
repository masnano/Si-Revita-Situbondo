<?php

namespace App\Http\Controllers;

use App\Models\RevitalizationProject;
use App\Models\School;
use App\Models\SystemNotification;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
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
        ]);

        $project = RevitalizationProject::create($validated);
        $project->load('school');
        $formattedAmount = number_format($project->contract_amount, 0, ',', '.');
        $schoolName = $project->school->name ?? 'Sekolah';

        SystemNotification::send(
            type: 'project',
            title: 'Penambahan Paket Revitalisasi Baru',
            message: "Paket '{$project->title}' untuk {$schoolName} (TA {$project->fiscal_year}, Pagu Rp {$formattedAmount}) berhasil didaftarkan.",
            url: route('projects.show', $project->id),
            icon: 'building-2',
            color: 'indigo',
            data: ['project_id' => $project->id, 'title' => $project->title, 'amount' => $project->contract_amount]
        );

        return redirect()->route('projects.index')->with('success', 'Paket Revitalisasi berhasil ditambahkan!');
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
        ]);

        $project->update($validated);
        return redirect()->route('projects.index')->with('success', 'Paket Revitalisasi berhasil diperbarui!');
    }

    public function destroy(RevitalizationProject $project)
    {
        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Paket Revitalisasi berhasil dihapus!');
    }
}

