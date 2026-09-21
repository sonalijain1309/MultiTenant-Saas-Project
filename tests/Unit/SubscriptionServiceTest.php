<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $service;
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Plan $basicPlan;
    protected Plan $freePlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->service = new SubscriptionService();
        $this->tenantA = Tenant::where('slug', 'apex-dynamics')->firstOrFail();
        $this->tenantB = Tenant::where('slug', 'bluewave-logistics')->firstOrFail();
        $this->basicPlan = Plan::where('slug', 'basic')->firstOrFail();
        $this->freePlan = Plan::where('slug', 'free')->firstOrFail();
    }

    public function test_get_active_subscription_and_current_plan(): void
    {
        $activeSub = $this->service->getActiveSubscription($this->tenantA);
        $this->assertNotNull($activeSub);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $activeSub->status);

        $plan = $this->service->getCurrentPlan($this->tenantA);
        $this->assertEquals('basic', $plan->slug);
    }

    public function test_has_feature_check(): void
    {
        // Apex is on Basic plan which has 'api_access' => true and 'custom_domain' => false
        $this->assertTrue($this->service->hasFeature($this->tenantA, 'api_access'));
        $this->assertFalse($this->service->hasFeature($this->tenantA, 'custom_domain'));
    }

    public function test_get_limit_resolves_correctly(): void
    {
        $this->assertEquals(5, $this->service->getLimit($this->tenantA, 'users'));
        $this->assertEquals(100, $this->service->getLimit($this->tenantA, 'customers'));
        $this->assertEquals(50, $this->service->getLimit($this->tenantA, 'products'));
        $this->assertEquals(250, $this->service->getLimit($this->tenantA, 'orders'));

        // Free plan limits
        $this->assertEquals(1, $this->service->getLimit($this->tenantB, 'users'));
        $this->assertEquals(10, $this->service->getLimit($this->tenantB, 'customers'));
        $this->assertEquals(5, $this->service->getLimit($this->tenantB, 'products'));
        $this->assertEquals(20, $this->service->getLimit($this->tenantB, 'orders'));
    }

    public function test_usage_calculation_is_strictly_tenant_isolated(): void
    {
        // Apex has 3 customers, 3 products, 3 users
        $this->assertEquals(3, $this->service->getUsage($this->tenantA, 'customers'));
        $this->assertEquals(3, $this->service->getUsage($this->tenantA, 'products'));
        $this->assertEquals(3, $this->service->getUsage($this->tenantA, 'users'));

        // BlueWave has 2 customers, 2 products, 2 users
        $this->assertEquals(2, $this->service->getUsage($this->tenantB, 'customers'));
        $this->assertEquals(2, $this->service->getUsage($this->tenantB, 'products'));
        $this->assertEquals(2, $this->service->getUsage($this->tenantB, 'users'));
    }

    public function test_monthly_order_usage_ignores_previous_months_and_canceled_orders(): void
    {
        $now = Carbon::now();

        // Add an order in current month
        Order::create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->tenantA->customers->first()->id,
            'order_number' => 'ORD-CURR-01',
            'status' => OrderStatus::COMPLETED,
            'total_amount' => 50,
            'ordered_at' => $now,
        ]);

        // Add an order from 2 months ago (must not count towards monthly usage)
        Order::create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->tenantA->customers->first()->id,
            'order_number' => 'ORD-OLD-01',
            'status' => OrderStatus::COMPLETED,
            'total_amount' => 50,
            'ordered_at' => $now->copy()->subMonths(2),
        ]);

        // Add a canceled order in current month (must not count towards monthly usage)
        Order::create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->tenantA->customers->first()->id,
            'order_number' => 'ORD-CANC-01',
            'status' => OrderStatus::CANCELED,
            'total_amount' => 50,
            'ordered_at' => $now,
        ]);

        // Total orders in DB for Apex is higher, but monthly active orders must count only current month non-canceled
        $monthlyCount = $this->service->getMonthlyOrderUsage($this->tenantA, $now);

        // From seeder: Apex has 2 orders from this month + 1 newly added = 3
        $this->assertEquals(3, $monthlyCount);
    }

    public function test_usage_summary_returns_well_structured_data(): void
    {
        $summary = $this->service->getUsageSummary($this->tenantA);

        $this->assertArrayHasKey('tenant', $summary);
        $this->assertArrayHasKey('plan', $summary);
        $this->assertArrayHasKey('subscription', $summary);
        $this->assertArrayHasKey('usage', $summary);

        $this->assertEquals('Apex Dynamics', $summary['tenant']['name']);
        $this->assertEquals('Basic', $summary['plan']['name']);
        $this->assertTrue($summary['subscription']['is_active']);

        $productUsage = $summary['usage']['products'];
        $this->assertEquals(3, $productUsage['usage']);
        $this->assertEquals(50, $productUsage['limit']);
        $this->assertEquals(47, $productUsage['remaining']);
        $this->assertTrue($productUsage['can_create']);
    }
}
