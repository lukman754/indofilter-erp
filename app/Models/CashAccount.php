<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAccount extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'company_id'];

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    /**
     * Current balance = total in - total out (non-deleted transactions)
     */
    public function getBalanceAttribute(): float
    {
        $in  = $this->transactions()->where('type', 'in')->sum('amount');
        $out = $this->transactions()->where('type', 'out')->sum('amount');
        return (float) ($in - $out);
    }
}
