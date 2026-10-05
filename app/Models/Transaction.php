<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'project_id',
        'budget_item_id',
        'transaction_number',
        'transaction_date',
        'type',
        'payment_method',
        'description',
        'recipient_name',
        'recipient_address',
        'amount',
        'has_tax',
        'tax_type',
        'tax_ppn',
        'tax_pph21',
        'tax_pph22',
        'tax_pph23',
        'tax_pph4_2',
        'tax_total',
        'net_amount',
        'tax_ntpn',
        'tax_payment_date',
        'tax_status',
        'receipt_file',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'tax_payment_date' => 'date',
        'amount' => 'decimal:2',
        'tax_ppn' => 'decimal:2',
        'tax_pph21' => 'decimal:2',
        'tax_pph22' => 'decimal:2',
        'tax_pph23' => 'decimal:2',
        'tax_pph4_2' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'has_tax' => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function project()
    {
        return $this->belongsTo(RevitalizationProject::class, 'project_id');
    }

    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class, 'budget_item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Terbilang Rupiah Helper
    public function getTerbilangAttribute()
    {
        return self::penyebut($this->amount) . ' Rupiah';
    }

    public static function penyebut($nilai)
    {
        $nilai = abs((float)$nilai);
        $huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
        $temp = "";

        if ($nilai < 12) {
            $temp = " " . $huruf[$nilai];
        } else if ($nilai < 20) {
            $temp = self::penyebut($nilai - 10) . " Belas";
        } else if ($nilai < 100) {
            $temp = self::penyebut((int)($nilai / 10)) . " Puluh" . self::penyebut($nilai % 10);
        } else if ($nilai < 200) {
            $temp = " Seratus" . self::penyebut($nilai - 100);
        } else if ($nilai < 1000) {
            $temp = self::penyebut((int)($nilai / 100)) . " Ratus" . self::penyebut($nilai % 100);
        } else if ($nilai < 2000) {
            $temp = " Seribu" . self::penyebut($nilai - 1000);
        } else if ($nilai < 1000000) {
            $temp = self::penyebut((int)($nilai / 1000)) . " Ribu" . self::penyebut($nilai % 1000);
        } else if ($nilai < 1000000000) {
            $temp = self::penyebut((int)($nilai / 1000000)) . " Juta" . self::penyebut($nilai % 1000000);
        } else if ($nilai < 1000000000000) {
            $temp = self::penyebut((int)($nilai / 1000000000)) . " Milyar" . self::penyebut(fmod($nilai, 1000000000));
        } else if ($nilai < 1000000000000000) {
            $temp = self::penyebut((int)($nilai / 1000000000000)) . " Triliun" . self::penyebut(fmod($nilai, 1000000000000));
        }

        return trim($temp);
    }
}

