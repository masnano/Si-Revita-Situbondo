<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use App\Models\RevitalizationProject;
use Illuminate\Http\Request;

class BudgetItemController extends Controller
{
    public function index(Request $request)
    {
        $projects = RevitalizationProject::with('school')->get();
        $selectedProjectId = $request->get('project_id', $projects->first()?->id);
        $selectedProject = RevitalizationProject::with('school')->find($selectedProjectId);

        $items = BudgetItem::where('project_id', $selectedProjectId)
            ->with(['transactions'])
            ->get();

        $totalRAB = $items->sum('total_price');

        return view('rab.index', compact('projects', 'selectedProject', 'selectedProjectId', 'items', 'totalRAB'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:revitalization_projects,id',
            'code' => 'nullable|string|max:50',
            'category' => 'required|string',
            'name' => 'required|string|max:255',
            'volume' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $totalPrice = (float) $validated['volume'] * (float) $validated['unit_price'];

        BudgetItem::create([
            'project_id' => $validated['project_id'],
            'code' => $validated['code'],
            'category' => $validated['category'],
            'name' => $validated['name'],
            'volume' => $validated['volume'],
            'unit' => $validated['unit'],
            'unit_price' => $validated['unit_price'],
            'total_price' => $totalPrice,
            'notes' => $validated['notes'],
        ]);

        return back()->with('success', 'Item RAB berhasil ditambahkan!');
    }

    public function update(Request $request, BudgetItem $budgetItem)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'category' => 'required|string',
            'name' => 'required|string|max:255',
            'volume' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $totalPrice = (float) $validated['volume'] * (float) $validated['unit_price'];

        $budgetItem->update([
            'code' => $validated['code'],
            'category' => $validated['category'],
            'name' => $validated['name'],
            'volume' => $validated['volume'],
            'unit' => $validated['unit'],
            'unit_price' => $validated['unit_price'],
            'total_price' => $totalPrice,
            'notes' => $validated['notes'],
        ]);

        return back()->with('success', 'Item RAB berhasil diperbarui!');
    }

    public function destroy(BudgetItem $budgetItem)
    {
        $budgetItem->delete();
        return back()->with('success', 'Item RAB berhasil dihapus!');
    }
}

