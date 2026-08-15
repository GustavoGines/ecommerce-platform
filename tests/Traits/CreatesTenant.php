<?php

namespace Tests\Traits;

use App\Models\Tenant;

trait CreatesTenant
{
    protected $tenant;

    protected function setUpTenancy(): void
    {
        // Limpiar tenants viejos en DB temporal
        Tenant::query()->delete();

        $this->tenant = Tenant::create([
            'id' => 'test-tenant',
        ]);

        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        
        tenancy()->initialize($this->tenant);
    }
}
