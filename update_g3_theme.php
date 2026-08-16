<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenants = \App\Models\Tenant::all();
foreach ($tenants as $tenant) {
    echo "Tenant: {$tenant->id}\n";
    if ($tenant->id === 'g3' || str_contains(strtolower($tenant->id), 'g3')) {
        tenancy()->initialize($tenant);
        $setting = \App\Models\StoreSetting::first();
        if ($setting) {
            echo "Current theme: {$setting->theme_name}\n";
            $setting->theme_name = 'tech-dark';
            $setting->save();
            echo "Updated theme to tech-dark for {$tenant->id}\n";
            \Illuminate\Support\Facades\Cache::forget('store_settings_' . $tenant->id);
        } else {
            echo "No StoreSetting found for {$tenant->id}\n";
        }
        tenancy()->end();
    }
}
