<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class SuspendTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:suspend
                            {tenant : ID del tenant a suspender (ej: jcg)}
                            {--message= : Mensaje opcional a mostrar en la pantalla 503}
                            {--retry=3600 : Segundos sugeridos en la cabecera Retry-After}
                            {--allow=* : Direcciones IP permitidas para saltear la suspensión}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suspende temporalmente un tenant (modo mantenimiento 503) conservando su base de datos y archivos';

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

        $message = $this->option('message') ? (string) $this->option('message') : null;
        $retry = max(60, (int) $this->option('retry'));
        $allowed = array_values(array_filter((array) $this->option('allow')));

        $tenant->suspend(
            message: $message,
            retry: $retry,
            allowed: $allowed,
        );

        $domains = $tenant->domains()->pluck('domain')->implode(', ');
        $this->info("Tenant '{$tenant->id}' ({$domains}) suspendido temporalmente (HTTP 503).");
        $this->line("Para reactivarlo: <comment>php artisan tenant:activate {$tenant->id}</comment>");

        return self::SUCCESS;
    }
}
