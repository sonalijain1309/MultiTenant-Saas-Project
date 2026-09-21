<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseAndRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_plans_are_seeded_with_correct_limits(): void
    {
        $this->assertDatabaseCount('plans', 3);

        $free = Plan::where('slug', 'free')->first();
        $this->assertNotNull($free);
        $this->assertEquals(0.00, $free->price);
        $this->assertEquals(1, $free->max_users);
        $this->assertEquals(10, $free->max_customers);
        $this->assertEquals(5, $free->max_products);
        $this->assertEquals(20, $free->max_orders_per_month);

        $basic = Plan::where('slug', 'basic')->first();
        $this->assertNotNull($basic);
        $this->assertEquals(29.00, $basic->price);
        $this->assertEquals(5, $basic->max_users);

        $pro = Plan::where('slug', 'professional')->first();
        $this->assertNotNull($pro);
        $this->assertEquals(79.00, $pro->price);
        $this->assertEquals(25, $pro->max_users);
    }

    public function test_super_admin_is_seeded_with_correct_role_and_null_tenant(): void
    {
        $admin = User::where('email', 'admin@saas-platform.test')->first();

        $this->assertNotNull($admin);
        $this->assertNull($admin->tenant_id);
        $this->assertEquals(UserRole::SUPER_ADMIN, $admin->role);
        $this->assertTrue($admin->isSuperAdmin());
    }

    public function test_demo_tenants_and_relationships_are_isolated(): void
    {
        // 1. Verify two tenants
        $apex = Tenant::where('slug', 'apex-dynamics')->first();
        $blueWave = Tenant::where('slug', 'bluewave-logistics')->first();

        $this->assertNotNull($apex);
        $this->assertNotNull($blueWave);
        $this->assertEquals(TenantStatus::ACTIVE, $apex->status);
        $this->assertEquals(TenantStatus::ACTIVE, $blueWave->status);

        // 2. Verify Apex Dynamics isolation
        $this->assertEquals('basic', $apex->plan->slug);
        $this->assertCount(3, $apex->users);
        $this->assertCount(3, $apex->customers);
        $this->assertCount(3, $apex->products);
        $this->assertCount(2, $apex->orders);

        // Check Apex order items relationship
        $apexOrder1 = $apex->orders()->where('order_number', 'ORD-APX-001')->first();
        $this->assertNotNull($apexOrder1);
        $this->assertCount(2, $apexOrder1->items);
        $this->assertEquals(OrderStatus::COMPLETED, $apexOrder1->status);

        // 3. Verify BlueWave Logistics isolation
        $this->assertEquals('free', $blueWave->plan->slug);
        $this->assertCount(2, $blueWave->users);
        $this->assertCount(2, $blueWave->customers);
        $this->assertCount(2, $blueWave->products);
        $this->assertCount(1, $blueWave->orders);

        // 4. Cross-tenant isolation verification
        // Ensure none of BlueWave's customers belong to Apex
        $blueWaveCustomerIds = $blueWave->customers->pluck('id');
        $apexCustomerIds = $apex->customers->pluck('id');
        $this->assertEmpty($blueWaveCustomerIds->intersect($apexCustomerIds));

        // Ensure none of BlueWave's products belong to Apex
        $blueWaveProductSkus = $blueWave->products->pluck('sku');
        $apexProductSkus = $apex->products->pluck('sku');
        $this->assertEmpty($blueWaveProductSkus->intersect($apexProductSkus));
    }

    public function test_tenant_subscription_relationship(): void
    {
        $apex = Tenant::where('slug', 'apex-dynamics')->first();
        $activeSub = $apex->activeSubscription;

        $this->assertNotNull($activeSub);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $activeSub->status);
        $this->assertEquals($apex->plan_id, $activeSub->plan_id);
    }
}
