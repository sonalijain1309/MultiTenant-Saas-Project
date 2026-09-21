<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    /**
     * Handle an incoming request and ensure the resolved tenant is active and operational.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'No active tenant context identified.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($tenant->status === TenantStatus::SUSPENDED) {
            return response()->json([
                'message' => 'Tenant account is suspended. Please contact platform support.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($tenant->status === TenantStatus::CANCELED) {
            return response()->json([
                'message' => 'Tenant account has been canceled.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $tenant->isOperational()) {
            return response()->json([
                'message' => 'Tenant account is currently inactive.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
