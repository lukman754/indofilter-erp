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
}
