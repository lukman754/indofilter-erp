<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportingDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_download_and_delete_supporting_documents()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = Company::create([
            'name' => 'PT. INDO FILTER SEMESTA',
            'alias' => 'IFS',
            'is_active' => true,
        ]);

        $partner = \App\Models\Partner::create([
            'company_id' => $company->id,
            'name' => 'John Doe',
            'type' => 'customer',
        ]);

        $document = Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'invoice',
            'date' => now(),
            'document_number' => 'INV/IFS/2026/07/0001',
            'status' => 'draft',
        ]);

        // 1. Upload Supporting Document
        $file = UploadedFile::fake()->create('kwitansi_dp_2026.pdf', 500, 'application/pdf');

        $response = $this->postJson("/api/documents/{$document->id}/upload-supporting", [
            'file' => $file,
            'title' => 'Kwitansi Pembayaran DP',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'supporting_documents' => [
                '*' => ['filename', 'original_name', 'title', 'uploaded_at']
            ]
        ]);

        $document->refresh();
        $this->assertCount(1, $document->supporting_documents);
        
        $uploaded = $document->supporting_documents[0];
        $this->assertEquals('Kwitansi Pembayaran DP', $uploaded['title']);
        $this->assertEquals('kwitansi_dp_2026.pdf', $uploaded['original_name']);
        
        $expectedFilename = 'Kwitansi Pembayaran DP - INV_IFS_2026_07_0001.pdf';
        $this->assertEquals($expectedFilename, $uploaded['filename']);

        // Check if file exists on disk
        $controller = app(\App\Http\Controllers\Api\DocumentController::class);
        $storagePath = $controller->getStoragePathForSupportingDocuments($document);
        $filePath = rtrim($storagePath, '/\\') . DIRECTORY_SEPARATOR . $uploaded['filename'];
        $this->assertTrue(file_exists($filePath), "File should be created at: " . $filePath);

        // 2. Download Supporting Document
        $downloadResponse = $this->get("/api/documents/{$document->id}/download-supporting/{$uploaded['filename']}");
        $downloadResponse->assertStatus(200);

        // 3. Delete Supporting Document
        $deleteResponse = $this->postJson("/api/documents/{$document->id}/delete-supporting", [
            'filename' => $uploaded['filename'],
        ]);

        $deleteResponse->assertStatus(200);
        $document->refresh();
        $this->assertCount(0, $document->supporting_documents);
        $this->assertFalse(file_exists($filePath), "File should be deleted from: " . $filePath);
    }
}
