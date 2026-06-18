<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentItem extends Model
{
    protected $fillable = [
        'document_id',
        'product_name',
        'description',
        'qty',
        'uom',
        'unit_price',
        'total',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
