<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use App\Models\RevitalizationProject;
use App\Services\RabImportService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BudgetItemController extends Controller
{
    protected RabImportService $rabImportService;

    public function __construct(RabImportService $rabImportService)
    {
        $this->rabImportService = $rabImportService;
    }

    public function index(Request $request)
    {
        $projects = RevitalizationProject::with('school')->get();
        $selectedProjectId = $request->get('project_id', $projects->first()?->id);
        $selectedProject = RevitalizationProject::with('school')->find($selectedProjectId);

        // Filter Menu Pekerjaan
        $selectedMenu = $request->get('menu_pekerjaan', 'all');

        $allProjectItems = BudgetItem::where('project_id', $selectedProjectId)
            ->with(['transactions'])
            ->get();

        $totalRAB = $allProjectItems->sum('total_price');

        // Statistik ringkasan per Menu Pekerjaan
        $menuStats = $allProjectItems->groupBy('menu_pekerjaan')->map(function ($menuItems, $menuName) {
            $pagu = $menuItems->sum('total_price');
            $realisasi = $menuItems->sum(function ($it) {
                return $it->transactions->whereIn('type', ['belanja_tunai', 'belanja_transfer'])->sum('amount');
            });
            return [
                'name' => $menuName,
                'count' => $menuItems->count(),
                'pagu' => $pagu,
                'realisasi' => $realisasi,
                'sisa' => $pagu - $realisasi,
            ];
        });

        // Filter query item sesuai menu terpilih
        $itemsQuery = BudgetItem::where('project_id', $selectedProjectId)
            ->with(['transactions']);

        if (!empty($selectedMenu) && $selectedMenu !== 'all') {
            $itemsQuery->where('menu_pekerjaan', $selectedMenu);
        }

        $items = $itemsQuery->orderBy('menu_pekerjaan')->orderBy('id')->get();
        $standardMenus = BudgetItem::STANDARD_MENUS;

        return view('rab.index', compact(
            'projects',
            'selectedProject',
            'selectedProjectId',
            'items',
            'totalRAB',
            'selectedMenu',
            'menuStats',
            'standardMenus'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:revitalization_projects,id',
            'menu_pekerjaan' => 'required|string|max:150',
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
            'menu_pekerjaan' => $validated['menu_pekerjaan'],
            'code' => $validated['code'],
            'category' => $validated['category'],
            'name' => $validated['name'],
            'volume' => $validated['volume'],
            'unit' => $validated['unit'],
            'unit_price' => $validated['unit_price'],
            'total_price' => $totalPrice,
            'notes' => $validated['notes'],
        ]);

        return back()->with('success', "Item RAB berhasil ditambahkan ke Menu '{$validated['menu_pekerjaan']}'!");
    }

    /**
     * Mengimpor berkas Excel / CSV RAB dan otomatis memetakan ke data RAB proyek.
     */
    public function import(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:revitalization_projects,id',
            'menu_pekerjaan' => 'nullable|string|max:150',
            'rab_file' => 'required|file|max:20480',
            'mode' => 'nullable|in:replace,append',
            'skip_zero_volume' => 'nullable|boolean',
        ], [
            'rab_file.required' => 'Pilih berkas file Excel/CSV RAB yang akan diimpor.',
        ]);

        $project = RevitalizationProject::findOrFail($request->project_id);
        $file = $request->file('rab_file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['xlsx', 'xls', 'csv', 'txt'])) {
            return back()->withInput()->with('error', 'Format berkas tidak didukung. Harap unggah berkas Excel (.xlsx, .xls) atau CSV (.csv).');
        }

        try {
            $mode = $request->input('mode', 'replace');
            $skipZero = $request->boolean('skip_zero_volume', true);
            $targetMenu = $request->input('menu_pekerjaan') ?: 'Rehabilitasi Ruang Kelas';

            $result = $this->rabImportService->importFromSpreadsheet($file, $project, [
                'mode' => $mode,
                'skip_zero_volume' => $skipZero,
                'target_menu_pekerjaan' => $targetMenu,
            ]);

            $totalFormatted = number_format($result['total_budget'], 0, ',', '.');
            $msg = "Alhamdulillah! Berhasil mengimpor {$result['count']} item pos belanja RAB ke menu '{$targetMenu}' pada proyek '{$project->title}' dengan total pagu Rp {$totalFormatted}.";
            if ($result['deleted_previous'] > 0) {
                $msg .= " ({$result['deleted_previous']} item lama pada menu ini berhasil digantikan).";
            }

            return redirect()->route('rab.index', [
                'project_id' => $project->id,
                'menu_pekerjaan' => $targetMenu,
            ])->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses berkas RAB: ' . $e->getMessage());
        }
    }

    /**
     * Mengunduh Template Resmi Excel RAB untuk acuan pengisian data pos belanja.
     */
    public function template(): StreamedResponse
    {
        $spreadsheet = $this->rabImportService->generateTemplateSpreadsheet();
        $fileName = 'Template_RAB_Pos_Belanja_Revitalisasi_' . date('Ymd') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function update(Request $request, BudgetItem $budgetItem)
    {
        $validated = $request->validate([
            'menu_pekerjaan' => 'nullable|string|max:150',
            'code' => 'nullable|string|max:50',
            'category' => 'required|string',
            'name' => 'required|string|max:255',
            'volume' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $totalPrice = (float) $validated['volume'] * (float) $validated['unit_price'];

        $updateData = [
            'code' => $validated['code'],
            'category' => $validated['category'],
            'name' => $validated['name'],
            'volume' => $validated['volume'],
            'unit' => $validated['unit'],
            'unit_price' => $validated['unit_price'],
            'total_price' => $totalPrice,
            'notes' => $validated['notes'],
        ];

        if (!empty($validated['menu_pekerjaan'])) {
            $updateData['menu_pekerjaan'] = $validated['menu_pekerjaan'];
        }

        $budgetItem->update($updateData);

        return back()->with('success', 'Item RAB berhasil diperbarui!');
    }

    public function destroy(BudgetItem $budgetItem)
    {
        $budgetItem->delete();
        return back()->with('success', 'Item RAB berhasil dihapus!');
    }
}

