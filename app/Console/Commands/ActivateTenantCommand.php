<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class ActivateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:activate {tenant : ID del tenant a reactivar (ej: jcg)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reactiva un tenant previamente suspendido';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = trim((string) $this->argument('tenant'));

        /** @var Tenant|null $tenant */
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $available = Tenant::query()->pluck('id')->implode(', ');
            $this->error("No existe ningún tenant con el ID '{$tenantId}'.");
            if ($available !== '') {
                $this->line("Tenants disponibles: <comment>{$available}</comment>");
            }

            return self::FAILURE;
        }

        if (! $tenant->isSuspended()) {
            $this->comment("El tenant '{$tenant->id}' ya se encuentra activo.");

            return self::SUCCESS;
        }

        $tenant->activate();

        $domains = $tenant->domains()->pluck('domain')->implode(', ');
        $this->info("Tenant '{$tenant->id}' ({$domains}) reactivado correctamente.");

        return self::SUCCESS;
    }
}
