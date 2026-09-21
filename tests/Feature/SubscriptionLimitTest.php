<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\SubscriptionException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SubscriptionLimitTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $subscriptionService;
    protected TenantContext $tenantContext;
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Plan $freePlan;
    protected Plan $basicPlan;
    protected Plan $proPlan;
    protected User $tenantAUser;
    protected User $tenantBUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->subscriptionService = new SubscriptionService();
        $this->tenantContext = app(TenantContext::class);
        $this->tenantA = Tenant::where('slug', 'apex-dynamics')->firstOrFail();
        $this->tenantB = Tenant::where('slug', 'bluewave-logistics')->firstOrFail();
        $this->freePlan = Plan::where('slug', 'free')->firstOrFail();
        $this->basicPlan = Plan::where('slug', 'basic')->firstOrFail();
        $this->proPlan = Plan::where('slug', 'professional')->firstOrFail();
        $this->tenantAUser = User::where('email', 'owner@apex.test')->firstOrFail();
        $this->tenantBUser = User::where('email', 'owner@bluewave.test')->firstOrFail();

        // Register test endpoint guarded by tenant context and plan limit middleware
        Route::middleware(['tenant.identify', 'tenant.active', 'plan.limit:products'])
            ->post('/_test/products', function () {
                $product = Product::create([
                    'sku' => 'TEST-SKU-' . uniqid(),
                    'name' => 'Sample Test Product',
                    'price' => 19.99,
                ]);
                return response()->json(['product_id' => $product->id], 201);
            });
    }

    protected function tearDown(): void
    {
        $this->tenantContext->clear();
        parent::tearDown();
    }

    public function test_product_limit_is_enforced(): void
    {
        // BlueWave is on Free plan (max 5 products). Currently has 2 products.
        $this->tenantContext->setTenant($this->tenantB);
        $this->assertEquals(2, $this->subscriptionService->getUsage($this->tenantB, 'products'));

        // Fill up to the limit (3 more products => total 5)
        Product::create(['sku' => 'BW-TEST-01', 'name' => 'P1', 'price' => 10]);
        Product::create(['sku' => 'BW-TEST-02', 'name' => 'P2', 'price' => 10]);
        Product::create(['sku' => 'BW-TEST-03', 'name' => 'P3', 'price' => 10]);

        $this->assertEquals(5, $this->subscriptionService->getUsage($this->tenantB, 'products'));

        // At limit (5/5), canCreateProduct should be false
        $this->assertFalse($this->subscriptionService->canCreateProduct($this->tenantB));

        // Attempting to enforce beyond limit must throw PlanLimitExceededException
        $this->expectException(PlanLimitExceededException::class);
        $this->subscriptionService->ensureCanCreate($this->tenantB, 'products');
    }

    public function test_customer_limit_is_enforced(): void
    {
        // BlueWave is on Free plan (max 10 customers). Currently has 2 customers.
        $this->tenantContext->setTenant($this->tenantB);

        // Add 8 more to reach limit of 10
        for ($i = 1; $i <= 8; $i++) {
            Customer::create(['name' => "Customer {$i}", 'email' => "test{$i}@bw.test"]);
        }

        $this->assertEquals(10, $this->subscriptionService->getUsage($this->tenantB, 'customers'));
        $this->assertFalse($this->subscriptionService->canCreateCustomer($this->tenantB));

        $this->expectException(PlanLimitExceededException::class);
        $this->subscriptionService->ensureCanCreate($this->tenantB, 'customers');
    }

    public function test_user_limit_is_enforced(): void
    {
        // BlueWave is on Free plan (max 1 user). Currently has 2 seeded users (owner + staff).
        // It has already reached/exceeded the Free user limit (2 >= 1).
        $this->assertFalse($this->subscriptionService->canCreateUser($this->tenantB));

        $this->expectException(PlanLimitExceededException::class);
        $this->subscriptionService->ensureCanCreate($this->tenantB, 'users');
    }

    public function test_monthly_order_limit_counts_only_current_month(): void
    {
        // BlueWave is on Free plan (max 20 orders/month).
        // Seeder gave it 1 order in the current month.
        $this->tenantContext->setTenant($this->tenantB);

        $initialMonthlyOrders = $this->subscriptionService->getMonthlyOrderUsage($this->tenantB);
        $this->assertEquals(1, $initialMonthlyOrders);

        // Add an order with ordered_at in previous month
        Order::create([
            'customer_id' => $this->tenantB->customers->first()->id,
            'order_number' => 'ORD-OLD-MONTH',
            'status' => OrderStatus::COMPLETED,
            'total_amount' => 10,
            'ordered_at' => Carbon::now()->subMonthNoOverflow(),
        ]);

        // Monthly count must remain 1
        $this->assertEquals(1, $this->subscriptionService->getMonthlyOrderUsage($this->tenantB));

        // Create 18 more current-month orders to reach 19
        for ($i = 1; $i <= 18; $i++) {
            Order::create([
                'customer_id' => $this->tenantB->customers->first()->id,
                'order_number' => "ORD-BW-M-{$i}",
                'status' => OrderStatus::COMPLETED,
                'total_amount' => 10,
                'ordered_at' => Carbon::now(),
            ]);
        }

        // At 19 orders out of 20, can still create
        $this->assertEquals(19, $this->subscriptionService->getMonthlyOrderUsage($this->tenantB));
        $this->assertTrue($this->subscriptionService->canCreateOrder($this->tenantB));

        // Create 20th order => reaches limit exactly
        Order::create([
            'customer_id' => $this->tenantB->customers->first()->id,
            'order_number' => 'ORD-BW-M-20',
            'status' => OrderStatus::COMPLETED,
            'total_amount' => 10,
            'ordered_at' => Carbon::now(),
        ]);

        $this->assertEquals(20, $this->subscriptionService->getMonthlyOrderUsage($this->tenantB));
        $this->assertFalse($this->subscriptionService->canCreateOrder($this->tenantB));

        $this->expectException(PlanLimitExceededException::class);
        $this->subscriptionService->ensureCanCreate($this->tenantB, 'orders');
    }

    public function test_api_middleware_blocks_direct_requests_with_exact_json(): void
    {
        $this->tenantContext->setTenant($this->tenantB);

        // Max products on Free plan is 5. BlueWave has 2, add 3 more to max it out:
        Product::create(['sku' => 'BW-MAX-1', 'name' => 'P1', 'price' => 10]);
        Product::create(['sku' => 'BW-MAX-2', 'name' => 'P2', 'price' => 10]);
        Product::create(['sku' => 'BW-MAX-3', 'name' => 'P3', 'price' => 10]);

        // Direct API call from Tenant B user to create product
        $response = $this->actingAs($this->tenantBUser)
            ->postJson('/_test/products');

        $response->assertForbidden();
        $response->assertJson([
            'message' => 'Product limit reached for your current plan.',
            'resource' => 'products',
            'current' => 5,
            'limit' => 5,
            'plan' => 'Free',
        ]);
    }

    public function test_past_due_subscription_blocks_resource_creation(): void
    {
        $sub = $this->tenantA->activeSubscription;
        $sub->update(['status' => SubscriptionStatus::PAST_DUE]);

        $this->assertFalse($this->subscriptionService->canCreateProduct($this->tenantA));

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Subscription is past due');
        $this->subscriptionService->ensureCanCreate($this->tenantA, 'products');
    }

    public function test_canceled_subscription_blocks_resource_creation(): void
    {
        $sub = $this->tenantA->activeSubscription;
        $sub->update(['status' => SubscriptionStatus::CANCELED]);

        $this->assertFalse($this->subscriptionService->canCreateProduct($this->tenantA));

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Subscription has been canceled');
        $this->subscriptionService->ensureCanCreate($this->tenantA, 'products');
    }

    public function test_trialing_subscription_allows_resource_creation(): void
    {
        $sub = $this->tenantA->activeSubscription;
        $sub->update(['status' => SubscriptionStatus::TRIALING]);

        $this->assertTrue($this->subscriptionService->canCreateProduct($this->tenantA));
    }

    public function test_tenant_upgrade_scenario(): void
    {
        $this->tenantContext->setTenant($this->tenantB);

        // 1. Max out BlueWave's Free product limit (5/5)
        Product::create(['sku' => 'BW-UP-1', 'name' => 'P1', 'price' => 10]);
        Product::create(['sku' => 'BW-UP-2', 'name' => 'P2', 'price' => 10]);
        Product::create(['sku' => 'BW-UP-3', 'name' => 'P3', 'price' => 10]);

        $this->assertFalse($this->subscriptionService->canCreateProduct($this->tenantB));

        // 2. Tenant upgrades from Free to Basic (50 products allowed)
        $this->tenantB->update(['plan_id' => $this->basicPlan->id]);
        $this->tenantB->activeSubscription->update(['plan_id' => $this->basicPlan->id]);

        // 3. Verify that creation is now permitted
        $this->assertTrue($this->subscriptionService->canCreateProduct($this->tenantB));
        $this->assertEquals(50, $this->subscriptionService->getLimit($this->tenantB, 'products'));
        $this->assertEquals(5, $this->subscriptionService->getUsage($this->tenantB, 'products'));

        // Can create 6th product without exception
        $sixthProduct = Product::create(['sku' => 'BW-UP-4', 'name' => 'P4', 'price' => 10]);
        $this->assertNotNull($sixthProduct);
        $this->assertEquals(6, $this->subscriptionService->getUsage($this->tenantB, 'products'));
    }
}
