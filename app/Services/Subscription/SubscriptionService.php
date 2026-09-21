<?php

namespace App\Services\Subscription;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\SubscriptionException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Scopes\TenantScope;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Supported resource keys mapped to Plan limit columns.
     */
    protected const RESOURCE_MAP = [
        'users' => 'max_users',
        'customers' => 'max_customers',
        'products' => 'max_products',
        'orders' => 'max_orders_per_month',
    ];

    /**
     * Get the active subscription for the given tenant.
     */
    public function getActiveSubscription(Tenant $tenant): ?Subscription
    {
        return $tenant->subscriptions()
            ->whereIn('status', [SubscriptionStatus::ACTIVE->value, SubscriptionStatus::TRIALING->value])
            ->latest('starts_at')
            ->first();
    }

    /**
     * Get the latest subscription (regardless of status) for the given tenant.
     */
    public function getLatestSubscription(Tenant $tenant): ?Subscription
    {
        return $tenant->subscriptions()->latest('created_at')->first();
    }

    /**
     * Get the current effective plan for the tenant.
     */
    public function getCurrentPlan(Tenant $tenant): ?Plan
    {
        $activeSubscription = $this->getActiveSubscription($tenant);

        if ($activeSubscription && $activeSubscription->plan) {
            return $activeSubscription->plan;
        }

        return $tenant->plan;
    }

    /**
     * Check if a feature is enabled in the tenant's current plan.
     */
    public function hasFeature(Tenant $tenant, string $feature): bool
    {
        $plan = $this->getCurrentPlan($tenant);

        if (! $plan || ! is_array($plan->features)) {
            return false;
        }

        return ! empty($plan->features[$feature]);
    }

    /**
     * Get the configured limit for a given resource.
     * Returns null if unlimited (-1 or null).
     */
    public function getLimit(Tenant $tenant, string $resource): ?int
    {
        $normalizedResource = strtolower(rtrim($resource, 's')) . 's';

        if (! isset(self::RESOURCE_MAP[$normalizedResource])) {
            return null;
        }

        $column = self::RESOURCE_MAP[$normalizedResource];
        $plan = $this->getCurrentPlan($tenant);

        if (! $plan) {
            return 0;
        }

        $limit = $plan->{$column};

        // Convention: null or -1 represents unlimited capacity
        if ($limit === null || $limit === -1) {
            return null;
        }

        return (int) $limit;
    }

    /**
     * Calculate current tenant usage for a resource.
     */
    public function getUsage(Tenant $tenant, string $resource): int
    {
        $normalizedResource = strtolower(rtrim($resource, 's')) . 's';

        return match ($normalizedResource) {
            'users' => User::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->count(),

            'customers' => Customer::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->count(),

            'products' => Product::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->count(),

            'orders' => $this->getMonthlyOrderUsage($tenant),

            default => 0,
        };
    }

    /**
     * Calculate orders created within the current calendar month (excluding canceled orders).
     */
    public function getMonthlyOrderUsage(Tenant $tenant, ?Carbon $date = null): int
    {
        $currentDate = $date ?? Carbon::now();
        $startOfMonth = $currentDate->copy()->startOfMonth();
        $endOfMonth = $currentDate->copy()->endOfMonth();

        return Order::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->whereBetween('ordered_at', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', OrderStatus::CANCELED->value)
            ->count();
    }

    /**
     * Determine if another resource can be created under the current plan and subscription.
     */
    public function canCreate(Tenant $tenant, string $resource): bool
    {
        try {
            $this->ensureCanCreate($tenant, $resource);
            return true;
        } catch (PlanLimitExceededException | SubscriptionException) {
            return false;
        }
    }

    /**
     * Enforce limit validation, throwing an exception if creation is disallowed.
     *
     * @throws SubscriptionException
     * @throws PlanLimitExceededException
     */
    public function ensureCanCreate(Tenant $tenant, string $resource): void
    {
        $subscription = $this->getLatestSubscription($tenant);

        // 1. Subscription Operational Status Check
        if (! $subscription) {
            throw SubscriptionException::missing();
        }

        if ($subscription->status === SubscriptionStatus::PAST_DUE) {
            throw SubscriptionException::pastDue();
        }

        if ($subscription->status === SubscriptionStatus::CANCELED) {
            throw SubscriptionException::canceled();
        }

        if (! $subscription->status->canCreateResources()) {
            throw new SubscriptionException("Subscription status [{$subscription->status->label()}] does not permit resource creation.");
        }

        // 2. Resource Limit Check
        $normalizedResource = strtolower(rtrim($resource, 's')) . 's';
        $limit = $this->getLimit($tenant, $normalizedResource);

        // Unlimited capacity (-1 or null)
        if ($limit === null) {
            return;
        }

        $currentUsage = $this->getUsage($tenant, $normalizedResource);

        if ($currentUsage >= $limit) {
            $plan = $this->getCurrentPlan($tenant);
            throw new PlanLimitExceededException(
                resource: $normalizedResource,
                current: $currentUsage,
                limit: $limit,
                planName: $plan?->name ?? 'Current Plan'
            );
        }
    }

    /**
     * Resource-specific helper methods.
     */
    public function canCreateUser(Tenant $tenant): bool
    {
        return $this->canCreate($tenant, 'users');
    }

    public function canCreateCustomer(Tenant $tenant): bool
    {
        return $this->canCreate($tenant, 'customers');
    }

    public function canCreateProduct(Tenant $tenant): bool
    {
        return $this->canCreate($tenant, 'products');
    }

    public function canCreateOrder(Tenant $tenant): bool
    {
        return $this->canCreate($tenant, 'orders');
    }

    /**
     * Get a comprehensive usage summary for a tenant (dashboard & API ready).
     */
    public function getUsageSummary(Tenant $tenant): array
    {
        $plan = $this->getCurrentPlan($tenant);
        $subscription = $this->getLatestSubscription($tenant);

        $resources = ['users', 'customers', 'products', 'orders'];
        $usageData = [];

        foreach ($resources as $resource) {
            $limit = $this->getLimit($tenant, $resource);
            $usage = $this->getUsage($tenant, $resource);
            $remaining = $limit !== null ? max(0, $limit - $usage) : null;
            $percentage = ($limit !== null && $limit > 0) ? round(min(100, ($usage / $limit) * 100), 1) : 0;

            $usageData[$resource] = [
                'usage' => $usage,
                'limit' => $limit,
                'is_unlimited' => $limit === null,
                'remaining' => $remaining,
                'percentage' => $percentage,
                'can_create' => $this->canCreate($tenant, $resource),
            ];
        }

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'plan' => [
                'name' => $plan?->name,
                'slug' => $plan?->slug,
                'price' => $plan?->price,
            ],
            'subscription' => [
                'status' => $subscription?->status->value,
                'status_label' => $subscription?->status->label(),
                'is_active' => $subscription?->status->canCreateResources() ?? false,
                'starts_at' => $subscription?->starts_at?->toIso8601String(),
                'ends_at' => $subscription?->ends_at?->toIso8601String(),
            ],
            'usage' => $usageData,
        ];
    }
}
