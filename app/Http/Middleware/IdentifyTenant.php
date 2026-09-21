<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request and resolve the active tenant context.
     *
     * Resolution rules:
     * 1. If an authenticated user belongs to a tenant, resolve their tenant.
     *    - SECURITY: A tenant user attempting to specify a different X-Tenant-ID header is rejected (403).
     * 2. If an authenticated user is a Super Admin (no tenant_id), they may optionally specify
     *    an X-Tenant-ID header to manage a specific tenant.
     * 3. For unauthenticated requests (e.g., public API routes requiring tenant context),
     *    an X-Tenant-ID header may resolve the tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantContext = app(TenantContext::class);
        $headerTenantId = $request->header('X-Tenant-ID');
        $user = $request->user();

        if ($user) {
            if ($user->tenant_id !== null) {
                // Security check: tenant users cannot spoof or switch tenants via header
                if ($headerTenantId !== null && (int) $headerTenantId !== (int) $user->tenant_id) {
                    return response()->json([
                        'message' => 'Tenant access denied. You cannot switch tenant context.',
                    ], Response::HTTP_FORBIDDEN);
                }

                $tenant = $user->tenant ?? Tenant::find($user->tenant_id);

                if (! $tenant) {
                    return response()->json([
                        'message' => 'Assigned tenant not found.',
                    ], Response::HTTP_NOT_FOUND);
                }

                $tenantContext->setTenant($tenant);
            } elseif ($user->isSuperAdmin() && $headerTenantId !== null) {
                // Super admins may explicitly resolve a tenant for administrative operations
                $tenant = Tenant::find($headerTenantId);

                if (! $tenant) {
                    return response()->json([
                        'message' => 'Specified tenant not found.',
                    ], Response::HTTP_NOT_FOUND);
                }

                $tenantContext->setTenant($tenant);
            }
        } elseif ($headerTenantId !== null) {
            // Unauthenticated requests specifying tenant via header
            $tenant = Tenant::find($headerTenantId);

            if (! $tenant) {
                return response()->json([
                    'message' => 'Specified tenant not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            $tenantContext->setTenant($tenant);
        }

        try {
            return $next($request);
        } finally {
            $tenantContext->clear();
        }
    }
}
