<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'npsn',
        'name',
        'jenjang',
        'status',
        'address',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'postal_code',
        'principal_name',
        'principal_nip',
        'treasurer_name',
        'treasurer_nip',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'phone',
        'email',
        'logo',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function projects()
    {
        return $this->hasMany(RevitalizationProject::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}

