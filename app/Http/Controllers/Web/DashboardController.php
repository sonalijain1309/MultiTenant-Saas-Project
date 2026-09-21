<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected TenantContext $tenantContext
    ) {
    }

    /**
     * Display the tenant or platform dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // -------------------------------------------------------------
        // Platform Super Admin (Global view when no tenant context)
        // -------------------------------------------------------------
        if ($user->isSuperAdmin() && ! $user->tenant_id) {
            $stats = [
                'total_tenants' => Tenant::count(),
                'total_users' => User::count(),
                'total_customers' => Customer::withoutTenancy()->count(),
                'total_products' => Product::withoutTenancy()->count(),
                'total_orders' => Order::withoutTenancy()->count(),
                'total_revenue' => (float) Order::withoutTenancy()
                    ->where('status', '!=', OrderStatus::CANCELED)
                    ->sum('total_amount'),
            ];

            $recentTenants = Tenant::with('plan')->latest()->take(5)->get();

            return view('dashboard.index', [
                'user' => $user,
                'isSuperAdmin' => true,
                'stats' => $stats,
                'recentTenants' => $recentTenants,
            ]);
        }

        // -------------------------------------------------------------
        // Tenant-Scoped Dashboard (Tenant Owner, Manager, Staff)
        // -------------------------------------------------------------
        $tenant = $user->tenant;

        if ($tenant) {
            $this->tenantContext->setTenant($tenant);
        }

        // Total metrics strictly scoped to active tenant
        $totalCustomers = Customer::where('tenant_id', $tenant?->id)->count();
        $totalProducts = Product::where('tenant_id', $tenant?->id)->count();
        $totalOrders = Order::where('tenant_id', $tenant?->id)->count();
        $totalRevenue = (float) Order::where('tenant_id', $tenant?->id)
            ->where('status', '!=', OrderStatus::CANCELED)
            ->sum('total_amount');

        // Subscription usage & limits
        $plan = $tenant?->plan;
        $subscription = $tenant?->activeSubscription;

        $customerUsage = $tenant ? $this->subscriptionService->getUsage($tenant, 'customers') : 0;
        $customerLimit = $tenant ? $this->subscriptionService->getLimit($tenant, 'customers') : null;

        $productUsage = $tenant ? $this->subscriptionService->getUsage($tenant, 'products') : 0;
        $productLimit = $tenant ? $this->subscriptionService->getLimit($tenant, 'products') : null;

        $orderUsage = $tenant ? $this->subscriptionService->getMonthlyOrderUsage($tenant) : 0;
        $orderLimit = $tenant ? $this->subscriptionService->getLimit($tenant, 'orders') : null;

        // Recent activity tables
        $recentOrders = Order::where('tenant_id', $tenant?->id)
            ->with(['customer', 'items'])
            ->latest('ordered_at')
            ->take(5)
            ->get();

        $recentProducts = Product::where('tenant_id', $tenant?->id)
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.index', [
            'user' => $user,
            'tenant' => $tenant,
            'plan' => $plan,
            'subscription' => $subscription,
            'isSuperAdmin' => false,
            'stats' => [
                'total_customers' => $totalCustomers,
                'total_products' => $totalProducts,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'customer_usage' => $customerUsage,
                'customer_limit' => $customerLimit,
                'customer_percentage' => $customerLimit && $customerLimit > 0 ? min(100, round(($customerUsage / $customerLimit) * 100)) : null,
                'product_usage' => $productUsage,
                'product_limit' => $productLimit,
                'product_percentage' => $productLimit && $productLimit > 0 ? min(100, round(($productUsage / $productLimit) * 100)) : null,
                'order_usage' => $orderUsage,
                'order_limit' => $orderLimit,
                'order_percentage' => $orderLimit && $orderLimit > 0 ? min(100, round(($orderUsage / $orderLimit) * 100)) : null,
            ],
            'recentOrders' => $recentOrders,
            'recentProducts' => $recentProducts,
        ]);
    }
}
