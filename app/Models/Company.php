<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'alias',
        'logo',
        'address',
        'phone',
        'email',
        'npwp',
        'po_masuk_path',
        'is_active',
    ];

    protected $with = ['phones', 'bankAccounts'];

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(CompanyPhone::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CompanyBankAccount::class);
    }
}
