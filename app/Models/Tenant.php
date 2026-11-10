<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\MaintenanceMode;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, MaintenanceMode;

    /**
     * Determina si el tenant se encuentra suspendido temporalmente (modo mantenimiento).
     */
    public function isSuspended(): bool
    {
        return ! empty($this->maintenance_mode);
    }

    /**
     * Suspende temporalmente el tenant sin eliminar su base de datos ni archivos.
     */
    public function suspend(?string $message = null, int $retry = 3600, array $allowed = []): void
    {
        $this->putDownForMaintenance([
            'message' => $message,
            'retry'   => $retry,
            'allowed' => $allowed,
        ]);

        if (tenancy()->initialized && tenancy()->tenant?->getTenantKey() === $this->getTenantKey()) {
            tenancy()->tenant->setAttribute('maintenance_mode', $this->maintenance_mode);
        }
    }

    /**
     * Reactiva el tenant quitando el estado de suspensión.
     */
    public function activate(): void
    {
        $this->update(['maintenance_mode' => null]);

        if (tenancy()->initialized && tenancy()->tenant?->getTenantKey() === $this->getTenantKey()) {
            tenancy()->tenant->setAttribute('maintenance_mode', null);
        }
    }
}
