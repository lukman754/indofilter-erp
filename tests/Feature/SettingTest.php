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

    public function test_can_change_drive_letter_in_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Setting::set('documents_storage_path', 'G:\My Drive\Arthawa');
        Setting::set('local_path_quotation', 'G:\My Drive\Arthawa\PT INDO FILTER SEMESTA\PENAWARAN');

        $response = $this->postJson('/api/settings/change-drive-letter', [
            'drive_letter' => 'H',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('H:\My Drive\Arthawa', Setting::get('documents_storage_path'));
        $this->assertEquals('H:\My Drive\Arthawa\PT INDO FILTER SEMESTA\PENAWARAN', Setting::get('local_path_quotation'));
    }

    public function test_can_update_supporting_docs_path_setting(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/settings', [
            'global_folders' => [
                'supporting_docs' => 'H:\My Drive\Arthawa\DOKUMEN PENDUKUNG',
            ]
        ]);

        $response->assertStatus(200);
        $this->assertEquals('H:\My Drive\Arthawa\DOKUMEN PENDUKUNG', Setting::get('local_path_supporting_docs'));
    }
}
