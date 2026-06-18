<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use SoftDeletes;

    protected $appends = ['number', 'document_type', 'terbilang'];

    protected $fillable = [
        'company_id',
        'partner_id',
        'type',
        'number',
        'document_number',
        'date',
        'due_date',
        'status',
        'terms',
        'notes',
        'subtotal',
        'discount',
        'tax',
        'grand_total',
        'stock_conditions',
        'term_of_payment',
        'price_conditions',
        'standard_packing',
        'offer_validity',
        'sender_name',
        'sender_phone',
        'sender_address',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_pic',
        'bank_account_id',
        'is_ppn',
        'dp_percent',
        'dp_amount',
        'payment_type',
        'vendor_bank_name',
        'vendor_bank_account_name',
        'vendor_bank_account_number',
        'reference_id',
        'customer_po_number',
        'customer_po_date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'customer_po_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'is_ppn' => 'boolean',
            'dp_percent' => 'decimal:2',
            'dp_amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'bank_account_id');
    }

    public function reference(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'reference_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'confirmed' => 'Confirmed',
            'canceled' => 'Canceled',
            default => ucfirst($this->status),
        };
    }

    public function getNumberAttribute(): ?string
    {
        return $this->document_number;
    }

    public function setNumberAttribute(?string $value): void
    {
        $this->document_number = $value;
    }

    public function getDocumentTypeAttribute(): string
    {
        return match ($this->type) {
            'quotation' => 'Quotation',
            'proforma_invoice' => 'Proforma Invoice',
            'invoice' => 'Invoice',
            'delivery_slip' => 'Delivery Slip',
            'delivery_address' => 'Delivery Address',
            'purchase_order' => 'Purchase Order',
            default => ucfirst($this->type),
        };
    }

    public function getTerbilangAttribute(): string
    {
        return $this->spellNumber((float) $this->grand_total);
    }

    private function terbilang(float $number): string
    {
        $number = abs($number);
        $words = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
        $temp = "";
        if ($number < 12) {
            $temp = " " . $words[(int)$number];
        } else if ($number < 20) {
            $temp = $this->terbilang($number - 10) . " belas";
        } else if ($number < 100) {
            $temp = $this->terbilang((int)($number / 10)) . " puluh" . $this->terbilang($number % 10);
        } else if ($number < 200) {
            $temp = " seratus" . $this->terbilang($number - 100);
        } else if ($number < 1000) {
            $temp = $this->terbilang((int)($number / 100)) . " ratus" . $this->terbilang($number % 100);
        } else if ($number < 2000) {
            $temp = " seribu" . $this->terbilang($number - 1000);
        } else if ($number < 1000000) {
            $temp = $this->terbilang((int)($number / 1000)) . " ribu" . $this->terbilang($number % 1000);
        } else if ($number < 1000000000) {
            $temp = $this->terbilang((int)($number / 1000000)) . " juta" . $this->terbilang($number % 1000000);
        } else if ($number < 1000000000000) {
            $temp = $this->terbilang((int)($number / 1000000000)) . " milyar" . $this->terbilang(fmod($number, 1000000000));
        } else if ($number < 1000000000000000) {
            $temp = $this->terbilang((int)($number / 1000000000000)) . " trilyun" . $this->terbilang(fmod($number, 1000000000000));
        }
        return $temp;
    }

    private function spellNumber(float $number): string
    {
        if ($number == 0) {
            return "Nol Rupiah";
        }
        $integerPart = (int) $number;
        $spelled = trim($this->terbilang($integerPart));
        return ucwords($spelled) . " Rupiah";
    }
}
