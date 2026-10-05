<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function index(Request $request)
    {
        $query = School::withCount(['projects', 'transactions']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'like', "%{$s}%")
                ->orWhere('npsn', 'like', "%{$s}%")
                ->orWhere('kecamatan', 'like', "%{$s}%");
        }

        if ($request->filled('jenjang')) {
            $query->where('jenjang', $request->jenjang);
        }

        $schools = $query->orderBy('name')->paginate(12)->withQueryString();
        return view('schools.index', compact('schools'));
    }

    public function create()
    {
        return view('schools.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'npsn' => 'required|string|unique:schools,npsn',
            'name' => 'required|string|max:255',
            'jenjang' => 'required|in:SD,SMP,SMA,SMK',
            'status' => 'required|in:Negeri,Swasta',
            'address' => 'required|string',
            'kecamatan' => 'required|string',
            'kabupaten' => 'required|string',
            'principal_name' => 'required|string|max:255',
            'principal_nip' => 'nullable|string|max:50',
            'treasurer_name' => 'required|string|max:255',
            'treasurer_nip' => 'nullable|string|max:50',
            'bank_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:100',
            'bank_account_holder' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
        ]);

        School::create($validated);
        return redirect()->route('schools.index')->with('success', 'Data Sekolah berhasil ditambahkan!');
    }

    public function edit(School $school)
    {
        return view('schools.edit', compact('school'));
    }

    public function update(Request $request, School $school)
    {
        $validated = $request->validate([
            'npsn' => 'required|string|unique:schools,npsn,' . $school->id,
            'name' => 'required|string|max:255',
            'jenjang' => 'required|in:SD,SMP,SMA,SMK',
            'status' => 'required|in:Negeri,Swasta',
            'address' => 'required|string',
            'kecamatan' => 'required|string',
            'kabupaten' => 'required|string',
            'principal_name' => 'required|string|max:255',
            'principal_nip' => 'nullable|string|max:50',
            'treasurer_name' => 'required|string|max:255',
            'treasurer_nip' => 'nullable|string|max:50',
            'bank_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:100',
            'bank_account_holder' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
        ]);

        $school->update($validated);
        return redirect()->route('schools.index')->with('success', 'Data Sekolah berhasil diperbarui!');
    }

    public function destroy(School $school)
    {
        $school->delete();
        return redirect()->route('schools.index')->with('success', 'Data Sekolah berhasil dihapus!');
    }
}

