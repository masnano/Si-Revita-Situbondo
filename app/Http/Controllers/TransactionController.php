<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use App\Models\RevitalizationProject;
use App\Models\School;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $schools = School::orderBy('name')->get();
        $selectedSchoolId = $user->school_id && !$user->isRoot() ? $user->school_id : $request->get('school_id');

        $query = Transaction::query()->with(['school', 'project', 'budgetItem']);

        if ($selectedSchoolId) {
            $query->where('school_id', $selectedSchoolId);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('tax_status')) {
            $query->where('tax_status', $request->tax_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('tax_ntpn', 'like', "%{$search}%");
            });
        }

        $transactions = $query->latest('transaction_date')->paginate(15)->withQueryString();

        return view('transactions.index', compact('transactions', 'schools', 'selectedSchoolId'));
    }

    public function create()
    {
        $user = auth()->user();
        $schools = School::orderBy('name')->get();
        $selectedSchoolId = $user->school_id && !$user->isRoot() ? $user->school_id : $schools->first()?->id;

        $projects = RevitalizationProject::where('school_id', $selectedSchoolId)->get();
        $budgetItems = BudgetItem::whereHas('project', function ($q) use ($selectedSchoolId) {
            $q->where('school_id', $selectedSchoolId);
        })->get();

        $year = date('Y');
        $count = Transaction::whereYear('transaction_date', $year)->count() + 1;
        $autoNumber = sprintf("BKU-%s/%03d", $year, $count);

        return view('transactions.create', compact('schools', 'selectedSchoolId', 'projects', 'budgetItems', 'autoNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'project_id' => 'required|exists:revitalization_projects,id',
            'budget_item_id' => 'nullable|exists:budget_items,id',
            'transaction_number' => 'required|string|unique:transactions,transaction_number',
            'transaction_date' => 'required|date',
            'type' => 'required|string|in:penerimaan_dana,tarik_tunai,setor_tunai,belanja_tunai,belanja_transfer,bunga_bank,biaya_bank',
            'payment_method' => 'required|in:kas_tunai,bank_transfer',
            'description' => 'required|string',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_address' => 'nullable|string',
            'amount' => 'required|numeric|min:1',
            'has_tax' => 'nullable|boolean',
            'tax_type' => 'nullable|string',
            'tax_ppn' => 'nullable|numeric|min:0',
            'tax_pph21' => 'nullable|numeric|min:0',
            'tax_pph22' => 'nullable|numeric|min:0',
            'tax_pph23' => 'nullable|numeric|min:0',
            'tax_pph4_2' => 'nullable|numeric|min:0',
            'tax_ntpn' => 'nullable|string|max:100',
            'tax_payment_date' => 'nullable|date',
            'tax_status' => 'nullable|in:none,dipungut,disetor',
            'notes' => 'nullable|string',
            'receipt_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $amount = (float) $validated['amount'];
        $hasTax = $request->boolean('has_tax');

        $taxPpn = (float) ($request->tax_ppn ?? 0);
        $taxPph21 = (float) ($request->tax_pph21 ?? 0);
        $taxPph22 = (float) ($request->tax_pph22 ?? 0);
        $taxPph23 = (float) ($request->tax_pph23 ?? 0);
        $taxPph4_2 = (float) ($request->tax_pph4_2 ?? 0);
        $taxTotal = $hasTax ? ($taxPpn + $taxPph21 + $taxPph22 + $taxPph23 + $taxPph4_2) : 0;
        $netAmount = max(0, $amount - $taxTotal);

        $taxStatus = 'none';
        if ($hasTax) {
            $taxStatus = !empty($request->tax_ntpn) ? 'disetor' : 'dipungut';
        }

        $receiptPath = null;
        if ($request->hasFile('receipt_file')) {
            $receiptPath = $request->file('receipt_file')->store('receipts', 'public');
        }

        Transaction::create([
            'school_id' => $validated['school_id'],
            'project_id' => $validated['project_id'],
            'budget_item_id' => $validated['budget_item_id'] ?? null,
            'transaction_number' => $validated['transaction_number'],
            'transaction_date' => $validated['transaction_date'],
            'type' => $validated['type'],
            'payment_method' => $validated['payment_method'],
            'description' => $validated['description'],
            'recipient_name' => $validated['recipient_name'] ?? null,
            'recipient_address' => $validated['recipient_address'] ?? null,
            'amount' => $amount,
            'has_tax' => $hasTax,
            'tax_type' => $request->tax_type,
            'tax_ppn' => $taxPpn,
            'tax_pph21' => $taxPph21,
            'tax_pph22' => $taxPph22,
            'tax_pph23' => $taxPph23,
            'tax_pph4_2' => $taxPph4_2,
            'tax_total' => $taxTotal,
            'net_amount' => $netAmount,
            'tax_ntpn' => $request->tax_ntpn,
            'tax_payment_date' => $request->tax_payment_date,
            'tax_status' => $taxStatus,
            'receipt_file' => $receiptPath,
            'notes' => $request->notes,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil disimpan!');
    }

    public function edit(Transaction $transaction)
    {
        $schools = School::orderBy('name')->get();
        $projects = RevitalizationProject::where('school_id', $transaction->school_id)->get();
        $budgetItems = BudgetItem::where('project_id', $transaction->project_id)->get();

        return view('transactions.edit', compact('transaction', 'schools', 'projects', 'budgetItems'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'transaction_number' => 'required|string|unique:transactions,transaction_number,' . $transaction->id,
            'transaction_date' => 'required|date',
            'type' => 'required|string|in:penerimaan_dana,tarik_tunai,setor_tunai,belanja_tunai,belanja_transfer,bunga_bank,biaya_bank',
            'payment_method' => 'required|in:kas_tunai,bank_transfer',
            'description' => 'required|string',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_address' => 'nullable|string',
            'amount' => 'required|numeric|min:1',
            'has_tax' => 'nullable|boolean',
            'tax_type' => 'nullable|string',
            'tax_ppn' => 'nullable|numeric|min:0',
            'tax_pph21' => 'nullable|numeric|min:0',
            'tax_pph22' => 'nullable|numeric|min:0',
            'tax_pph23' => 'nullable|numeric|min:0',
            'tax_pph4_2' => 'nullable|numeric|min:0',
            'tax_ntpn' => 'nullable|string|max:100',
            'tax_payment_date' => 'nullable|date',
            'tax_status' => 'nullable|in:none,dipungut,disetor',
            'notes' => 'nullable|string',
            'receipt_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $amount = (float) $validated['amount'];
        $hasTax = $request->boolean('has_tax');

        $taxPpn = (float) ($request->tax_ppn ?? 0);
        $taxPph21 = (float) ($request->tax_pph21 ?? 0);
        $taxPph22 = (float) ($request->tax_pph22 ?? 0);
        $taxPph23 = (float) ($request->tax_pph23 ?? 0);
        $taxPph4_2 = (float) ($request->tax_pph4_2 ?? 0);
        $taxTotal = $hasTax ? ($taxPpn + $taxPph21 + $taxPph22 + $taxPph23 + $taxPph4_2) : 0;
        $netAmount = max(0, $amount - $taxTotal);

        $taxStatus = 'none';
        if ($hasTax) {
            $taxStatus = !empty($request->tax_ntpn) ? 'disetor' : 'dipungut';
        }

        $receiptPath = $transaction->receipt_file;
        if ($request->hasFile('receipt_file')) {
            if ($receiptPath) {
                Storage::disk('public')->delete($receiptPath);
            }
            $receiptPath = $request->file('receipt_file')->store('receipts', 'public');
        }

        $transaction->update([
            'transaction_number' => $validated['transaction_number'],
            'transaction_date' => $validated['transaction_date'],
            'type' => $validated['type'],
            'payment_method' => $validated['payment_method'],
            'description' => $validated['description'],
            'recipient_name' => $validated['recipient_name'] ?? null,
            'recipient_address' => $validated['recipient_address'] ?? null,
            'amount' => $amount,
            'has_tax' => $hasTax,
            'tax_type' => $request->tax_type,
            'tax_ppn' => $taxPpn,
            'tax_pph21' => $taxPph21,
            'tax_pph22' => $taxPph22,
            'tax_pph23' => $taxPph23,
            'tax_pph4_2' => $taxPph4_2,
            'tax_total' => $taxTotal,
            'net_amount' => $netAmount,
            'tax_ntpn' => $request->tax_ntpn,
            'tax_payment_date' => $request->tax_payment_date,
            'tax_status' => $taxStatus,
            'receipt_file' => $receiptPath,
            'notes' => $request->notes,
        ]);

        return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil diperbarui!');
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->receipt_file) {
            Storage::disk('public')->delete($transaction->receipt_file);
        }

        $transaction->delete();
        return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil dihapus!');
    }

    public function setorPajak(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'tax_ntpn' => 'required|string|max:100',
            'tax_payment_date' => 'required|date',
        ]);

        $transaction->update([
            'tax_ntpn' => $validated['tax_ntpn'],
            'tax_payment_date' => $validated['tax_payment_date'],
            'tax_status' => 'disetor',
        ]);

        return back()->with('success', "Penyetoran Pajak untuk transaksi {$transaction->transaction_number} berhasil dicatat dengan NTPN: {$validated['tax_ntpn']}");
    }
}
