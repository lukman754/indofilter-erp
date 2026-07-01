<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Document;
use App\Models\Partner;
use App\Models\Setting;
use App\Services\DocumentNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        $overwrite = $request->boolean('overwrite');
        return DB::transaction(function () use ($validated, $overwrite, $request) {
            $items = $validated['items'] ?? [];
            $customNumber = $request->input('number');
            unset($validated['items'], $validated['number']);

            $validated['subtotal'] = $validated['subtotal'] ?? 0;
            $validated['discount'] = $validated['discount'] ?? 0;
            $validated['tax'] = $validated['tax'] ?? 0;
            $validated['grand_total'] = $validated['grand_total'] ?? 0;
            $validated['dp_percent'] = $validated['dp_percent'] ?? 0;
            $validated['dp_amount'] = $validated['dp_amount'] ?? 0;
            $validated['payment_type'] = $validated['payment_type'] ?? 'full';

            $document = Document::create($validated);

            if (!empty($customNumber)) {
                $document->document_number = $customNumber;
            } else {
                $document->document_number = $this->documentNumberService->generate($document);
            }
            $document->save();

            foreach ($items as $item) {
                $document->items()->create($item);
                $this->syncProductFromItem($item, $document->company_id);
            }

            $document->load(['partner', 'items', 'reference']);

            $this->autoSaveIfConfirmed($document, $overwrite);

            return response()->json($document, 201);
        });
    }

    public function show(Document $document): JsonResponse
    {
        $document->load(['partner', 'items', 'company', 'bankAccount', 'reference']);

        return response()->json($document);
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

        $overwrite = $request->boolean('overwrite');
        return DB::transaction(function () use ($validated, $document, $overwrite, $request) {
            $items = $validated['items'] ?? null;
            $customNumber = $request->input('number');
            unset($validated['items'], $validated['number']);

            $document->update($validated);

            if (!empty($customNumber)) {
                $document->document_number = $customNumber;
            } else if (empty($document->document_number)) {
                $document->document_number = $this->documentNumberService->generate($document);
            }
            $document->save();

            if ($items !== null) {
                $document->items()->delete();
                foreach ($items as $item) {
                    $document->items()->create($item);
                    $this->syncProductFromItem($item, $document->company_id);
                }
            }

            $document->load(['partner', 'items', 'reference']);

            $this->autoSaveIfConfirmed($document, $overwrite);

            return response()->json($document);
        });
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }

    public function confirm(Document $document, Request $request): JsonResponse
    {
        if ($document->status !== 'draft') {
            return response()->json(['message' => 'Only draft documents can be confirmed.'], 422);
        }

        $document->update(['status' => 'confirmed']);
        $this->autoSaveIfConfirmed($document, $request->boolean('overwrite'));

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

    public function exportDocx(Request $request, $id): JsonResponse
    {
        try {
            $document = Document::with(['company', 'partner', 'items'])->findOrFail($id);
            $overwrite = $request->boolean('overwrite');
            
            $storagePath = $this->getStoragePathForDocument($document);
            if (empty($storagePath)) {
                return response()->json([
                    'message' => 'Path penyimpanan lokal belum dikonfigurasi di Pengaturan Aplikasi.'
                ], 422);
            }

            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $fileName = str_replace(['/', '\\'], '-', $document->document_number) . '.docx';
            $fullPath = rtrim($storagePath, '/\\') . DIRECTORY_SEPARATOR . $fileName;

            if (file_exists($fullPath) && !$overwrite) {
                return response()->json([
                    'exists' => true,
                    'filename' => $fileName,
                    'message' => 'File already exists.'
                ]);
            }

            $templateProcessor = $this->fillTemplate($document);
            $templateProcessor->saveAs($fullPath);

            return response()->json([
                'success' => true,
                'message' => "Dokumen berhasil diekspor & disimpan ke:\n" . $fullPath,
                'path' => $fullPath
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengekspor dokumen: ' . $e->getMessage()], 500);
        }
    }

    public function checkLocalFileExists(Request $request): JsonResponse
    {
        $request->validate([
            'number' => 'required|string',
            'company_id' => 'nullable|integer',
            'type' => 'nullable|string',
        ]);

        $number = $request->query('number');
        $companyId = $request->query('company_id');
        $type = $request->query('type');

        $document = Document::with('company')->where('document_number', $number)->first();
        if (!$document) {
            $document = new Document([
                'document_number' => $number,
                'type' => $type,
                'company_id' => $companyId,
            ]);
            if ($companyId) {
                $document->setRelation('company', \App\Models\Company::find($companyId));
            }
        }

        $storagePath = $this->getStoragePathForDocument($document);

        if (empty($storagePath)) {
            return response()->json([
                'exists' => false,
                'path_configured' => false,
                'message' => 'Path penyimpanan lokal belum dikonfigurasi di Pengaturan Aplikasi.'
            ]);
        }

        $fileName = str_replace(['/', '\\'], '-', $number) . '.docx';
        $fullPath = rtrim($storagePath, '/\\') . DIRECTORY_SEPARATOR . $fileName;

        return response()->json([
            'exists' => file_exists($fullPath),
            'filename' => $fileName,
            'path_configured' => true,
            'full_path' => $fullPath
        ]);
    }

    public function generateAndSaveLocalFile(Document $document, bool $overwrite = false): string|null
    {
        $storagePath = $this->getStoragePathForDocument($document);

        if (empty($storagePath)) {
            return null;
        }

        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $fileName = str_replace(['/', '\\'], '-', $document->document_number) . '.docx';
        $fullPath = rtrim($storagePath, '/\\') . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($fullPath) && !$overwrite) {
            throw new \Exception("File already exists at '{$fullPath}'.");
        }

        $templateProcessor = $this->fillTemplate($document);
        $templateProcessor->saveAs($fullPath);

        return $fullPath;
    }

    public function getStoragePathForDocument(Document $document): ?string
    {
        $type = $document->type;
        $companyId = $document->company_id;

        if (empty($type)) {
            return null;
        }

        // 1. Check company-specific path setting for this document type
        $path = null;
        if ($companyId) {
            $path = \App\Models\Setting::get("local_path_{$type}", null, $companyId);
        }
        if ($path) {
            return $path;
        }

        // 2. Check global path setting for this document type
        $path = \App\Models\Setting::get("local_path_{$type}");
        if ($path) {
            return $path;
        }

        // 3. Fallback: Check general base path and construct path dynamically
        $basePath = \App\Models\Setting::get('documents_storage_path');
        if (empty($basePath)) {
            $basePath = env('DOCUMENTS_STORAGE_PATH');
        }

        if ($basePath) {
            $companyName = $document->company ? $document->company->name : null;
            if (!$companyName && $companyId) {
                $company = \App\Models\Company::find($companyId);
                $companyName = $company ? $company->name : null;
            }
            
            $companyDir = $companyName ? str_replace('.', '', $companyName) : 'Default';
            $typeDir = strtoupper(str_replace('_', ' ', $type));
            
            return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $companyDir . DIRECTORY_SEPARATOR . $typeDir;
        }

        return null;
    }

    private function autoSaveIfConfirmed(Document $document, bool $overwrite = false): void
    {
        if ($document->status !== 'confirmed') {
            return;
        }

        try {
            $this->generateAndSaveLocalFile($document, $overwrite);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to auto-save document: " . $e->getMessage());
        }
    }

    private function fillTemplate(Document $document): \PhpOffice\PhpWord\TemplateProcessor
    {
        $company = $document->company;
        $templatePath = null;

        if ($company && !empty($company->alias)) {
            $alias = strtolower(trim($company->alias));
            $companyTemplatePath = base_path("templates/{$alias}_{$document->type}.docx");
            if (file_exists($companyTemplatePath)) {
                $templatePath = $companyTemplatePath;
            }
        }

        if (!$templatePath) {
            $templatePath = base_path("templates/{$document->type}.docx");
        }

        if (! file_exists($templatePath)) {
            throw new \Exception("Template file not found for type: {$document->type}");
        }

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

        $templateProcessor->setValue('company_bank', $bank ? $this->formatBankAccount($bank) : '');
        $templateProcessor->setValue('doc_number', $document->document_number ?? '');
        $templateProcessor->setValue('doc_date', $this->formatIndonesianDate($document->date));
        $templateProcessor->setValue('doc_due_date', $this->formatIndonesianDate($document->due_date));
        $templateProcessor->setValue('partner_name', $document->recipient_name ?: $partner->name ?? '');
        $templateProcessor->setValue('partner_address', $document->recipient_address ?: $partner->address ?? '');
        $templateProcessor->setValue('partner_contact', $partner->contact_person ?? '');
        $templateProcessor->setValue('partner_phone', $document->recipient_phone ?: $partner->phone ?? '');
        $templateProcessor->setValue('partner_pic', $document->recipient_pic ?: $partner->contact_person ?? '');
        $templateProcessor->setValue('partner_npwp', $partner->npwp ?? '');

        $templateProcessor->setValue('subtotal', $this->formatRupiah($document->subtotal));
        $templateProcessor->setValue('discount', $this->formatRupiah($document->discount));
        $templateProcessor->setValue('tax', $this->formatRupiah($document->tax));
        $templateProcessor->setValue('grand_total', $this->formatRupiah($document->grand_total));
        $templateProcessor->setValue('terbilang', $document->terbilang ?? '');
        $templateProcessor->setValue('dp_percent', $this->formatQty($document->dp_percent ?? 0));
        $templateProcessor->setValue('dp_amount', $this->formatRupiah($document->dp_amount ?? 0));
        $templateProcessor->setValue('remaining_amount', $this->formatRupiah(($document->subtotal + $document->tax) - ($document->dp_amount ?? 0)));
        
        $paymentType = $document->payment_type ?? 'full';
        $paymentTypeLabel = 'Grand Total';
        if ($paymentType === 'dp') {
            $paymentTypeLabel = 'Uang Muka (DP) ' . number_format((float) $document->dp_percent, 0) . '%';
        } elseif ($paymentType === 'pelunasan') {
            $paymentTypeLabel = 'Pelunasan ' . number_format((float) $document->dp_percent, 0) . '%';
        }
        $templateProcessor->setValue('payment_type', $paymentType);
        $templateProcessor->setValue('payment_type_label', $paymentTypeLabel);
        $templateProcessor->setValue('bank_name', $bank?->bank_name ?? '');
        $templateProcessor->setValue('bank_account_name', $bank?->account_name ?? '');
        $templateProcessor->setValue('bank_account_number', $bank?->account_number ?? '');
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

        $this->setMultilineValue($templateProcessor, 'terms', $document->terms);
        $this->setMultilineValue($templateProcessor, 'notes', $document->notes);
        $templateProcessor->setValue('customer_po_number', $document->customer_po_number ?? '');
        $templateProcessor->setValue('customer_po_date', $this->formatIndonesianDate($document->customer_po_date));
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
            $templateProcessor->setValue("no#{$row}", $row);
            $templateProcessor->setValue("product_name#{$row}", $item->product_name ?? '');
            $templateProcessor->setValue("description#{$row}", $item->description ?? '');
            $templateProcessor->setValue("qty#{$row}", $this->formatQty($item->qty));
            $templateProcessor->setValue("uom#{$row}", $item->uom ?? '');
            $templateProcessor->setValue("unit_price#{$row}", $this->formatRupiah($item->unit_price));
            $templateProcessor->setValue("total#{$row}", $this->formatRupiah($item->total));
        }

        return $templateProcessor;
    }

    private function formatBankAccount($bank): string
    {
        if (!$bank) return '';
        return "{$bank->bank_name} - {$bank->account_name} ({$bank->account_number})";
    }

    private function formatRupiah($value): string
    {
        $floatVal = (float)$value;
        $formatted = number_format($floatVal, 2, ',', '.');
        if (str_ends_with($formatted, ',00')) {
            $formatted = substr($formatted, 0, -3);
        }
        return 'Rp ' . $formatted;
    }

    private function formatQty($value): string
    {
        $floatVal = (float)$value;
        $formatted = number_format($floatVal, 2, ',', '.');
        if (str_ends_with($formatted, ',00')) {
            $formatted = substr($formatted, 0, -3);
        }
        return $formatted;
    }

    private function setMultilineValue(\PhpOffice\PhpWord\TemplateProcessor $templateProcessor, string $placeholder, ?string $value): void
    {
        $value = $value ?? '';
        $lines = explode("\n", str_replace("\r", "", $value));
        $textrun = new \PhpOffice\PhpWord\Element\TextRun();
        $textrun->addText(array_shift($lines));
        foreach ($lines as $line) {
            $textrun->addTextBreak();
            $textrun->addText($line);
        }
        $templateProcessor->setComplexValue($placeholder, $textrun);
    }

    private function formatIndonesianDate($date): string
    {
        if (empty($date)) {
            return '';
        }
        
        $carbonDate = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        
        return $carbonDate->locale('id')->isoFormat('D MMMM YYYY');
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

    public function formDependencies(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        $companies = Company::with(['phones', 'bankAccounts'])->get();

        $partnersQuery = \App\Models\Partner::query();
        $productsQuery = \App\Models\Product::query();
        $documentsQuery = Document::with(['partner', 'items', 'reference']);

        if ($companyId) {
            $partnersQuery->where('company_id', $companyId);
            $productsQuery->where('company_id', $companyId);
            $documentsQuery->where('company_id', $companyId);
        }

        return response()->json([
            'companies' => $companies,
            'partners' => $partnersQuery->get(),
            'products' => $productsQuery->get(),
            'documents' => $documentsQuery->orderBy('id', 'desc')->get(),
        ]);
    }

    public function parsePdf(Request $request): JsonResponse
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240', // Max 10MB
        ]);

        $apiKey = Setting::get('gemini_api_key') ?: (config('services.gemini.key') ?? env('GEMINI_API_KEY'));
        $model = Setting::get('gemini_model') ?: 'gemini-1.5-flash';

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Gemini API Key belum dikonfigurasi. Silakan atur di Pengaturan Web atau file .env.'
            ], 422);
        }

        try {
            $file = $request->file('pdf');
            $pdfBase64 = base64_encode(file_get_contents($file->getRealPath()));

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'mimeType' => 'application/pdf',
                                    'data' => $pdfBase64,
                                ]
                            ],
                            [
                                'text' => 'Analyze the uploaded document. Extract the details like partner name (vendor or customer), document date, document number, payment terms, notes, and the line items with their product name, description, quantity, unit of measure, and unit price.'
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'partner_name' => [
                                'type' => 'STRING',
                                'description' => 'Nama perusahaan rekanan (customer / vendor / client) yang tertulis di PDF. Dahulukan nama perusahaan daripada perorangan jika ada.'
                            ],
                            'document_number' => [
                                'type' => 'STRING',
                                'description' => 'Nomor surat penawaran, invoice, atau PO yang tercantum di PDF.'
                            ],
                            'date' => [
                                'type' => 'STRING',
                                'description' => 'Tanggal dokumen dikeluarkan, format: YYYY-MM-DD. Gunakan format YYYY-MM-DD saja.'
                            ],
                            'due_date' => [
                                'type' => 'STRING',
                                'description' => 'Tanggal jatuh tempo jika ada, format: YYYY-MM-DD.'
                            ],
                            'notes' => [
                                'type' => 'STRING',
                                'description' => 'Catatan tambahan seperti keterangan teknis atau catatan lain yang ada di PDF.'
                            ],
                            'terms' => [
                                'type' => 'STRING',
                                'description' => 'Syarat pembayaran atau terms & conditions yang tertulis di PDF.'
                            ],
                            'discount' => [
                                'type' => 'NUMBER',
                                'description' => 'Nilai diskon total jika tertulis nominalnya.'
                            ],
                            'is_ppn' => [
                                'type' => 'BOOLEAN',
                                'description' => 'Apakah harga di penawaran ini sudah termasuk PPN (VAT) atau menerapkan PPN (true jika ya, false jika tidak).'
                            ],
                            'items' => [
                                'type' => 'ARRAY',
                                'items' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'product_name' => [
                                            'type' => 'STRING',
                                            'description' => 'Nama barang / nama produk utama.'
                                        ],
                                        'description' => [
                                            'type' => 'STRING',
                                            'description' => 'Spesifikasi detail, tipe, deskripsi barang.'
                                        ],
                                        'qty' => [
                                            'type' => 'NUMBER',
                                            'description' => 'Kuantitas atau jumlah unit.'
                                        ],
                                        'uom' => [
                                            'type' => 'STRING',
                                            'description' => 'Satuan barang, contoh: PCS, SET, UNIT, BOX.'
                                        ],
                                        'unit_price' => [
                                            'type' => 'NUMBER',
                                            'description' => 'Harga per unit sebelum PPN/Diskon.'
                                        ]
                                    ],
                                    'required' => ['product_name', 'qty', 'unit_price']
                                ]
                            ]
                        ],
                        'required' => ['partner_name', 'items']
                    ]
                ]
            ]);

            if ($response->failed()) {
                $errorMsg = $response->json('error.message') ?? 'Terjadi kesalahan pada Gemini API.';
                return response()->json([
                    'success' => false,
                    'message' => 'Gemini API Error: ' . $errorMsg
                ], $response->status());
            }

            $result = $response->json();
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($text)) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI tidak mengembalikan hasil pembacaan. Coba periksa file PDF Anda.'
                ], 500);
            }

            $extracted = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI tidak mengembalikan JSON yang valid.'
                ], 500);
            }

            // Find matching partner in DB
            $partnerName = $extracted['partner_name'] ?? '';
            $matchedPartner = null;
            if (!empty($partnerName)) {
                $matchedPartner = Partner::where(function($query) use ($partnerName) {
                    $query->where('name', 'like', "%{$partnerName}%")
                          ->orWhere('alias', 'like', "%{$partnerName}%");
                })->first();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'partner_id' => $matchedPartner ? $matchedPartner->id : null,
                    'partner_name' => $partnerName,
                    'document_number' => $extracted['document_number'] ?? '',
                    'date' => $extracted['date'] ?? date('Y-m-d'),
                    'due_date' => $extracted['due_date'] ?? '',
                    'notes' => $extracted['notes'] ?? '',
                    'terms' => $extracted['terms'] ?? '',
                    'discount' => $extracted['discount'] ?? 0,
                    'is_ppn' => $extracted['is_ppn'] ?? true,
                    'items' => array_map(function($item) {
                        return [
                            'product_name' => $item['product_name'] ?? '',
                            'description' => $item['description'] ?? '',
                            'qty' => floatval($item['qty'] ?? 1),
                            'uom' => $item['uom'] ?? 'PCS',
                            'unit_price' => floatval($item['unit_price'] ?? 0),
                        ];
                    }, $extracted['items'] ?? [])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca PDF: ' . $e->getMessage()
            ], 500);
        }
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


}
