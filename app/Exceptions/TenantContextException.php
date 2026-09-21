<?php

namespace App\Exceptions;

use RuntimeException;

class TenantContextException extends RuntimeException
{
    public static function missingTenant(string $modelClass): self
    {
        return new self("Cannot create [{$modelClass}] without an active tenant context. Ensure a tenant is identified or explicitly assign tenant_id.");
    }

    public static function accessDenied(string $message = 'Tenant access denied.'): self
    {
        return new self($message);
    }
}
