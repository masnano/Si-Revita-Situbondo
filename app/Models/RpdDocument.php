<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RpdDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'project_id',
        'title',
        'term_stage',
        'file_path',
        'file_name',
        'file_type',
        'total_budget',
        'total_materials',
        'total_wages',
        'total_equipment',
        'total_operational',
        'total_tax_estimated',
        'total_net_estimated',
        'total_items_count',
        'status',
        'posted_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'total_budget' => 'decimal:2',
        'total_materials' => 'decimal:2',
        'total_wages' => 'decimal:2',
        'total_equipment' => 'decimal:2',
        'total_operational' => 'decimal:2',
        'total_tax_estimated' => 'decimal:2',
        'total_net_estimated' => 'decimal:2',
        'total_items_count' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function project()
    {
        return $this->belongsTo(RevitalizationProject::class, 'project_id');
    }

    public function items()
    {
        return $this->hasMany(RpdItem::class, 'rpd_document_id')->orderBy('row_number', 'asc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'posted_to_bku' => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Terposting ke BKU</span>',
            'analyzed' => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-300 dark:border-blue-700"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>Siap Posting (Dianalisis)</span>',
            default => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Draft Pecah Bahan</span>',
        };
    }
}

