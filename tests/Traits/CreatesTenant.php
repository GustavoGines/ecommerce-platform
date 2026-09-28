<?php

namespace Tests\Traits;

use App\Models\Tenant;

trait CreatesTenant
{
    protected $tenant;

    protected function setUpTenancy(): void
    {
        if (file_exists(database_path('tenanttest-tenant'))) {
            @unlink(database_path('tenanttest-tenant'));
        }

        Tenant::all()->each->delete();

        $this->tenant = Tenant::create([
            'id' => 'test-tenant',
        ]);

        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->tenant) {
            $this->tenant->delete();
        }

        if (file_exists(database_path('tenanttest-tenant'))) {
            @unlink(database_path('tenanttest-tenant'));
        }

        parent::tearDown();
    }
}
