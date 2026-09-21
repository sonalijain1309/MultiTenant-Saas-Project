<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Services\Order\OrderService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrderApiController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {
    }

    /**
     * Display a paginated listing of orders for the active tenant.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $query = Order::with(['customer', 'items']);

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by customer
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        // Filter by order number
        if ($orderNumber = $request->input('order_number')) {
            $query->where('order_number', 'like', "%{$orderNumber}%");
        }

        // Filter by date range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('ordered_at', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('ordered_at', '<=', $toDate);
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $orders = $query->latest('ordered_at')->paginate($perPage);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Store a newly created order using the OrderService.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $tenant = app(TenantContext::class)->getTenant() ?? $request->user()->tenant;

        if (! $tenant) {
            return response()->json([
                'message' => 'Cannot create order: no active tenant context identified.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->orderService->createOrder($tenant, $request->validated());

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => new OrderResource($order),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified order with items and customer.
     */
    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $order->load(['customer', 'items.product']);

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Update the order notes or transition status via OrderService.
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        if ($request->filled('status')) {
            $newStatus = OrderStatus::from($request->input('status'));
            $order = $this->orderService->updateOrderStatus($order, $newStatus, $request->user());
        }

        if ($request->has('notes')) {
            $order->update(['notes' => $request->input('notes')]);
        }

        return response()->json([
            'message' => 'Order updated successfully.',
            'data' => new OrderResource($order->fresh(['customer', 'items.product'])),
        ]);
    }

    /**
     * Remove the specified order.
     */
    public function destroy(Order $order): JsonResponse
    {
        Gate::authorize('delete', $order);

        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully.',
        ]);
    }
}
