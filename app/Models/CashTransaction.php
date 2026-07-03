<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cash_account_id',
        'type',
        'amount',
        'description',
        'pic',
        'is_marked',
        'date',
        'created_by',
    ];

    protected $casts = [
        'date'      => 'date',
        'amount'    => 'float',
        'is_marked' => 'boolean',
    ];


    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
