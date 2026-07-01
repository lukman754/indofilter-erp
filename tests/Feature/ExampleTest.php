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
}
