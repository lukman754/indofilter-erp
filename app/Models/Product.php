<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\DocumentItem;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'uom',
        'price',
        'is_active',
        'variations',
    ];

    protected $casts = [
        'variations' => 'array',
    ];

    protected $appends = ['document_items'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getDocumentItemsAttribute()
    {
        return DocumentItem::whereRaw('LOWER(product_name) = ?', [strtolower($this->name)])
            ->whereHas('document', function ($query) {
                $query->where('company_id', $this->company_id);
            })
            ->with('document.partner')
            ->get();
    }
}
