<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {
    }

    /**
     * Create an order with line items, inventory updates, and totals calculation.
     *
     * @param Tenant $tenant
     * @param array $data
     * @return Order
     * @throws ValidationException
     */
    public function createOrder(Tenant $tenant, array $data): Order
    {
        // 1. Enforce monthly order limit on active subscription
        $this->subscriptionService->ensureCanCreate($tenant, 'orders');

        // 2. Validate customer belongs to this tenant
        $customer = Customer::withoutGlobalScope(TenantScope::class)
            ->where('id', $data['customer_id'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_id' => ['The selected customer is invalid or does not belong to your account.'],
            ]);
        }

        // 3. Validate products, availability, and stock within tenant
        $preparedItems = [];
        $subtotal = 0.00;

        // Precompute cumulative quantities per product in case the same product appears multiple times
        $requestedQuantities = [];
        foreach ($data['items'] as $itemInput) {
            $pId = (int) $itemInput['product_id'];
            $requestedQuantities[$pId] = ($requestedQuantities[$pId] ?? 0) + (int) $itemInput['quantity'];
        }

        foreach ($data['items'] as $index => $itemInput) {
            $product = Product::withoutGlobalScope(TenantScope::class)
                ->where('id', $itemInput['product_id'])
                ->where('tenant_id', $tenant->id)
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['The selected product is invalid or does not belong to your account.'],
                ]);
            }

            if (! $product->is_available) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ["Product [{$product->name}] is currently unavailable."],
                ]);
            }

            $quantity = (int) $itemInput['quantity'];

            if ($product->stock < $requestedQuantities[$product->id]) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ["Insufficient stock for [{$product->name}]. Available: {$product->stock}, requested: {$requestedQuantities[$product->id]}."],
                ]);
            }

            // Server-side price resolution (never trust client prices)
            $unitPrice = (float) $product->price;
            $itemSubtotal = round($unitPrice * $quantity, 2);
            $subtotal += $itemSubtotal;

            $preparedItems[] = [
                'product' => $product,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $itemSubtotal,
            ];
        }

        // 4. Calculate Order Totals
        $taxAmount = round($subtotal * 0.08, 2); // 8% standard tax rate
        $discountAmount = 0.00;
        $totalAmount = round($subtotal + $taxAmount - $discountAmount, 2);

        // 5. Generate server-side unique order number
        $orderNumber = $this->generateOrderNumber($tenant);

        // 6. Execute atomic transaction
        return DB::transaction(function () use (
            $tenant,
            $customer,
            $preparedItems,
            $orderNumber,
            $subtotal,
            $taxAmount,
            $discountAmount,
            $totalAmount,
            $data
        ) {
            $order = Order::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'order_number' => $orderNumber,
                'status' => OrderStatus::PENDING,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'ordered_at' => Carbon::now(),
            ]);

            foreach ($preparedItems as $item) {
                OrderItem::create([
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);

                // Decrement inventory stock atomically
                $item['product']->decrement('stock', $item['quantity']);
            }

            return $order->load(['customer', 'items.product']);
        });
    }

    /**
     * Generate a deterministic, tenant-isolated sequential order number.
     * Format: ORD-{PREFIX}-{YEARMONTH}-{SEQ} (e.g., ORD-APX-202609-0001)
     */
    public function generateOrderNumber(Tenant $tenant): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $tenant->slug), 0, 4));
        if (empty($prefix)) {
            $prefix = 'ORD';
        }
        $period = Carbon::now()->format('Ym');

        $basePattern = "ORD-{$prefix}-{$period}-";

        // Query the highest existing sequential number for this tenant and period
        $latestOrder = Order::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('order_number', 'like', "{$basePattern}%")
            ->orderByDesc('id')
            ->first();

        $nextSeq = 1;
        if ($latestOrder && preg_match('/-(\d{4})$/', $latestOrder->order_number, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        $orderNumber = $basePattern . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        // Ensure collision safety under concurrent requests
        while (Order::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('order_number', $orderNumber)
            ->exists()) {
            $nextSeq++;
            $orderNumber = $basePattern . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
        }

        return $orderNumber;
    }

    /**
     * Transition order status with business rules and role authorization.
     *
     * @throws ValidationException
     */
    public function updateOrderStatus(Order $order, OrderStatus $newStatus, User $user): Order
    {
        $currentStatus = $order->status;

        // Terminal states cannot be changed
        if (in_array($currentStatus, [OrderStatus::COMPLETED, OrderStatus::CANCELED], true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot change status of an order that is already [{$currentStatus->label()}]."],
            ]);
        }

        // Canceling or completing an order requires Manager, Tenant Owner, or Super Admin
        if (in_array($newStatus, [OrderStatus::COMPLETED, OrderStatus::CANCELED], true) && ! $user->canManageUsers()) {
            throw ValidationException::withMessages([
                'status' => ["Only Managers or Tenant Owners can mark orders as [{$newStatus->label()}]."],
            ]);
        }

        return DB::transaction(function () use ($order, $newStatus) {
            // If order is being canceled, restore inventory stock
            if ($newStatus === OrderStatus::CANCELED) {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        Product::withoutGlobalScope(TenantScope::class)
                            ->where('id', $item->product_id)
                            ->increment('stock', $item->quantity);
                    }
                }
            }

            $order->update(['status' => $newStatus]);
            return $order->fresh(['customer', 'items.product']);
        });
    }
}
