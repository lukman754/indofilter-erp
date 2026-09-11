<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Partner;
use App\Http\Controllers\Api\DocumentController;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class DocumentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_fill_template_uses_company_alias_prefix_template(): void
    {
        // 1. Create a Company with alias "IFS"
        $company = Company::create([
            'name' => 'PT. INDO FILTER SEMESTA',
            'alias' => 'IFS',
            'is_active' => true,
        ]);

        // 2. Create a Partner
        $partner = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner',
            'is_active' => true,
        ]);

        // 3. Create a Document
        $document = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'quotation',
            'date' => now(),
            'document_number' => 'QUO-001',
            'status' => 'draft',
        ]);

        $document->load(['company', 'partner']);

        // 4. Instantiate DocumentController
        $controller = new DocumentController($this->createMock(DocumentNumberService::class));

        // 5. Use Reflection to invoke private fillTemplate method
        $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
        $method->setAccessible(true);

        /** @var \PhpOffice\PhpWord\TemplateProcessor $templateProcessor */
        $templateProcessor = $method->invoke($controller, $document);

        // 6. Assert that it used the company-specific template
        $this->assertFileExists(base_path('templates/ifs_quotation.docx'));
        $this->assertNotNull($templateProcessor);
    }

    public function test_fill_template_uses_company_alias_prefix_template_for_af(): void
    {
        // 1. Create a Company with alias "AF"
        $company = Company::create([
            'name' => 'PT. INDO ARTHAWA FILTER',
            'alias' => 'AF',
            'is_active' => true,
        ]);

        // 2. Create a Partner
        $partner = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner',
            'is_active' => true,
        ]);

        // 3. Create a Document
        $document = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'invoice',
            'date' => now(),
            'document_number' => 'INV-001',
            'status' => 'draft',
        ]);

        $document->load(['company', 'partner']);

        // 4. Instantiate DocumentController
        $controller = new DocumentController($this->createMock(DocumentNumberService::class));

        // 5. Use Reflection to invoke private fillTemplate method
        $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
        $method->setAccessible(true);

        /** @var \PhpOffice\PhpWord\TemplateProcessor $templateProcessor */
        $templateProcessor = $method->invoke($controller, $document);

        $this->assertFileExists(base_path('templates/af_invoice.docx'));
        $this->assertNotNull($templateProcessor);
    }

    public function test_document_with_item_variations_fills_template_successfully(): void
    {
        // 1. Create a Company with alias "IFS"
        $company = Company::create([
            'name' => 'PT. INDO FILTER SEMESTA',
            'alias' => 'IFS',
            'is_active' => true,
        ]);

        // 2. Create a Partner
        $partner = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner',
            'is_active' => true,
        ]);

        // 3. Create a Document
        $document = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'quotation',
            'date' => now(),
            'document_number' => 'QUO-002',
            'status' => 'draft',
        ]);

        // 4. Create a DocumentItem with variations
        $document->items()->create([
            'product_name' => 'Pass Flexible Coupling',
            'description' => "SS316, 1200 Psi\nSome main description line",
            'qty' => 20,
            'uom' => 'PCS',
            'unit_price' => 1705850,
            'total' => 34117000,
            'variations' => [
                ['name' => '1,5 Inch (48,33mm)', 'qty' => 10, 'unit_price' => 1596500, 'total' => 15965000],
                ['name' => '2 Inch (60,3mm)', 'qty' => 10, 'unit_price' => 1815200, 'total' => 18152000],
            ],
        ]);

        $document->load(['company', 'partner', 'items']);

        // 5. Instantiate DocumentController
        $controller = new DocumentController($this->createMock(DocumentNumberService::class));

        // 6. Use Reflection to invoke private fillTemplate method
        $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
        $method->setAccessible(true);

        /** @var \PhpOffice\PhpWord\TemplateProcessor $templateProcessor */
        $templateProcessor = $method->invoke($controller, $document);

        // 7. Assertions
        $this->assertNotNull($templateProcessor);
        $this->assertEquals(1, $document->items->count());
        $this->assertIsArray($document->items->first()->variations);
        $this->assertEquals('1,5 Inch (48,33mm)', $document->items->first()->variations[0]['name']);
    }

    public function test_document_with_dp_payment_type_clones_total_rows_successfully(): void
    {
        copy(base_path('templates/invoice.docx'), base_path('templates/test_invoice.docx'));
        try {
            // 1. Create a Company with alias "TEST"
            $company = Company::create([
                'name' => 'PT. INDO FILTER SEMESTA',
                'alias' => 'TEST',
                'is_active' => true,
            ]);

            // 2. Create a Partner
            $partner = Partner::create([
                'company_id' => $company->id,
                'type' => 'customer',
                'name' => 'Test Partner',
                'is_active' => true,
            ]);

            // 3. Create a Document with DP payment type
            $document = Document::create([
                'company_id' => $company->id,
                'partner_id' => $partner->id,
                'type' => 'invoice',
                'date' => now(),
                'document_number' => 'INV-002',
                'status' => 'draft',
                'payment_type' => 'dp',
                'dp_percent' => 30,
                'dp_amount' => 300000,
                'subtotal' => 1000000,
                'tax' => 110000,
                'discount' => 0,
                'grand_total' => 1110000,
                'is_ppn' => true,
            ]);

            $document->load(['company', 'partner', 'items']);

            // 4. Instantiate DocumentController
            $controller = new DocumentController($this->createMock(DocumentNumberService::class));

            // 5. Use Reflection to invoke private fillTemplate method
            $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
            $method->setAccessible(true);

            /** @var \PhpOffice\PhpWord\TemplateProcessor $templateProcessor */
            $templateProcessor = $method->invoke($controller, $document);

            $this->assertNotNull($templateProcessor);

            // Save to temporary file and inspect XML
            $tempFile = tempnam(sys_get_temp_dir(), 'docx');
            $templateProcessor->saveAs($tempFile);

            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($tempFile));
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            unlink($tempFile);

            // Under DP payment:
            // Total Tagihan row should exist
            $this->assertStringContainsString('Total Tagihan', $xml);
            $this->assertStringContainsString('Rp 1.110.000', $xml);

            // DP value row should exist
            $this->assertStringContainsString('DP 30%', $xml);
            $this->assertStringContainsString('Rp 300.000', $xml);

            // Remaining value row should exist
            $this->assertStringContainsString('PELUNASAN 70%', $xml);
            $this->assertStringContainsString('Rp 810.000', $xml);

            // BALANCE DUE (Total Yang Harus Dibayar) row should exist
            $this->assertStringContainsString('BALANCE DUE', $xml);
        } finally {
            @unlink(base_path('templates/test_invoice.docx'));
        }
    }

    public function test_document_with_full_payment_type_removes_dp_rows_successfully(): void
    {
        copy(base_path('templates/invoice.docx'), base_path('templates/test_invoice.docx'));
        try {
            // 1. Create a Company with alias "TEST"
            $company = Company::create([
                'name' => 'PT. INDO FILTER SEMESTA',
                'alias' => 'TEST',
                'is_active' => true,
            ]);

            // 2. Create a Partner
            $partner = Partner::create([
                'company_id' => $company->id,
                'type' => 'customer',
                'name' => 'Test Partner',
                'is_active' => true,
            ]);

            // 3. Create a Document with full payment type
            $document = Document::create([
                'company_id' => $company->id,
                'partner_id' => $partner->id,
                'type' => 'invoice',
                'date' => now(),
                'document_number' => 'INV-003',
                'status' => 'draft',
                'payment_type' => 'full',
                'subtotal' => 1000000,
                'tax' => 110000,
                'discount' => 0,
                'grand_total' => 1110000,
                'is_ppn' => true,
            ]);

            $document->load(['company', 'partner', 'items']);

            // 4. Instantiate DocumentController
            $controller = new DocumentController($this->createMock(DocumentNumberService::class));

            // 5. Use Reflection to invoke private fillTemplate method
            $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
            $method->setAccessible(true);

            /** @var \PhpOffice\PhpWord\TemplateProcessor $templateProcessor */
            $templateProcessor = $method->invoke($controller, $document);

            $this->assertNotNull($templateProcessor);

            // Save to temporary file and inspect XML
            $tempFile = tempnam(sys_get_temp_dir(), 'docx');
            $templateProcessor->saveAs($tempFile);

            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($tempFile));
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            unlink($tempFile);

            // Under full payment:
            // Total Tagihan row should exist
            $this->assertStringContainsString('Total Tagihan', $xml);

            // DP rows should NOT exist
            $this->assertStringNotContainsString('DP', $xml);
            $this->assertStringNotContainsString('PELUNASAN', $xml);

            // BALANCE DUE row should exist
            $this->assertStringContainsString('BALANCE DUE', $xml);
            $this->assertStringContainsString('Rp 1.110.000', $xml);
        } finally {
            @unlink(base_path('templates/test_invoice.docx'));
        }
    }

    public function test_document_export_with_skipped_purchase_order_template(): void
    {
        // 1. Create a Company with alias "IFS"
        $company = Company::create([
            'name' => 'PT. INDO FILTER SEMESTA',
            'alias' => 'IFS',
            'is_active' => true,
        ]);

        // 2. Create a Partner
        $partner = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner',
            'is_active' => true,
        ]);

        // 3. Create a Document with type "purchase_order"
        $document = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'purchase_order',
            'date' => now(),
            'document_number' => 'PO-001',
            'status' => 'draft',
            'payment_type' => 'full',
            'subtotal' => 1000000,
            'tax' => 110000,
            'discount' => 0,
            'grand_total' => 1110000,
            'is_ppn' => true,
        ]);

        $document->load(['company', 'partner', 'items']);

        // 4. Instantiate DocumentController
        $controller = new DocumentController($this->createMock(DocumentNumberService::class));

        // 5. Use Reflection to invoke private fillTemplate method
        $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
        $method->setAccessible(true);

        // This would throw an exception before the fix because ifs_purchase_order.docx doesn't contain total_tagihan / dp_value / rem_value
        /** @var \PhpOffice\PhpWord\TemplateProcessor $templateProcessor */
        $templateProcessor = $method->invoke($controller, $document);

        $this->assertNotNull($templateProcessor);

        $tempFile = tempnam(sys_get_temp_dir(), 'docx');
        $templateProcessor->saveAs($tempFile);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempFile));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tempFile);

        // Confirm that since it's an old template, it doesn't crash, and it still contains grand_total value replacement
        $this->assertStringContainsString('Rp 1.110.000', $xml);
    }

    public function test_document_export_inserts_po_image_successfully(): void
    {
        // 1. Create a temporary folder and dummy image file
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'po_masuk_test_' . uniqid();
        mkdir($tempDir);

        $dummyImagePath = $tempDir . DIRECTORY_SEPARATOR . 'PO-12345.png';
        copy(base_path('resources/logo_placeholder.png'), $dummyImagePath);

        // 2. Create Company with the temporary path
        $company = Company::create([
            'name' => 'PT. INDO FILTER SEMESTA',
            'alias' => 'IFS',
            'po_masuk_path' => $tempDir,
            'is_active' => true,
        ]);

        // 3. Create Partner
        $partner = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner',
            'is_active' => true,
        ]);

        // 4. Create Document with type "invoice" and customer_po_number "PO-12345"
        $document = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'invoice',
            'date' => now(),
            'document_number' => 'INV-004',
            'status' => 'draft',
            'customer_po_number' => 'PO-12345',
            'payment_type' => 'full',
            'subtotal' => 1000000,
            'tax' => 110000,
            'discount' => 0,
            'grand_total' => 1110000,
            'is_ppn' => true,
        ]);

        $document->load(['company', 'partner', 'items']);

        // 5. Instantiate DocumentController and call fillTemplate
        $controller = new DocumentController($this->createMock(DocumentNumberService::class));
        $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
        $method->setAccessible(true);

        /** @var \PhpOffice\PhpWord\TemplateProcessor $templateProcessor */
        $templateProcessor = $method->invoke($controller, $document);

        $this->assertNotNull($templateProcessor);

        $tempFile = tempnam(sys_get_temp_dir(), 'docx');
        $templateProcessor->saveAs($tempFile);

        // 6. Inspect ZIP archive to ensure the image is inside word/media/
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempFile));

        $hasImage = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'word/media/') === 0) {
                $hasImage = true;
                break;
            }
        }

        $zip->close();
        unlink($tempFile);
        unlink($dummyImagePath);
        rmdir($tempDir);

        $this->assertTrue($hasImage, "The exported document should contain the PO image in its media folder.");
    }

    public function test_document_po_file_upload_and_download_successfully(): void
    {
        // 1. Create a user
        $user = \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test' . uniqid() . '@example.com',
            'password' => bcrypt('password')
        ]);
        $this->actingAs($user);

        // 2. Create Company
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'po_upload_test_' . uniqid();
        mkdir($tempDir);

        $company = Company::create([
            'name' => 'PT. INDO FILTER SEMESTA',
            'alias' => 'IFS',
            'po_masuk_path' => $tempDir,
            'is_active' => true,
        ]);

        // 3. Create Partner
        $partner = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner',
            'is_active' => true,
        ]);

        // 4. Create Document
        $document = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'invoice',
            'date' => now(),
            'document_number' => 'INV-999',
            'status' => 'draft',
            'payment_type' => 'full',
            'subtotal' => 1000000,
            'tax' => 110000,
            'discount' => 0,
            'grand_total' => 1110000,
            'is_ppn' => true,
        ]);

        // 5. Upload file using the API endpoint
        $fakePdf = \Illuminate\Http\UploadedFile::fake()->createWithContent('po_customer.pdf', "%PDF-1.4\n%\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\ntrailer\n<<\n/Root 1 0 R\n>>\n%%EOF");

        $response = $this->postJson("/api/documents/{$document->id}/upload-po", [
            'customer_po_file' => $fakePdf
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'customer_po_file']);

        $uploadedFilename = $response->json('customer_po_file');
        $this->assertNotEmpty($uploadedFilename);

        $document->refresh();
        $this->assertEquals($uploadedFilename, $document->customer_po_file);

        // Verify the file was stored in the custom path
        $filePath = $tempDir . DIRECTORY_SEPARATOR . $uploadedFilename;
        $this->assertFileExists($filePath);

        // 6. Download/view file using the API endpoint
        $downloadResponse = $this->get("/api/documents/{$document->id}/download-po");
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');

        // Cleanup
        @unlink($filePath);
        @rmdir($tempDir);
    }

    public function test_delivery_slip_exports_po_number_successfully(): void
    {
        copy(base_path('templates/delivery_slip.docx'), base_path('templates/test_delivery_slip.docx'));
        try {
            $company = Company::create([
                'name' => 'PT. INDO FILTER SEMESTA',
                'alias' => 'TEST',
                'is_active' => true,
            ]);

            $partner = Partner::create([
                'company_id' => $company->id,
                'type' => 'customer',
                'name' => 'Test Partner',
                'is_active' => true,
            ]);

            $referencedInvoice = Document::create([
                'company_id' => $company->id,
                'partner_id' => $partner->id,
                'type' => 'invoice',
                'date' => now(),
                'document_number' => 'INV-REF-123',
                'status' => 'confirmed',
                'payment_type' => 'full',
                'subtotal' => 1000000,
                'tax' => 110000,
                'discount' => 0,
                'grand_total' => 1110000,
                'is_ppn' => true,
            ]);

            $deliverySlip = Document::create([
                'company_id' => $company->id,
                'partner_id' => $partner->id,
                'type' => 'delivery_slip',
                'date' => now(),
                'document_number' => 'SJ-001',
                'status' => 'draft',
                'customer_po_number' => 'PO-CUST-789',
                'reference_id' => $referencedInvoice->id,
                'subtotal' => 0,
                'tax' => 0,
                'discount' => 0,
                'grand_total' => 0,
            ]);

            $controller = new DocumentController($this->createMock(DocumentNumberService::class));
            $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
            $method->setAccessible(true);

            $templateProcessor = $method->invoke($controller, $deliverySlip);
            $this->assertNotNull($templateProcessor);

            $tempFile = tempnam(sys_get_temp_dir(), 'docx');
            $templateProcessor->saveAs($tempFile);

            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($tempFile));
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            unlink($tempFile);

            // Assert PO number is exported
            $this->assertStringContainsString('PO-CUST-789', $xml);
            // Assert referenced invoice number is exported
            $this->assertStringContainsString('INV-REF-123', $xml);
        } finally {
            @unlink(base_path('templates/test_delivery_slip.docx'));
        }
    }

    public function test_delivery_address_template_is_23cm_by_11cm(): void
    {
        $templatePath = base_path('templates/delivery_address.docx');
        $this->assertFileExists($templatePath);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($templatePath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('w:pgSz', $xml);
        $this->assertMatchesRegularExpression('/w:w="13039(?:\.\d+)?"/', $xml);
        $this->assertMatchesRegularExpression('/w:h="6236(?:\.\d+)?"/', $xml);
    }

    public function test_partner_phone_formatting_slash(): void
    {
        $company = Company::create([
            'name' => 'PT. Test Company',
            'alias' => 'TC',
            'is_active' => true,
        ]);

        $partner = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner',
            'phone' => '0895292904210',
            'contact_person' => 'Lukman',
            'is_active' => true,
        ]);

        // 1. Non-delivery_address document (e.g. quotation) should format with slash
        $quotation = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'quotation',
            'date' => now(),
            'document_number' => 'QUO-TEST-123',
            'status' => 'draft',
            'recipient_pic' => 'Lukman',
            'recipient_phone' => '0895292904210',
        ]);

        $controller = new DocumentController($this->createMock(DocumentNumberService::class));
        $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
        $method->setAccessible(true);

        $templateProcessor = $method->invoke($controller, $quotation);

        $tempFile = tempnam(sys_get_temp_dir(), 'docx');
        $templateProcessor->saveAs($tempFile);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempFile));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tempFile);

        // Check if XML contains formatted phone
        $this->assertStringContainsString('Lukman / 0895292904210', $xml);

        // 2. Delivery_address document should format without slash
        $deliveryAddress = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'delivery_address',
            'date' => now(),
            'document_number' => 'DA-TEST-123',
            'status' => 'draft',
            'recipient_pic' => 'Lukman',
            'recipient_phone' => '0895292904210',
        ]);

        $templateProcessor2 = $method->invoke($controller, $deliveryAddress);

        $tempFile2 = tempnam(sys_get_temp_dir(), 'docx');
        $templateProcessor2->saveAs($tempFile2);
        $zip2 = new \ZipArchive();
        $this->assertTrue($zip2->open($tempFile2));
        $xml2 = $zip2->getFromName('word/document.xml');
        $zip2->close();
        unlink($tempFile2);

        // Should not have the combined slash, only the phone number
        $this->assertStringNotContainsString('Lukman / 0895292904210', $xml2);
        $this->assertStringContainsString('0895292904210', $xml2);

        // 3. Only PIC is present (phone is empty)
        $partnerNoPhone = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner No Phone',
            'phone' => null,
            'contact_person' => 'Lukman',
            'is_active' => true,
        ]);
        $onlyPicDoc = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partnerNoPhone->id,
            'type' => 'quotation',
            'date' => now(),
            'document_number' => 'QUO-TEST-PIC',
            'status' => 'draft',
            'recipient_pic' => 'Lukman',
            'recipient_phone' => '',
        ]);
        $templateProcessor3 = $method->invoke($controller, $onlyPicDoc);
        $tempFile3 = tempnam(sys_get_temp_dir(), 'docx');
        $templateProcessor3->saveAs($tempFile3);
        $zip3 = new \ZipArchive();
        $this->assertTrue($zip3->open($tempFile3));
        $xml3 = $zip3->getFromName('word/document.xml');
        $zip3->close();
        unlink($tempFile3);

        $this->assertStringNotContainsString('Lukman /', $xml3);
        $this->assertStringContainsString('Lukman', $xml3);

        // 4. Only Phone is present (PIC is empty)
        $partnerNoPic = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner No PIC',
            'phone' => '0895292904210',
            'contact_person' => null,
            'is_active' => true,
        ]);
        $onlyPhoneDoc = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partnerNoPic->id,
            'type' => 'quotation',
            'date' => now(),
            'document_number' => 'QUO-TEST-PHONE',
            'status' => 'draft',
            'recipient_pic' => '',
            'recipient_phone' => '0895292904210',
        ]);
        $templateProcessor4 = $method->invoke($controller, $onlyPhoneDoc);
        $tempFile4 = tempnam(sys_get_temp_dir(), 'docx');
        $templateProcessor4->saveAs($tempFile4);
        $zip4 = new \ZipArchive();
        $this->assertTrue($zip4->open($tempFile4));
        $xml4 = $zip4->getFromName('word/document.xml');
        $zip4->close();
        unlink($tempFile4);

        $this->assertStringNotContainsString('/ 0895292904210', $xml4);
        $this->assertStringContainsString('0895292904210', $xml4);
    }

    public function test_ppn_row_not_deleted_when_is_ppn_false(): void
    {
        $company = Company::create([
            'name' => 'PT. Test Company',
            'alias' => 'TC',
            'is_active' => true,
        ]);

        $partner = Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Test Partner',
            'is_active' => true,
        ]);

        // Create a document with is_ppn = false
        $document = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'invoice',
            'date' => now(),
            'document_number' => 'INV-PPN-FALSE',
            'status' => 'draft',
            'payment_type' => 'full',
            'subtotal' => 1000000,
            'tax' => 110000,
            'discount' => 0,
            'grand_total' => 1110000,
            'is_ppn' => false,
        ]);

        $controller = new DocumentController($this->createMock(DocumentNumberService::class));
        $method = new ReflectionMethod(DocumentController::class, 'fillTemplate');
        $method->setAccessible(true);

        $templateProcessor = $method->invoke($controller, $document);
        $tempFile = tempnam(sys_get_temp_dir(), 'docx');
        $templateProcessor->saveAs($tempFile);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempFile));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tempFile);

        // Since is_ppn is false, the PPN row should still exist (it should contain 'PPN (11%)' in the template)
        // but its value should be replaced with empty string (it should NOT contain 'Rp 110.000')
        $this->assertStringContainsString('PPN (11%)', $xml);
        $this->assertStringNotContainsString('Rp 110.000', $xml);
    }
}
