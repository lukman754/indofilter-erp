<?php
 
namespace App\Services;
 
use App\Models\Document;
use Illuminate\Support\Facades\DB;
 
class DocumentNumberService
{
    protected array $prefixes = [
        'quotation' => 'QUOT',
        'proforma_invoice' => 'PI',
        'invoice' => 'INV',
        'delivery_slip' => 'DS',
        'delivery_address' => 'RESI',
        'purchase_order' => 'PO',
    ];
 
    public function generate(Document $document): string
    {
        $year = $document->date ? $document->date->format('Y') : now()->format('Y');
        $monthNum = $document->date ? (int) $document->date->format('m') : (int) now()->format('m');
        $romanMonth = $this->getRomanMonth($monthNum);
 
        // 1. Sequence (xxxx)
        $lastNumber = Document::withTrashed()
            ->where('company_id', $document->company_id)
            ->where('type', $document->type)
            ->whereNotNull('document_number')
            ->lockForUpdate()
            ->orderBy('id', 'desc')
            ->value('document_number');
 
        $sequence = 1001; // Start at 1001 for a clean 4-digit sequence
        if ($lastNumber) {
            $parts = explode('-', $lastNumber);
            $firstPart = $parts[0] ?? '';
            if (is_numeric($firstPart)) {
                $sequence = (int) $firstPart + 1;
            } else {
                $lastPart = end($parts);
                if (is_numeric($lastPart)) {
                    $sequence = (int) $lastPart + 1;
                } else {
                    $sequence = 1001;
                }
            }
        }
 
        // 2. Company Alias
        $company = $document->company ?? \App\Models\Company::find($document->company_id);
        $companyAlias = 'COMP';
        if ($company) {
            $companyAlias = $company->alias;
            if (empty($companyAlias)) {
                $cleanName = preg_replace('/^(PT|CV|UD)\.?\s+/i', '', $company->name);
                $words = array_filter(explode(' ', $cleanName));
                $initials = '';
                foreach ($words as $w) {
                    $initials .= strtoupper($w[0] ?? '');
                }
                $companyAlias = $initials ?: 'COMP';
            }
        }
        $companyAlias = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $companyAlias));
 
        // 3. Document Type Code
        $typeCode = $this->prefixes[$document->type] ?? 'DOC';
 
        // 4. Partner Alias
        $partner = $document->partner ?? \App\Models\Partner::find($document->partner_id);
        $partnerAlias = 'PART';
        if ($partner) {
            if (!empty($partner->alias)) {
                $partnerAlias = $partner->alias;
            } else {
                $cleanName = preg_replace('/^(PT|CV|UD|Toko)\.?\s+/i', '', $partner->name);
                $words = array_filter(explode(' ', $cleanName));
                $initials = '';
                foreach ($words as $w) {
                    $initials .= strtoupper($w[0] ?? '');
                }
                $partnerAlias = $initials ?: 'PART';
            }
        }
        $partnerAlias = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $partnerAlias));
 
        // 5. Product Code
        $productCode = 'BARANG';
        if (isset($document->temp_product_code) && !empty($document->temp_product_code)) {
            $productCode = $document->temp_product_code;
        } elseif ($document->reference_id) {
            $refDoc = $document->reference;
            if ($refDoc && $refDoc->document_number) {
                $refParts = explode('-', $refDoc->document_number);
                if (!empty($refParts[5])) {
                    $productCode = $refParts[5];
                }
            }
        }
        if ($productCode === 'BARANG') {
            $items = $document->items;
            if (!$items || $items->isEmpty()) {
                if ($document->exists) {
                    $items = $document->items()->get();
                }
            }
            if ($items && $items->isNotEmpty()) {
                $firstItem = $items->first();
                $product = \App\Models\Product::where('company_id', $document->company_id)
                    ->where('name', $firstItem->product_name)
                    ->first();
                if ($product) {
                    $productCode = $product->code;
                } else {
                    $clean = preg_replace('/[^a-zA-Z0-9\s-]/', '', $firstItem->product_name);
                    $parts = array_filter(explode(' ', $clean));
                    $productCode = strtoupper(end($parts) ?: 'BARANG');
                }
            }
        }
        $productCode = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $productCode));
 
        return sprintf('%04d-%s-%s-%s-%s-%s-%s', $sequence, $companyAlias, $romanMonth, $typeCode, $partnerAlias, $productCode, $year);
    }
 
    private function getRomanMonth(int $month): string
    {
        $romans = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $romans[$month] ?? 'I';
    }
}
