<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevitalizationProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'fiscal_year',
        'title',
        'funding_source',
        'spk_number',
        'spk_date',
        'contract_amount',
        'start_date',
        'end_date',
        'status',
        'physical_progress',
        'description',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'contract_amount' => 'decimal:2',
        'physical_progress' => 'decimal:2',
        'spk_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function budgetItems()
    {
        return $this->hasMany(BudgetItem::class, 'project_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'project_id');
    }

    // Total Realisasi Pengeluaran (Belanja Kas + Belanja Transfer)
    public function getTotalRealizationAttribute()
    {
        return $this->transactions()
            ->whereIn('type', ['belanja_tunai', 'belanja_transfer', 'biaya_bank'])
            ->sum('amount');
    }

    // Persentase Realisasi Keuangan (%)
    public function getFinancialProgressAttribute()
    {
        if ($this->contract_amount <= 0) {
            return 0;
        }
        return round(($this->total_realization / $this->contract_amount) * 100, 2);
    }

    // Sisa Anggaran (Pagu - Realisasi)
    public function getRemainingBudgetAttribute()
    {
        return $this->contract_amount - $this->total_realization;
    }
}

