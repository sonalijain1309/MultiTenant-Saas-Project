<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $apexOwner;
    protected User $apexManager;
    protected User $apexStaff;
    protected User $blueWaveOwner;
    protected Tenant $apexTenant;
    protected Tenant $blueWaveTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->apexTenant = Tenant::where('slug', 'apex-dynamics')->firstOrFail();
        $this->blueWaveTenant = Tenant::where('slug', 'bluewave-logistics')->firstOrFail();

        $this->apexOwner = User::where('email', 'owner@apex.test')->firstOrFail();
        $this->apexManager = User::where('email', 'manager@apex.test')->firstOrFail();
        $this->apexStaff = User::where('email', 'staff@apex.test')->firstOrFail();
        $this->blueWaveOwner = User::where('email', 'owner@bluewave.test')->firstOrFail();
    }

    public function test_tenant_can_list_own_orders_with_eager_loaded_relations(): void
    {
        $response = $this->actingAs($this->apexOwner)->getJson('/api/v1/orders');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_number',
                    'status',
                    'status_label',
                    'subtotal',
                    'tax_amount',
                    'discount_amount',
                    'total_amount',
                    'notes',
                    'ordered_at',
                    'customer' => ['id', 'name', 'email'],
                    'items' => [
                        '*' => ['id', 'product_id', 'product_name', 'unit_price', 'quantity', 'subtotal'],
                    ],
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

        $orderNumbers = collect($response->json('data'))->pluck('order_number')->all();
        $this->assertContains('ORD-APX-001', $orderNumbers);
        $this->assertNotContains('ORD-BW-001', $orderNumbers);
    }

    public function test_create_order_successfully_with_server_side_calculations_and_stock_decrement(): void
    {
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();
        $prod1 = Product::where('tenant_id', $this->apexTenant->id)->where('sku', 'APX-CLD-01')->firstOrFail(); // price 49.00
        $prod2 = Product::where('tenant_id', $this->apexTenant->id)->where('sku', 'APX-SEC-02')->firstOrFail(); // price 19.00

        $initialStock1 = $prod1->stock;
        $initialStock2 = $prod2->stock;

        $payload = [
            'customer_id' => $customer->id,
            'notes' => 'Urgent deployment for Q3 enterprise rollout.',
            'items' => [
                ['product_id' => $prod1->id, 'quantity' => 2], // 49.00 * 2 = 98.00
                ['product_id' => $prod2->id, 'quantity' => 1], // 19.00 * 1 = 19.00
            ],
        ];

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/orders', $payload);

        $response->assertCreated();

        // Subtotal = 98.00 + 19.00 = 117.00
        // Tax (8%) = round(117 * 0.08, 2) = 9.36
        // Total = 117.00 + 9.36 = 126.36
        $this->assertEquals(117.00, $response->json('data.subtotal'));
        $this->assertEquals(9.36, $response->json('data.tax_amount'));
        $this->assertEquals(126.36, $response->json('data.total_amount'));
        $response->assertJsonPath('data.status', 'pending');

        // Verify order number was generated server-side
        $orderNumber = $response->json('data.order_number');
        $this->assertStringStartsWith('ORD-APEX-', $orderNumber);

        // Verify inventory stock was decremented
        $this->assertEquals($initialStock1 - 2, $prod1->fresh()->stock);
        $this->assertEquals($initialStock2 - 1, $prod2->fresh()->stock);

        // Verify items were stored in database with historical price snapshots
        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $prod1->id,
            'unit_price' => 49.00,
            'quantity' => 2,
            'subtotal' => 98.00,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $prod2->id,
            'unit_price' => 19.00,
            'quantity' => 1,
            'subtotal' => 19.00,
        ]);
    }

    public function test_client_cannot_tamper_with_order_financials_or_order_number(): void
    {
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();
        $product = Product::where('tenant_id', $this->apexTenant->id)->where('sku', 'APX-CLD-01')->firstOrFail();

        $tamperedPayload = [
            'customer_id' => $customer->id,
            'order_number' => 'HACKED-ORD-999',
            'subtotal' => 1.00,      // Attempt to pay $1 instead of $49
            'tax_amount' => 0.00,
            'total_amount' => 1.00,
            'tenant_id' => $this->blueWaveTenant->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 1.00, // Attempt to buy for $1
                    'subtotal' => 1.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/orders', $tamperedPayload);

        $response->assertCreated();

        // The server must IGNORE all spoofed fields and compute real prices
        $this->assertNotEquals('HACKED-ORD-999', $response->json('data.order_number'));
        $this->assertEquals(49.00, $response->json('data.subtotal'));
        $this->assertEquals(3.92, $response->json('data.tax_amount')); // 49 * 0.08 = 3.92
        $this->assertEquals(52.92, $response->json('data.total_amount'));

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'tenant_id' => $this->apexTenant->id,
        ]);
    }

    public function test_cross_tenant_customer_id_is_rejected(): void
    {
        $blueWaveCustomer = Customer::where('tenant_id', $this->blueWaveTenant->id)->firstOrFail();
        $apexProduct = Product::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/orders', [
            'customer_id' => $blueWaveCustomer->id,
            'items' => [
                ['product_id' => $apexProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    }

    public function test_cross_tenant_product_id_is_rejected(): void
    {
        $apexCustomer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();
        $blueWaveProduct = Product::where('tenant_id', $this->blueWaveTenant->id)->firstOrFail();

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/orders', [
            'customer_id' => $apexCustomer->id,
            'items' => [
                ['product_id' => $blueWaveProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_monthly_order_limit_is_enforced(): void
    {
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();
        $product = Product::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        // Restrict plan max orders per month to existing count
        $currentMonthOrders = Order::where('tenant_id', $this->apexTenant->id)
            ->whereYear('ordered_at', now()->year)
            ->whereMonth('ordered_at', now()->month)
            ->count();

        $this->apexTenant->plan->update(['max_orders_per_month' => $currentMonthOrders]);

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertForbidden();
        $response->assertJsonStructure(['message', 'resource', 'current', 'limit', 'plan']);
        $response->assertJsonPath('resource', 'orders');
    }

    public function test_failed_order_creation_rolls_back_entire_transaction(): void
    {
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();
        $prod1 = Product::where('tenant_id', $this->apexTenant->id)->where('sku', 'APX-CLD-01')->firstOrFail();
        $prod2 = Product::where('tenant_id', $this->apexTenant->id)->where('sku', 'APX-SEC-02')->firstOrFail();

        $initialStock1 = $prod1->stock;
        $initialStock2 = $prod2->stock;
        $orderCountBefore = Order::where('tenant_id', $this->apexTenant->id)->count();
        $itemCountBefore = OrderItem::where('tenant_id', $this->apexTenant->id)->count();

        // Product 2 requests quantity far exceeding available stock
        $payload = [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $prod1->id, 'quantity' => 1],
                ['product_id' => $prod2->id, 'quantity' => 999999], // will fail stock validation
            ],
        ];

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/orders', $payload);

        $response->assertStatus(422);

        // Verification of full rollback:
        // 1. No new order created
        $this->assertEquals($orderCountBefore, Order::where('tenant_id', $this->apexTenant->id)->count());
        // 2. No order items created
        $this->assertEquals($itemCountBefore, OrderItem::where('tenant_id', $this->apexTenant->id)->count());
        // 3. Stock of first product was NOT decremented
        $this->assertEquals($initialStock1, $prod1->fresh()->stock);
        $this->assertEquals($initialStock2, $prod2->fresh()->stock);
    }

    public function test_order_status_transitions_and_authorization(): void
    {
        $order = Order::where('tenant_id', $this->apexTenant->id)
            ->where('status', OrderStatus::PROCESSING)
            ->firstOrFail();

        // Staff CANNOT complete an order
        $staffResponse = $this->actingAs($this->apexStaff)->patchJson("/api/v1/orders/{$order->id}", [
            'status' => OrderStatus::COMPLETED->value,
        ]);
        $staffResponse->assertStatus(422);
        $staffResponse->assertJsonValidationErrors(['status']);

        // Manager CAN complete an order
        $managerResponse = $this->actingAs($this->apexManager)->patchJson("/api/v1/orders/{$order->id}", [
            'status' => OrderStatus::COMPLETED->value,
        ]);
        $managerResponse->assertOk();
        $this->assertEquals(OrderStatus::COMPLETED->value, $order->fresh()->status->value);

        // Cannot transition from terminal state (COMPLETED)
        $terminalResponse = $this->actingAs($this->apexOwner)->patchJson("/api/v1/orders/{$order->id}", [
            'status' => OrderStatus::PENDING->value,
        ]);
        $terminalResponse->assertStatus(422);
        $terminalResponse->assertJsonValidationErrors(['status']);
    }

    public function test_canceling_order_restores_inventory_stock(): void
    {
        // First create a new order to ensure clean stock baseline
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();
        $product = Product::where('tenant_id', $this->apexTenant->id)->where('sku', 'APX-CLD-01')->firstOrFail();

        $stockBefore = $product->stock;

        $createResponse = $this->actingAs($this->apexOwner)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);
        $createResponse->assertCreated();
        $orderId = $createResponse->json('data.id');

        // Stock decreased by 3
        $this->assertEquals($stockBefore - 3, $product->fresh()->stock);

        // Cancel the order as Manager
        $cancelResponse = $this->actingAs($this->apexManager)->patchJson("/api/v1/orders/{$orderId}", [
            'status' => OrderStatus::CANCELED->value,
        ]);
        $cancelResponse->assertOk();
        $this->assertEquals(OrderStatus::CANCELED->value, $cancelResponse->json('data.status'));

        // Stock restored back to initial level
        $this->assertEquals($stockBefore, $product->fresh()->stock);
    }

    public function test_delete_order_authorization_by_role(): void
    {
        $order = Order::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        // Staff CANNOT delete order
        $staffResponse = $this->actingAs($this->apexStaff)->deleteJson("/api/v1/orders/{$order->id}");
        $staffResponse->assertForbidden();

        // Manager CANNOT delete order (Only Tenant Owner can delete orders)
        $managerResponse = $this->actingAs($this->apexManager)->deleteJson("/api/v1/orders/{$order->id}");
        $managerResponse->assertForbidden();

        // Owner CAN delete order
        $ownerResponse = $this->actingAs($this->apexOwner)->deleteJson("/api/v1/orders/{$order->id}");
        $ownerResponse->assertOk();
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_cross_tenant_order_access_rejected(): void
    {
        $blueWaveOrder = Order::where('tenant_id', $this->blueWaveTenant->id)->firstOrFail();

        // Show cross-tenant order
        $showResponse = $this->actingAs($this->apexOwner)->getJson("/api/v1/orders/{$blueWaveOrder->id}");
        $showResponse->assertForbidden();

        // Update cross-tenant order
        $updateResponse = $this->actingAs($this->apexOwner)->patchJson("/api/v1/orders/{$blueWaveOrder->id}", [
            'notes' => 'Malicious note',
        ]);
        $updateResponse->assertForbidden();

        // Delete cross-tenant order
        $deleteResponse = $this->actingAs($this->apexOwner)->deleteJson("/api/v1/orders/{$blueWaveOrder->id}");
        $deleteResponse->assertForbidden();
    }

    public function test_order_filtering_by_status_customer_and_order_number(): void
    {
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->where('email', 'acme@client-example.com')->firstOrFail();

        // Filter by customer_id
        $customerFilter = $this->actingAs($this->apexOwner)->getJson("/api/v1/orders?customer_id={$customer->id}");
        $customerFilter->assertOk();
        $this->assertNotEmpty($customerFilter->json('data'));
        foreach ($customerFilter->json('data') as $orderItem) {
            $this->assertEquals($customer->id, $orderItem['customer']['id']);
        }

        // Filter by status
        $statusFilter = $this->actingAs($this->apexOwner)->getJson("/api/v1/orders?status=completed");
        $statusFilter->assertOk();
        foreach ($statusFilter->json('data') as $orderItem) {
            $this->assertEquals('completed', $orderItem['status']);
        }

        // Filter by order number
        $numberFilter = $this->actingAs($this->apexOwner)->getJson('/api/v1/orders?order_number=ORD-APX-001');
        $numberFilter->assertOk();
        $this->assertCount(1, $numberFilter->json('data'));
        $this->assertEquals('ORD-APX-001', $numberFilter->json('data.0.order_number'));
    }
}
