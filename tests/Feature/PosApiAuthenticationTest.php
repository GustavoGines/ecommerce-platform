<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar StoreSetting de la tienda de prueba
        $setting = StoreSetting::first() ?? new StoreSetting();
        $setting->store_name = 'G3 Tecnología';
        $setting->pos_sync_enabled = true;
        $setting->pos_api_token = 'pos_secret_token_12345';
        $setting->pos_webhook_url = 'https://pos.test/webhook';
        $setting->pos_webhook_secret = 'sec_pos_abcde';
        $setting->save();
    }

    public function test_api_rejects_requests_without_bearer_token()
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(401)
                 ->assertJson([
                     'error' => 'No autorizado',
                 ]);
    }

    public function test_api_rejects_requests_with_invalid_bearer_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_invalido_999',
            'X-Tenant-ID'   => 'g3',
        ])->getJson('/api/v1/health');

        $response->assertStatus(401)
                 ->assertJson([
                     'error' => 'Token inválido',
                 ]);
    }

    public function test_api_accepts_requests_with_valid_token_and_returns_health()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer pos_secret_token_12345',
            'X-Tenant-ID'   => 'g3',
        ])->getJson('/api/v1/health');

        $response->assertStatus(200)
                 ->assertJson([
                     'status'     => 'ok',
                     'version'    => '1.0',
                     'store_name' => 'G3 Tecnología',
                 ]);
    }

    public function test_api_store_status_alias_works()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer pos_secret_token_12345',
            'X-Tenant-ID'   => 'g3',
        ])->getJson('/api/v1/store/status');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'ok',
                     'version' => '1.0',
                 ]);
    }
}
