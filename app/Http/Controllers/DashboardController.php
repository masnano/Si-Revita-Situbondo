<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use App\Models\RevitalizationProject;
use App\Models\School;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $selectedSchoolId = $request->get('school_id');

        if ($user->school_id && !$user->isRoot()) {
            $selectedSchoolId = $user->school_id;
        }

        $schoolsQuery = School::query();
        $projectsQuery = RevitalizationProject::query()->with('school');
        $transactionsQuery = Transaction::query()->with(['school', 'project', 'budgetItem']);

        if ($selectedSchoolId) {
            $projectsQuery->where('school_id', $selectedSchoolId);
            $transactionsQuery->where('school_id', $selectedSchoolId);
        }

        $totalSchools = School::count();
        $totalProjects = $projectsQuery->count();
        $totalBudget = (clone $projectsQuery)->sum('contract_amount');

        $totalRealization = (clone $transactionsQuery)
            ->whereIn('type', ['belanja_tunai', 'belanja_transfer', 'biaya_bank'])
            ->sum('amount');

        $remainingBudget = max(0, $totalBudget - $totalRealization);
        $financialPercentage = $totalBudget > 0 ? round(($totalRealization / $totalBudget) * 100, 2) : 0;

        $tarikTunai = (clone $transactionsQuery)->where('type', 'tarik_tunai')->sum('amount');
        $setorTunai = (clone $transactionsQuery)->where('type', 'setor_tunai')->sum('amount');
        $belanjaTunai = (clone $transactionsQuery)->where('type', 'belanja_tunai')->sum('amount');
        $saldoKasTunai = max(0, $tarikTunai - $setorTunai - $belanjaTunai);

        $penerimaanDana = (clone $transactionsQuery)->where('type', 'penerimaan_dana')->sum('amount');
        $bungaBank = (clone $transactionsQuery)->where('type', 'bunga_bank')->sum('amount');
        $belanjaTransfer = (clone $transactionsQuery)->where('type', 'belanja_transfer')->sum('amount');
        $biayaBank = (clone $transactionsQuery)->where('type', 'biaya_bank')->sum('amount');
        $saldoBank = max(0, ($penerimaanDana + $bungaBank + $setorTunai) - ($tarikTunai + $belanjaTransfer + $biayaBank));

        $pajakDipungut = (clone $transactionsQuery)->where('has_tax', true)->sum('tax_total');
        $pajakDisetor = (clone $transactionsQuery)->where('tax_status', 'disetor')->sum('tax_total');
        $pajakTerutang = max(0, $pajakDipungut - $pajakDisetor);

        $projects = $projectsQuery->with(['school', 'transactions'])->latest()->get();
        $recentTransactions = (clone $transactionsQuery)->latest('transaction_date')->take(6)->get();

        $budgetItems = BudgetItem::query()
            ->when($selectedSchoolId, function ($q) use ($selectedSchoolId) {
                $q->whereHas('project', function ($p) use ($selectedSchoolId) {
                    $p->where('school_id', $selectedSchoolId);
                });
            })
            ->selectRaw('category, SUM(total_price) as total_pagu')
            ->groupBy('category')
            ->get();

        $categoryLabels = $budgetItems->pluck('category')->toArray();
        $categoryData = $budgetItems->pluck('total_pagu')->toArray();

        $chartProjectLabels = [];
        $chartPhysicalProgress = [];
        $chartFinancialProgress = [];

        foreach ($projects as $proj) {
            $chartProjectLabels[] = $proj->school->name ?? $proj->title;
            $chartPhysicalProgress[] = (float) $proj->physical_progress;
            $chartFinancialProgress[] = (float) $proj->financial_progress;
        }

        $schools = School::orderBy('name')->get();

        return view('dashboard.index', compact(
            'totalSchools',
            'totalProjects',
            'totalBudget',
            'totalRealization',
            'remainingBudget',
            'financialPercentage',
            'saldoKasTunai',
            'saldoBank',
            'pajakDipungut',
            'pajakDisetor',
            'pajakTerutang',
            'projects',
            'recentTransactions',
            'categoryLabels',
            'categoryData',
            'chartProjectLabels',
            'chartPhysicalProgress',
            'chartFinancialProgress',
            'schools',
            'selectedSchoolId'
        ));
    }
}
