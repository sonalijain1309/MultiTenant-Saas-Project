<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantContextException;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait BelongsToTenant
 *
 * Automatically applies the TenantScope and handles automatic tenant_id
 * assignment during model creation.
 */
trait BelongsToTenant
{
    /**
     * Boot the trait: register global scope and model creation listener.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            $tenantContext = app(TenantContext::class);

            if ($tenantContext->hasTenant()) {
                // Security: Enforce active tenant context, preventing tenant_id spoofing
                $model->tenant_id = $tenantContext->id();
            } elseif (empty($model->tenant_id)) {
                // Prevent accidental creation of tenant-scoped records without an active tenant
                throw TenantContextException::missingTenant(static::class);
            }
        });
    }

    /**
     * Relationship to the owning Tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to bypass tenant filtering for system or super admin operations.
     */
    public function scopeWithoutTenancy(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
