<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\PlanLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerWebController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected TenantContext $tenantContext
    ) {
    }

    /**
     * Display a paginated listing of customers for the active workspace.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $tenant = $this->resolveTenant($request);

        $query = Customer::query();

        // Search filter (name, email, phone, company)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Status filter (active, lead, inactive)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $customers = $query->latest()->paginate(10)->withQueryString();

        $canCreate = $tenant ? $this->subscriptionService->canCreateCustomer($tenant) : true;
        $totalCustomers = $tenant ? Customer::where('tenant_id', $tenant->id)->count() : 0;
        $customerLimit = $tenant ? $this->subscriptionService->getLimit($tenant, 'customers') : null;

        return view('customers.index', [
            'customers' => $customers,
            'canCreate' => $canCreate,
            'totalCustomers' => $totalCustomers,
            'customerLimit' => $customerLimit,
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Customer::class);

        $tenant = $this->resolveTenant($request);
        $canCreate = $tenant ? $this->subscriptionService->canCreateCustomer($tenant) : true;
        $customerLimit = $tenant ? $this->subscriptionService->getLimit($tenant, 'customers') : null;
        $currentCount = $tenant ? $this->subscriptionService->getUsage($tenant, 'customers') : 0;

        return view('customers.create', [
            'canCreate' => $canCreate,
            'customerLimit' => $customerLimit,
            'currentCount' => $currentCount,
        ]);
    }

    /**
     * Store a newly created customer in database with quota check.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Gate::authorize('create', Customer::class);

        $tenant = $this->resolveTenant($request);

        if ($tenant) {
            try {
                $this->subscriptionService->ensureCanCreate($tenant, 'customers');
            } catch (PlanLimitExceededException $e) {
                return back()
                    ->withInput()
                    ->with('error', $e->getMessage());
            }
        }

        $customer = Customer::create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', "Customer [{$customer->name}] created successfully.");
    }

    /**
     * Display the specified customer and recent order history.
     */
    public function show(Customer $customer): View
    {
        Gate::authorize('view', $customer);

        $customer->load(['orders' => function ($q) {
            $q->latest('ordered_at')->take(10);
        }]);

        return view('customers.show', [
            'customer' => $customer,
        ]);
    }

    /**
     * Show the form for editing the customer.
     */
    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
        ]);
    }

    /**
     * Update the specified customer in database.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $customer->update($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', "Customer [{$customer->name}] updated successfully.");
    }

    /**
     * Remove the specified customer from database.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $customerName = $customer->name;
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('status', "Customer [{$customerName}] has been deleted successfully.");
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
