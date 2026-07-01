<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_settings_including_gemini_model(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Setting::set('gemini_model', 'gemini-2.5-flash');

        $response = $this->getJson('/api/settings');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'gemini_model' => 'gemini-2.5-flash',
            ]);
    }

    public function test_can_update_gemini_model_setting(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/settings', [
            'gemini_model' => 'gemini-1.5-pro',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('gemini-1.5-pro', Setting::get('gemini_model'));
    }
}
