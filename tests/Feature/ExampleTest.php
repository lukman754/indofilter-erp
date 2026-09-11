<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_partner_validation(): void
    {
        $user = \App\Models\User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $company = \App\Models\Company::create([
            'name' => 'PT. INDO FILTER SEMESTA',
            'alias' => 'IFS',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/partners', [
            'name' => 'John Doe',
            'alias' => '',
            'type' => 'customer',
            'address' => '',
            'phone' => '',
            'email' => '',
            'npwp' => '',
            'contact_person' => '',
            'company_id' => $company->id,
        ]);

        echo "\nRESPONSE: " . $response->getContent() . "\n";
        $response->assertStatus(201);
    }

    public function test_partner_update_syncs_to_documents(): void
    {
        $user = \App\Models\User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $company = \App\Models\Company::create([
            'name' => 'PT. INDO FILTER SEMESTA',
            'alias' => 'IFS',
            'is_active' => true,
        ]);

        $partner = \App\Models\Partner::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Old Partner Name',
            'address' => 'Old Address',
            'phone' => '123456',
            'contact_person' => 'Old Contact',
        ]);

        $document = \App\Models\Document::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => 'quotation',
            'date' => '2026-07-21',
            'recipient_name' => 'Old Partner Name',
            'recipient_address' => 'Old Address',
            'recipient_phone' => '123456',
            'recipient_pic' => 'Old Contact',
        ]);

        $response = $this->putJson("/api/partners/{$partner->id}", [
            'name' => 'New Partner Name',
            'address' => 'New Address',
            'phone' => '987654',
            'contact_person' => 'New Contact',
        ]);

        $response->assertStatus(200);

        $document->refresh();
        $this->assertEquals('New Partner Name', $document->recipient_name);
        $this->assertEquals('New Address', $document->recipient_address);
        $this->assertEquals('987654', $document->recipient_phone);
        $this->assertEquals('New Contact', $document->recipient_pic);
    }
}
