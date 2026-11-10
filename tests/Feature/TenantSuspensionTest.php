<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_commands_suspend_and_activate_tenant(): void
    {
        $this->assertFalse($this->tenant->fresh()->isSuspended());

        $this->artisan('tenant:suspend', [
            'tenant'    => 'test-tenant',
            '--message' => 'Tienda en mantenimiento programado.',
        ])->assertExitCode(0);

        $suspendedTenant = Tenant::find('test-tenant');
        $this->assertTrue($suspendedTenant->isSuspended());
        $this->assertSame('Tienda en mantenimiento programado.', $suspendedTenant->maintenance_mode['message'] ?? null);

        $this->artisan('tenant:activate', [
            'tenant' => 'test-tenant',
        ])->assertExitCode(0);

        $this->assertFalse(Tenant::find('test-tenant')->isSuspended());
    }

    public function test_suspended_tenant_blocks_web_and_pos_api_with_503_and_recovers_on_activate(): void
    {
        $setting = StoreSetting::first() ?? new StoreSetting();
        $setting->store_name = 'JCG Electrónica';
        $setting->pos_sync_enabled = true;
        $setting->pos_api_token = 'jcg_token_test_123';
        $setting->save();

        // Suspender el tenant
        $this->tenant->suspend('Sitio pausado temporalmente.');
        tenancy()->initialize($this->tenant->fresh());

        // 1. Petición web al dominio del tenant devuelve 503 con el mensaje personalizado
        $webResponse = $this->get('http://test.localhost/');
        $webResponse->assertStatus(503);
        $webResponse->assertSee('Sitio pausado temporalmente.');

        // 2. Petición POS API también devuelve 503
        $apiResponse = $this->withHeaders([
            'Authorization' => 'Bearer jcg_token_test_123',
            'X-Tenant-ID'   => 'test-tenant',
        ])->getJson('/api/v1/health');

        $apiResponse->assertStatus(503)
                    ->assertJson([
                        'error'   => 'Tienda suspendida temporalmente',
                        'message' => 'Sitio pausado temporalmente.',
                    ]);

        // 3. Reactivar el tenant y verificar que vuelve a responder 200
        $this->tenant->fresh()->activate();
        tenancy()->initialize($this->tenant->fresh());

        $activeApiResponse = $this->withHeaders([
            'Authorization' => 'Bearer jcg_token_test_123',
            'X-Tenant-ID'   => 'test-tenant',
        ])->getJson('/api/v1/health');

        $activeApiResponse->assertStatus(200);
    }

    public function test_allowed_ip_bypasses_web_suspension_and_invalid_tenant_fails_gracefully(): void
    {
        // 1. Si se suspende permitiendo la IP 127.0.0.1, la web permite el paso (200)
        $this->artisan('tenant:suspend', [
            'tenant'  => 'test-tenant',
            '--allow' => ['127.0.0.1'],
        ])->assertExitCode(0);

        tenancy()->end();

        $allowedResponse = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://test.localhost/');
        $allowedResponse->assertStatus(200);

        $blockedResponse = $this->withServerVariables(['REMOTE_ADDR' => '200.10.20.30'])
            ->get('http://test.localhost/');
        $blockedResponse->assertStatus(503);

        // 2. Si se intenta suspender un tenant inexistente, devuelve código de error controlado (1)
        $this->artisan('tenant:suspend', [
            'tenant' => 'tenant_inexistente',
        ])->assertExitCode(1);
    }
}
