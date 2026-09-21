<?php

namespace Tests\Feature;

use App\Exceptions\TenantContextException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->tenantContext = app(TenantContext::class);
        $this->tenantA = Tenant::where('slug', 'apex-dynamics')->firstOrFail();
        $this->tenantB = Tenant::where('slug', 'bluewave-logistics')->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->tenantContext->clear();
        parent::tearDown();
    }

    public function test_tenant_a_can_only_query_its_own_customers(): void
    {
        $this->tenantContext->setTenant($this->tenantA);

        $customers = Customer::all();

        $this->assertCount(3, $customers);
        foreach ($customers as $customer) {
            $this->assertEquals($this->tenantA->id, $customer->tenant_id);
        }
    }

    public function test_tenant_b_can_only_query_its_own_customers(): void
    {
        $this->tenantContext->setTenant($this->tenantB);

        $customers = Customer::all();

        $this->assertCount(2, $customers);
        foreach ($customers as $customer) {
            $this->assertEquals($this->tenantB->id, $customer->tenant_id);
        }
    }

    public function test_tenant_a_can_only_query_its_own_products_and_orders(): void
    {
        $this->tenantContext->setTenant($this->tenantA);

        $products = Product::all();
        $this->assertCount(3, $products);
        foreach ($products as $product) {
            $this->assertEquals($this->tenantA->id, $product->tenant_id);
        }

        $orders = Order::all();
        $this->assertCount(2, $orders);
        foreach ($orders as $order) {
            $this->assertEquals($this->tenantA->id, $order->tenant_id);
        }
    }

    public function test_model_created_in_tenant_context_automatically_receives_tenant_id(): void
    {
        $this->tenantContext->setTenant($this->tenantA);

        $newCustomer = Customer::create([
            'name' => 'Auto Tenant Customer',
            'email' => 'auto@example.com',
        ]);

        $this->assertEquals($this->tenantA->id, $newCustomer->tenant_id);
        $this->assertDatabaseHas('customers', [
            'id' => $newCustomer->id,
            'tenant_id' => $this->tenantA->id,
            'email' => 'auto@example.com',
        ]);
    }

    public function test_tenant_id_spoofing_is_prevented(): void
    {
        // Tenant A is active
        $this->tenantContext->setTenant($this->tenantA);

        // Attacker attempts to forge tenant_id to Tenant B
        $customer = Customer::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Spoofed Customer',
            'email' => 'spoofed@example.com',
        ]);

        // BelongsToTenant must override and enforce active tenant_id
        $this->assertEquals($this->tenantA->id, $customer->tenant_id);
    }

    public function test_cannot_create_tenant_scoped_model_without_context_unless_explicit(): void
    {
        $this->tenantContext->clear();

        $this->expectException(TenantContextException::class);

        // Attempt creation without any context or tenant_id
        Customer::create([
            'name' => 'Orphan Customer',
            'email' => 'orphan@example.com',
        ]);
    }

    public function test_cross_tenant_read_attempt_returns_null(): void
    {
        // Get Tenant B's customer
        $this->tenantContext->clear();
        $tenantBCustomer = Customer::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenantB->id)
            ->firstOrFail();

        // Switch context to Tenant A
        $this->tenantContext->setTenant($this->tenantA);

        // Attempt to find Tenant B's customer through standard Eloquent lookup
        $result = Customer::find($tenantBCustomer->id);

        $this->assertNull($result, 'Tenant A context must NOT be able to find Tenant B record');
    }

    public function test_cross_tenant_update_attempt_does_not_affect_target_record(): void
    {
        $this->tenantContext->clear();
        $tenantBCustomer = Customer::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenantB->id)
            ->firstOrFail();

        $originalName = $tenantBCustomer->name;

        // Switch to Tenant A
        $this->tenantContext->setTenant($this->tenantA);

        // Malicious attempt to update Tenant B's record via ID
        $affectedRows = Customer::where('id', $tenantBCustomer->id)
            ->update(['name' => 'HACKED_NAME']);

        $this->assertEquals(0, $affectedRows);

        // Verify the original record remains untouched
        $this->tenantContext->clear();
        $freshRecord = Customer::withoutGlobalScope(TenantScope::class)->find($tenantBCustomer->id);
        $this->assertEquals($originalName, $freshRecord->name);
    }

    public function test_cross_tenant_delete_attempt_does_not_affect_target_record(): void
    {
        $this->tenantContext->clear();
        $tenantBCustomer = Customer::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenantB->id)
            ->firstOrFail();

        // Switch to Tenant A
        $this->tenantContext->setTenant($this->tenantA);

        // Malicious attempt to delete Tenant B's record
        $affectedRows = Customer::where('id', $tenantBCustomer->id)->delete();

        $this->assertEquals(0, $affectedRows);

        // Verify the record is still intact
        $this->tenantContext->clear();
        $this->assertDatabaseHas('customers', [
            'id' => $tenantBCustomer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_safe_bypass_mechanism_works_for_system_admin(): void
    {
        $this->tenantContext->setTenant($this->tenantA);

        // Querying with withoutTenancy() scope bypasses the TenantScope
        $allCustomers = Customer::withoutTenancy()->get();

        $this->assertCount(5, $allCustomers); // 3 from Apex + 2 from BlueWave
    }
}
