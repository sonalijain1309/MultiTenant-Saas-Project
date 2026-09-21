<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\V1\CustomerResource;
use App\Models\Customer;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CustomerApiController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {
    }

    /**
     * Display a paginated listing of customers for the active tenant.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $query = Customer::query();

        // Search filter (name, email, phone)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $customers = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => CustomerResource::collection($customers),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    /**
     * Store a new customer under the active tenant, enforcing subscription quota.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $tenant = app(TenantContext::class)->getTenant() ?? $request->user()->tenant;

        if ($tenant) {
            $this->subscriptionService->ensureCanCreate($tenant, 'customers');
        }

        $customer = Customer::create($request->validated());

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => new CustomerResource($customer),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        return response()->json([
            'data' => new CustomerResource($customer),
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        $customer->update($request->validated());

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => new CustomerResource($customer->fresh()),
        ]);
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }
}
