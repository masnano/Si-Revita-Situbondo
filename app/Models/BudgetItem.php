<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'code',
        'category',
        'name',
        'volume',
        'unit',
        'unit_price',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'volume' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(RevitalizationProject::class, 'project_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'budget_item_id');
    }

    public function getRealizedAmountAttribute()
    {
        return $this->transactions()
            ->whereIn('type', ['belanja_tunai', 'belanja_transfer'])
            ->sum('amount');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->total_price - $this->realized_amount;
    }
}

