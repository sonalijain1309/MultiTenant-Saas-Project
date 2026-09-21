<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;

class TenantContext
{
    /**
     * Currently active tenant instance.
     */
    protected ?Tenant $tenant = null;

    /**
     * Set the current tenant.
     */
    public function setTenant(Tenant $tenant): self
    {
        $this->tenant = $tenant;
        return $this;
    }

    /**
     * Get the currently resolved tenant.
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the ID of the currently resolved tenant.
     */
    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    /**
     * Determine if a tenant is currently resolved.
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Clear the resolved tenant context.
     */
    public function clear(): void
    {
        $this->tenant = null;
    }
}
