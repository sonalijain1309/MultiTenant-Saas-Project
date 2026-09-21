<?php

namespace App\Models\Scopes;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TenantScope ensures all queries on tenant-owned models are restricted
 * to the currently active tenant in the TenantContext.
 *
 * Safe Bypass Mechanism:
 * To perform legitimate administrative or system-level queries across all tenants:
 *   Model::withoutGlobalScope(TenantScope::class)->get();
 *   or:
 *   $query->withoutGlobalScope(TenantScope::class);
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantContext = app(TenantContext::class);

        if ($tenantContext->hasTenant()) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantContext->id());
        }
    }
}
