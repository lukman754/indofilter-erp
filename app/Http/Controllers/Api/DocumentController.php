<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Document;
use App\Services\DocumentNumberService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentNumberService $documentNumberService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Document::with(['partner', 'items', 'reference']);

        if ($request->filled('type')) {
            $type = strtolower(str_replace(' ', '_', $request->type));
            $query->where('type', $type);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function store(Request $request): JsonResponse
    {
        // Treat empty strings as null for nullable fields
        $request->merge([
            'number'         => $request->input('number') ?: null,
            'due_date'       => $request->input('due_date') ?: null,
            'reference_id'   => $request->input('reference_id') ?: null,
            'bank_account_id'=> $request->input('bank_account_id') ?: null,
            'customer_po_number' => $request->input('customer_po_number') ?: null,
            'customer_po_date' => $request->input('customer_po_date') ?: null,
        ]);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'partner_id' => 'required|exists:partners,id',
            'reference_id' => 'nullable|exists:documents,id',
            'type' => 'required|in:quotation,proforma_invoice,invoice,delivery_slip,delivery_address,purchase_order',
            'number' => 'nullable|string',
            'date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:date',
            'status' => 'sometimes|in:draft,confirmed,canceled',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'stock_conditions' => 'nullable|string',
            'term_of_payment' => 'nullable|string',
            'price_conditions' => 'nullable|string',
            'standard_packing' => 'nullable|string',
            'offer_validity' => 'nullable|string',
            'sender_name' => 'nullable|string|max:255',
            'sender_phone' => 'nullable|string|max:50',
            'sender_address' => 'nullable|string',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:50',
            'recipient_address' => 'nullable|string',
            'recipient_pic' => 'nullable|string|max:255',
            'subtotal' => 'sometimes|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
            'tax' => 'sometimes|numeric|min:0',
            'grand_total' => 'sometimes|numeric|min:0',
            'bank_account_id' => 'nullable|exists:company_bank_accounts,id',
            'is_ppn' => 'sometimes|boolean',
            'dp_percent' => 'nullable|numeric|min:0|max:100',
            'dp_amount' => 'nullable|numeric|min:0',
            'payment_type' => 'sometimes|string|in:full,dp,pelunasan',
            'vendor_bank_name' => 'nullable|string|max:255',
            'vendor_bank_account_name' => 'nullable|string|max:255',
            'vendor_bank_account_number' => 'nullable|string|max:255',
            'customer_po_number' => 'nullable|string|max:255',
            'customer_po_date' => 'nullable|date',
            'items' => 'sometimes|array',
            'items.*.product_name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'required_with:items|numeric|min:0',
            'items.*.uom' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.total' => 'required_with:items|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $items = $validated['items'] ?? [];
            unset($validated['items'], $validated['number']);

            $validated['subtotal'] = $validated['subtotal'] ?? 0;
            $validated['discount'] = $validated['discount'] ?? 0;
            $validated['tax'] = $validated['tax'] ?? 0;
            $validated['grand_total'] = $validated['grand_total'] ?? 0;
            $validated['dp_percent'] = $validated['dp_percent'] ?? 0;
            $validated['dp_amount'] = $validated['dp_amount'] ?? 0;
            $validated['payment_type'] = $validated['payment_type'] ?? 'full';

            $document = Document::create($validated);

            $document->document_number = $this->documentNumberService->generate($document);
            $document->save();

            foreach ($items as $item) {
                $document->items()->create($item);
                $this->syncProductFromItem($item, $document->company_id);
            }

            $document->load(['partner', 'items', 'reference']);

            return response()->json($document, 201);
        });
    }

    public function show(Document $document): JsonResponse
    {
        $document->load(['partner', 'items', 'company', 'bankAccount', 'reference']);

        $storageService = new SupabaseStorageService();
        $data = $document->toArray();
        $data['supabase_enabled'] = $storageService->isEnabled();

        return response()->json($data);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        // Treat empty strings as null for nullable fields
        $request->merge([
            'number'         => $request->input('number') ?: null,
            'due_date'       => $request->input('due_date') ?: null,
            'reference_id'   => $request->input('reference_id') ?: null,
            'bank_account_id'=> $request->input('bank_account_id') ?: null,
            'customer_po_number' => $request->input('customer_po_number') ?: null,
            'customer_po_date' => $request->input('customer_po_date') ?: null,
        ]);

        $validated = $request->validate([
            'company_id' => 'sometimes|exists:companies,id',
            'partner_id' => 'sometimes|exists:partners,id',
            'reference_id' => 'nullable|exists:documents,id',
            'type' => 'sometimes|in:quotation,proforma_invoice,invoice,delivery_slip,delivery_address,purchase_order',
            'number' => 'nullable|string',
            'date' => 'sometimes|date',
            'due_date' => 'nullable|date|after_or_equal:date',
            'status' => 'sometimes|in:draft,confirmed,canceled',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'stock_conditions' => 'nullable|string',
            'term_of_payment' => 'nullable|string',
            'price_conditions' => 'nullable|string',
            'standard_packing' => 'nullable|string',
            'offer_validity' => 'nullable|string',
            'sender_name' => 'nullable|string|max:255',
            'sender_phone' => 'nullable|string|max:50',
            'sender_address' => 'nullable|string',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:50',
            'recipient_address' => 'nullable|string',
            'recipient_pic' => 'nullable|string|max:255',
            'subtotal' => 'sometimes|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
            'tax' => 'sometimes|numeric|min:0',
            'grand_total' => 'sometimes|numeric|min:0',
            'bank_account_id' => 'nullable|exists:company_bank_accounts,id',
            'is_ppn' => 'sometimes|boolean',
            'dp_percent' => 'nullable|numeric|min:0|max:100',
            'dp_amount' => 'nullable|numeric|min:0',
            'payment_type' => 'sometimes|string|in:full,dp,pelunasan',
            'vendor_bank_name' => 'nullable|string|max:255',
            'vendor_bank_account_name' => 'nullable|string|max:255',
            'vendor_bank_account_number' => 'nullable|string|max:255',
            'customer_po_number' => 'nullable|string|max:255',
            'customer_po_date' => 'nullable|date',
            'items' => 'sometimes|array',
            'items.*.product_name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'required_with:items|numeric|min:0',
            'items.*.uom' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.total' => 'required_with:items|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $document) {
            $items = $validated['items'] ?? null;
            if (array_key_exists('number', $validated)) {
                $document->document_number = $validated['number'];
            }
            unset($validated['items'], $validated['number']);

            $document->update($validated);

            if ($items !== null) {
                $document->items()->delete();
                foreach ($items as $item) {
                    $document->items()->create($item);
                    $this->syncProductFromItem($item, $document->company_id);
                }
            }

            $document->load(['partner', 'items', 'reference']);

            return response()->json($document);
        });
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }

    public function confirm(Document $document): JsonResponse
    {
        if ($document->status !== 'draft') {
            return response()->json(['message' => 'Only draft documents can be confirmed.'], 422);
        }

        $document->update(['status' => 'confirmed']);

        $storageService = new SupabaseStorageService();
        if ($storageService->isEnabled()) {
            try {
                $this->syncSupabase($document, $storageService);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Auto-upload to Supabase on confirm failed: " . $e->getMessage());
            }
        }

        return response()->json($document);
    }

    public function cancel(Document $document): JsonResponse
    {
        if ($document->status === 'canceled') {
            return response()->json(['message' => 'Document is already canceled.'], 422);
        }

        $document->update(['status' => 'canceled']);

        return response()->json($document);
    }

    public function exportDocx($id): BinaryFileResponse|JsonResponse
    {
        $document = Document::with(['company', 'partner', 'items'])->findOrFail($id);

        $templatePath = base_path("templates/{$document->type}.docx");

        if (! file_exists($templatePath)) {
            return response()->json(['message' => "Template file not found for type: {$document->type}"], 404);
        }

        try {
            $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to load template: ' . $e->getMessage()], 500);
        }

        $company = $document->company;
        $partner = $document->partner;

        $templateProcessor->setValue('company_name', $document->sender_name ?: $company->name ?? '');
        $templateProcessor->setValue('company_address', $document->sender_address ?: $company->address ?? '');
        $templateProcessor->setValue('company_phone', $document->sender_phone ?: $company->phone ?? '');
        $templateProcessor->setValue('company_email', $company->email ?? '');
        $templateProcessor->setValue('company_npwp', $company->npwp ?? '');
        $bank = null;
        if ($document->bank_account_id) {
            $bank = \App\Models\CompanyBankAccount::find($document->bank_account_id);
        }
        if (!$bank) {
            $bank = $company->bankAccounts->where('is_default', true)->first();
        }
        if (!$bank) {
            $bank = $company->bankAccounts->first();
        }

        $isInvoiceOrProforma = in_array($document->type, ['proforma_invoice', 'invoice']);
        $templateProcessor->setValue('company_bank', ($bank && $isInvoiceOrProforma) ? $this->formatBankAccount($bank) : '');
        $templateProcessor->setValue('doc_number', $document->document_number ?? '');
        $templateProcessor->setValue('doc_date', $document->date ? $document->date->format('Y-m-d') : '');
        $templateProcessor->setValue('doc_due_date', $document->due_date ? $document->due_date->format('Y-m-d') : '');
        $templateProcessor->setValue('partner_name', $document->recipient_name ?: $partner->name ?? '');
        $templateProcessor->setValue('partner_address', $document->recipient_address ?: $partner->address ?? '');
        $templateProcessor->setValue('partner_contact', $partner->contact_person ?? '');
        $templateProcessor->setValue('partner_phone', $document->recipient_phone ?: $partner->phone ?? '');
        $templateProcessor->setValue('partner_pic', $document->recipient_pic ?: $partner->contact_person ?? '');
        $templateProcessor->setValue('partner_npwp', $partner->npwp ?? '');

        $templateProcessor->setValue('subtotal', number_format((float) $document->subtotal, 2, ',', '.'));
        $templateProcessor->setValue('discount', number_format((float) $document->discount, 2, ',', '.'));
        $templateProcessor->setValue('tax', number_format((float) $document->tax, 2, ',', '.'));
        $templateProcessor->setValue('grand_total', number_format((float) $document->grand_total, 2, ',', '.'));
        $templateProcessor->setValue('terbilang', $document->terbilang ?? '');
        $templateProcessor->setValue('dp_percent', number_format((float) ($document->dp_percent ?? 0), 2, ',', '.'));
        $templateProcessor->setValue('dp_amount', number_format((float) ($document->dp_amount ?? 0), 2, ',', '.'));
        $templateProcessor->setValue('remaining_amount', number_format((float) (($document->subtotal + $document->tax) - ($document->dp_amount ?? 0)), 2, ',', '.'));
        
        $paymentType = $document->payment_type ?? 'full';
        $paymentTypeLabel = 'Grand Total';
        if ($paymentType === 'dp') {
            $paymentTypeLabel = 'Uang Muka (DP) ' . number_format((float) $document->dp_percent, 0) . '%';
        } elseif ($paymentType === 'pelunasan') {
            $paymentTypeLabel = 'Pelunasan ' . number_format((float) $document->dp_percent, 0) . '%';
        }
        $templateProcessor->setValue('payment_type', $paymentType);
        $templateProcessor->setValue('payment_type_label', $paymentTypeLabel);
        $templateProcessor->setValue('bank_name', $isInvoiceOrProforma ? ($bank?->bank_name ?? '') : '');
        $templateProcessor->setValue('bank_account_name', $isInvoiceOrProforma ? ($bank?->account_name ?? '') : '');
        $vendorBankName = $document->vendor_bank_name;
        $vendorBankAccountName = $document->vendor_bank_account_name;
        $vendorBankAccountNumber = $document->vendor_bank_account_number;
        if (empty($vendorBankName) && $partner && $partner->type === 'vendor') {
            $vendorBankName = $partner->bank_name;
            $vendorBankAccountName = $partner->bank_account_name;
            $vendorBankAccountNumber = $partner->bank_account_number;
        }
        $templateProcessor->setValue('vendor_bank_name', $vendorBankName ?? '');
        $templateProcessor->setValue('vendor_bank_account_name', $vendorBankAccountName ?? '');
        $templateProcessor->setValue('vendor_bank_account_number', $vendorBankAccountNumber ?? '');

        $templateProcessor->setValue('terms', $document->terms ?? '');
        $templateProcessor->setValue('notes', $document->notes ?? '');
        $templateProcessor->setValue('customer_po_number', $document->customer_po_number ?? '');
        $templateProcessor->setValue('customer_po_date', $document->customer_po_date ? $document->customer_po_date->format('Y-m-d') : '');
        $templateProcessor->setValue('stock_conditions', $document->stock_conditions ?? '');
        $templateProcessor->setValue('term_of_payment', $document->term_of_payment ?? '');
        $templateProcessor->setValue('price_conditions', $document->price_conditions ?? '');
        $templateProcessor->setValue('standard_packing', $document->standard_packing ?? '');
        $templateProcessor->setValue('offer_validity', $document->offer_validity ?? '');
        $templateProcessor->setValue('signature_name', auth()->user()->name ?? '');
        $templateProcessor->setValue('delivery_date', '');
        $templateProcessor->setValue('courier', '');
        $templateProcessor->setValue('tracking_number', '');
        $templateProcessor->setValue('delivery_note', '');
        $templateProcessor->setValue('invoice_number', '');
        $templateProcessor->setValue('driver_name', '');
        $templateProcessor->setValue('receiver_name', '');
        $templateProcessor->setValue('prepared_by', '');
        $templateProcessor->setValue('checked_by', '');
        $templateProcessor->setValue('approved_by', '');
        $templateProcessor->setValue('shipping_address', '');
        $templateProcessor->setValue('shipping_method', '');

        $items = $document->items;

        $templateProcessor->cloneRow('product_name', $items->count());

        foreach ($items as $index => $item) {
            $row = $index + 1;
            $templateProcessor->setValue("product_name#{$row}", $item->product_name ?? '');
            $templateProcessor->setValue("description#{$row}", $item->description ?? '');
            $templateProcessor->setValue("qty#{$row}", number_format((float) $item->qty, 2, ',', '.'));
            $templateProcessor->setValue("uom#{$row}", $item->uom ?? '');
            $templateProcessor->setValue("unit_price#{$row}", number_format((float) $item->unit_price, 2, ',', '.'));
            $templateProcessor->setValue("total#{$row}", number_format((float) $item->total, 2, ',', '.'));
        }

        $fileName = str_replace(['/', '\\'], '-', $document->document_number) . '.docx';
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_');
        $templateProcessor->saveAs($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    private function formatBankAccount($bank): string
    {
        if (!$bank) return '';
        return "{$bank->bank_name} - {$bank->account_name} ({$bank->account_number})";
    }

    public function generateNumber(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:quotation,proforma_invoice,invoice,delivery_slip,delivery_address,purchase_order',
            'company_id' => 'required|exists:companies,id',
            'partner_id' => 'required|exists:partners,id',
            'date' => 'required|date',
            'product_name' => 'nullable|string',
            'product_code' => 'nullable|string',
        ]);

        $document = new Document([
            'type' => $request->type,
            'company_id' => $request->company_id,
            'partner_id' => $request->partner_id,
            'date' => \Carbon\Carbon::parse($request->date),
        ]);

        if ($request->filled('product_code')) {
            $document->temp_product_code = $request->product_code;
        }

        if ($request->filled('product_name')) {
            $document->setRelation('items', collect([
                new \App\Models\DocumentItem(['product_name' => $request->product_name])
            ]));
        }

        $number = $this->documentNumberService->generate($document);

        return response()->json(['number' => $number]);
    }

    private function syncProductFromItem(array $item, int $companyId): void
    {
        $productName = trim($item['product_name']);
        if (empty($productName)) {
            return;
        }

        $product = \App\Models\Product::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($productName)])
            ->first();

        $data = [
            'company_id' => $companyId,
            'name' => $productName,
            'description' => $item['description'] ?? null,
            'uom' => $item['uom'] ?? 'PCS',
            'price' => $item['unit_price'] ?? 0,
            'is_active' => true,
        ];

        if ($product) {
            $product->update($data);
        } else {
            $count = \App\Models\Product::where('company_id', $companyId)->count() + 1;
            $code = 'PRD-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            while (\App\Models\Product::where('company_id', $companyId)->where('code', $code)->exists()) {
                $count++;
                $code = 'PRD-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
            $data['code'] = $code;
            \App\Models\Product::create($data);
        }
    }

    public function syncSupabase(Document $document, ?SupabaseStorageService $storageService = null): JsonResponse
    {
        if ($storageService === null) {
            $storageService = new SupabaseStorageService();
        }

        if (!$storageService->isEnabled()) {
            return response()->json(['message' => 'Integrasi Supabase Storage belum diaktifkan atau dikonfigurasi.'], 400);
        }

        $templatePath = base_path("templates/{$document->type}.docx");

        if (!file_exists($templatePath)) {
            return response()->json(['message' => "Berkas template tidak ditemukan untuk tipe: {$document->type}"], 404);
        }

        try {
            $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
            
            $company = $document->company;
            $partner = $document->partner;

            $templateProcessor->setValue('company_name', $document->sender_name ?: $company->name ?? '');
            $templateProcessor->setValue('company_address', $document->sender_address ?: $company->address ?? '');
            $templateProcessor->setValue('company_phone', $document->sender_phone ?: $company->phone ?? '');
            $templateProcessor->setValue('company_email', $company->email ?? '');
            $templateProcessor->setValue('company_npwp', $company->npwp ?? '');
            
            $bank = null;
            if ($document->bank_account_id) {
                $bank = \App\Models\CompanyBankAccount::find($document->bank_account_id);
            }
            if (!$bank) {
                $bank = $company->bankAccounts->where('is_default', true)->first();
            }
            if (!$bank) {
                $bank = $company->bankAccounts->first();
            }

            $isInvoiceOrProforma = in_array($document->type, ['proforma_invoice', 'invoice']);
            $templateProcessor->setValue('company_bank', ($bank && $isInvoiceOrProforma) ? $this->formatBankAccount($bank) : '');
            $templateProcessor->setValue('doc_number', $document->document_number ?? '');
            $templateProcessor->setValue('doc_date', $document->date ? $document->date->format('Y-m-d') : '');
            $templateProcessor->setValue('doc_due_date', $document->due_date ? $document->due_date->format('Y-m-d') : '');
            $templateProcessor->setValue('partner_name', $document->recipient_name ?: $partner->name ?? '');
            $templateProcessor->setValue('partner_address', $document->recipient_address ?: $partner->address ?? '');
            $templateProcessor->setValue('partner_contact', $partner->contact_person ?? '');
            $templateProcessor->setValue('partner_phone', $document->recipient_phone ?: $partner->phone ?? '');
            $templateProcessor->setValue('partner_pic', $document->recipient_pic ?: $partner->contact_person ?? '');
            $templateProcessor->setValue('partner_npwp', $partner->npwp ?? '');

            $templateProcessor->setValue('subtotal', number_format((float) $document->subtotal, 2, ',', '.'));
            $templateProcessor->setValue('discount', number_format((float) $document->discount, 2, ',', '.'));
            $templateProcessor->setValue('tax', number_format((float) $document->tax, 2, ',', '.'));
            $templateProcessor->setValue('grand_total', number_format((float) $document->grand_total, 2, ',', '.'));
            $templateProcessor->setValue('terbilang', $document->terbilang ?? '');
            $templateProcessor->setValue('dp_percent', number_format((float) ($document->dp_percent ?? 0), 2, ',', '.'));
            $templateProcessor->setValue('dp_amount', number_format((float) ($document->dp_amount ?? 0), 2, ',', '.'));
            $templateProcessor->setValue('remaining_amount', number_format((float) (($document->subtotal + $document->tax) - ($document->dp_amount ?? 0)), 2, ',', '.'));
            
            $paymentType = $document->payment_type ?? 'full';
            $paymentTypeLabel = 'Grand Total';
            if ($paymentType === 'dp') {
                $paymentTypeLabel = 'Uang Muka (DP) ' . number_format((float) $document->dp_percent, 0) . '%';
            } elseif ($paymentType === 'pelunasan') {
                $paymentTypeLabel = 'Pelunasan ' . number_format((float) $document->dp_percent, 0) . '%';
            }
            $templateProcessor->setValue('payment_type', $paymentType);
            $templateProcessor->setValue('payment_type_label', $paymentTypeLabel);
            $templateProcessor->setValue('bank_name', $isInvoiceOrProforma ? ($bank?->bank_name ?? '') : '');
            $templateProcessor->setValue('bank_account_name', $isInvoiceOrProforma ? ($bank?->account_name ?? '') : '');
            
            $vendorBankName = $document->vendor_bank_name;
            $vendorBankAccountName = $document->vendor_bank_account_name;
            $vendorBankAccountNumber = $document->vendor_bank_account_number;
            if (empty($vendorBankName) && $partner && $partner->type === 'vendor') {
                $vendorBankName = $partner->bank_name;
                $vendorBankAccountName = $partner->bank_account_name;
                $vendorBankAccountNumber = $partner->bank_account_number;
            }
            $templateProcessor->setValue('vendor_bank_name', $vendorBankName ?? '');
            $templateProcessor->setValue('vendor_bank_account_name', $vendorBankAccountName ?? '');
            $templateProcessor->setValue('vendor_bank_account_number', $vendorBankAccountNumber ?? '');

            $templateProcessor->setValue('terms', $document->terms ?? '');
            $templateProcessor->setValue('notes', $document->notes ?? '');
            $templateProcessor->setValue('customer_po_number', $document->customer_po_number ?? '');
            $templateProcessor->setValue('customer_po_date', $document->customer_po_date ? $document->customer_po_date->format('Y-m-d') : '');
            $templateProcessor->setValue('stock_conditions', $document->stock_conditions ?? '');
            $templateProcessor->setValue('term_of_payment', $document->term_of_payment ?? '');
            $templateProcessor->setValue('price_conditions', $document->price_conditions ?? '');
            $templateProcessor->setValue('standard_packing', $document->standard_packing ?? '');
            $templateProcessor->setValue('offer_validity', $document->offer_validity ?? '');
            $templateProcessor->setValue('signature_name', auth()->user()->name ?? '');
            
            $templateProcessor->setValue('delivery_date', '');
            $templateProcessor->setValue('courier', '');
            $templateProcessor->setValue('tracking_number', '');
            $templateProcessor->setValue('delivery_note', '');
            $templateProcessor->setValue('invoice_number', '');
            $templateProcessor->setValue('driver_name', '');
            $templateProcessor->setValue('receiver_name', '');
            $templateProcessor->setValue('prepared_by', '');
            $templateProcessor->setValue('checked_by', '');
            $templateProcessor->setValue('approved_by', '');
            $templateProcessor->setValue('shipping_address', '');
            $templateProcessor->setValue('shipping_method', '');

            $items = $document->items;
            $templateProcessor->cloneRow('product_name', $items->count());

            foreach ($items as $index => $item) {
                $row = $index + 1;
                $templateProcessor->setValue("product_name#{$row}", $item->product_name ?? '');
                $templateProcessor->setValue("description#{$row}", $item->description ?? '');
                $templateProcessor->setValue("qty#{$row}", number_format((float) $item->qty, 2, ',', '.'));
                $templateProcessor->setValue("uom#{$row}", $item->uom ?? '');
                $templateProcessor->setValue("unit_price#{$row}", number_format((float) $item->unit_price, 2, ',', '.'));
                $templateProcessor->setValue("total#{$row}", number_format((float) $item->total, 2, ',', '.'));
            }

            $fileName = str_replace(['/', '\\'], '-', $document->document_number) . '.docx';
            $tempFile = tempnam(sys_get_temp_dir(), 'docx_');
            $templateProcessor->saveAs($tempFile);

            $fileUrl = $storageService->uploadDocument($tempFile, $fileName, $document->type, $document->company_id);

            $oldFileUrl = $document->cloud_file_url;

            $document->update([
                'cloud_file_url' => $fileUrl
            ]);

            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            if (!empty($oldFileUrl) && $oldFileUrl !== $fileUrl) {
                $storageService->deleteFile($oldFileUrl);
            }

            return response()->json([
                'success'        => true,
                'message'        => 'Dokumen berhasil disinkronkan ke Supabase Storage.',
                'cloud_file_url' => $fileUrl
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal sinkronisasi Supabase Storage: ' . $e->getMessage()
            ], 500);
        }
    }
}
