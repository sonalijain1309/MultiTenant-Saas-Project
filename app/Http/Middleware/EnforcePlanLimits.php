<?php

namespace App\Http\Middleware;

use App\Exceptions\SubscriptionException;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlanLimits
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {
    }

    /**
     * Handle an incoming request and enforce subscription quotas.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $resource ('users', 'customers', 'products', 'orders')
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $tenant = app(TenantContext::class)->getTenant() ?? $request->user()?->tenant;

        if (! $tenant) {
            return response()->json([
                'message' => 'No active tenant context for subscription enforcement.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->subscriptionService->ensureCanCreate($tenant, $resource);

        return $next($request);
    }
}
