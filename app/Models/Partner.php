<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'alias',
        'address',
        'phone',
        'email',
        'npwp',
        'contact_person',
        'is_active',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
