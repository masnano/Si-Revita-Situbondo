<?php

namespace App\Http\Controllers;

use App\Models\RevitalizationProject;
use App\Models\School;
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

        RevitalizationProject::create($validated);
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

