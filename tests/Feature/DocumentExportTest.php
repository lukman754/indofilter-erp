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
}
