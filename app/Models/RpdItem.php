<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RpdItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'rpd_document_id',
        'project_id',
        'budget_item_id',
        'transaction_id',
        'row_number',
        'item_code',
        'category',
        'item_name',
        'specification',
        'volume',
        'unit',
        'unit_price',
        'total_price',
        'planned_date',
        'supplier_name',
        'supplier_npwp',
        'payment_method',
        'bku_number',
        'bku_description',
        'has_tax',
        'tax_ppn',
        'tax_pph22',
        'tax_pph21',
        'tax_total',
        'net_amount',
        'is_posted',
        'notes',
    ];

    protected $casts = [
        'volume' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'planned_date' => 'date',
        'has_tax' => 'boolean',
        'tax_ppn' => 'decimal:2',
        'tax_pph22' => 'decimal:2',
        'tax_pph21' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'is_posted' => 'boolean',
    ];

    public function document()
    {
        return $this->belongsTo(RpdDocument::class, 'rpd_document_id');
    }

    public function project()
    {
        return $this->belongsTo(RevitalizationProject::class, 'project_id');
    }

    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class, 'budget_item_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function getCategoryBadgeAttribute(): string
    {
        return match ($this->category) {
            'bahan' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Bahan / Material</span>',
            'upah' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">Upah Tenaga</span>',
            'alat' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">Alat Kerja</span>',
            default => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300">Operasional</span>',
        };
    }

    public function getPaymentMethodBadgeAttribute(): string
    {
        return $this->payment_method === 'belanja_transfer'
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">Bank Jatim (BB)</span>'
            : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300">Kas Tunai (BPK)</span>';
    }
}

