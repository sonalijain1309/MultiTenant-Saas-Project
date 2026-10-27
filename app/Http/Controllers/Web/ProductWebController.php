<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\PlanLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductWebController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected TenantContext $tenantContext
    ) {
    }

    /**
     * Display a paginated listing of products for the active workspace.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $tenant = $this->resolveTenant($request);

        $query = Product::query();

        // Search filter (name, SKU)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Status filter (available, unavailable, out_of_stock)
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            if ($status === 'available' || $status === '1') {
                $query->where('is_available', true);
            } elseif ($status === 'unavailable' || $status === '0') {
                $query->where('is_available', false);
            } elseif ($status === 'out_of_stock') {
                $query->where('stock', '<=', 0);
            }
        }

        $products = $query->latest()->paginate(10)->withQueryString();

        $canCreate = $tenant ? $this->subscriptionService->canCreateProduct($tenant) : true;
        $totalProducts = $tenant ? Product::where('tenant_id', $tenant->id)->count() : 0;
        $productLimit = $tenant ? $this->subscriptionService->getLimit($tenant, 'products') : null;

        return view('products.index', [
            'products' => $products,
            'canCreate' => $canCreate,
            'totalProducts' => $totalProducts,
            'productLimit' => $productLimit,
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Product::class);

        $tenant = $this->resolveTenant($request);
        $canCreate = $tenant ? $this->subscriptionService->canCreateProduct($tenant) : true;
        $productLimit = $tenant ? $this->subscriptionService->getLimit($tenant, 'products') : null;
        $currentCount = $tenant ? $this->subscriptionService->getUsage($tenant, 'products') : 0;

        return view('products.create', [
            'canCreate' => $canCreate,
            'productLimit' => $productLimit,
            'currentCount' => $currentCount,
        ]);
    }

    /**
     * Store a newly created product in database with quota check.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $tenant = $this->resolveTenant($request);

        if ($tenant) {
            try {
                $this->subscriptionService->ensureCanCreate($tenant, 'products');
            } catch (PlanLimitExceededException $e) {
                return back()
                    ->withInput()
                    ->with('error', $e->getMessage());
            }
        }

        $validated = $request->validated();

        // Handle boolean checkbox if omitted or explicit
        if ($request->has('is_available')) {
            $validated['is_available'] = $request->boolean('is_available');
        } else {
            $validated['is_available'] = true;
        }

        $product = Product::create($validated);

        return redirect()
            ->route('products.show', $product)
            ->with('status', "Product [{$product->name}] created successfully.");
    }

    /**
     * Display the specified product and associated sales/order activity.
     */
    public function show(Product $product): View
    {
        Gate::authorize('view', $product);

        // Load recent order items with associated order and customer
        $product->load(['orderItems' => function ($q) {
            $q->latest()->take(10)->with(['order.customer']);
        }]);

        // Aggregate sales metrics for this product
        $totalSold = (int) $product->orderItems()->sum('quantity');
        $totalRevenue = (float) $product->orderItems()->sum('subtotal');
        $ordersCount = $product->orderItems()->distinct('order_id')->count('order_id');

        return view('products.show', [
            'product' => $product,
            'totalSold' => $totalSold,
            'totalRevenue' => $totalRevenue,
            'ordersCount' => $ordersCount,
        ]);
    }

    /**
     * Show the form for editing the product.
     */
    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        return view('products.edit', [
            'product' => $product,
        ]);
    }

    /**
     * Update the specified product in database.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $validated = $request->validated();

        if ($request->has('is_available')) {
            $validated['is_available'] = $request->boolean('is_available');
        }

        $product->update($validated);

        return redirect()
            ->route('products.show', $product)
            ->with('status', "Product [{$product->name}] updated successfully.");
    }

    /**
     * Remove the specified product from database.
     */
    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $productName = $product->name;
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('status', "Product [{$productName}] has been deleted successfully.");
    }

    /**
     * Helper to resolve active tenant and guarantee TenantContext initialization.
     */
    protected function resolveTenant(Request $request)
    {
        $tenant = $this->tenantContext->getTenant() ?? $request->user()->tenant;

        if ($tenant && ! $this->tenantContext->hasTenant()) {
            $this->tenantContext->setTenant($tenant);
        }

        return $tenant;
    }
}
