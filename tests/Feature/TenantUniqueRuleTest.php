<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Rules\TenantUnique;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TenantUniqueRuleTest extends TestCase
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

    public function test_duplicate_value_in_same_tenant_fails_validation(): void
    {
        $this->tenantContext->setTenant($this->tenantA);

        // Apex already has a customer with email 'acme@client-example.com'
        $validator = Validator::make(
            ['email' => 'acme@client-example.com'],
            ['email' => [new TenantUnique('customers', 'email')]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('already been taken', $validator->errors()->first('email'));
    }

    public function test_same_value_in_different_tenants_passes_validation(): void
    {
        // Apex already has 'acme@client-example.com'
        // BlueWave does NOT have 'acme@client-example.com'
        $this->tenantContext->setTenant($this->tenantB);

        $validator = Validator::make(
            ['email' => 'acme@client-example.com'],
            ['email' => [new TenantUnique('customers', 'email')]]
        );

        $this->assertFalse($validator->fails(), 'Same email across different tenants must pass validation');
    }

    public function test_product_sku_uniqueness_scoped_to_tenant(): void
    {
        // Apex has 'APX-CLD-01'
        $this->tenantContext->setTenant($this->tenantA);

        $failValidator = Validator::make(
            ['sku' => 'APX-CLD-01'],
            ['sku' => [new TenantUnique('products', 'sku')]]
        );
        $this->assertTrue($failValidator->fails());

        // BlueWave can use 'APX-CLD-01'
        $this->tenantContext->setTenant($this->tenantB);
        $passValidator = Validator::make(
            ['sku' => 'APX-CLD-01'],
            ['sku' => [new TenantUnique('products', 'sku')]]
        );
        $this->assertFalse($passValidator->fails());
    }

    public function test_update_scenario_ignores_current_record(): void
    {
        $this->tenantContext->setTenant($this->tenantA);

        $existingCustomer = Customer::where('email', 'acme@client-example.com')->firstOrFail();

        // Updating with the exact same email, ignoring the customer's own ID
        $validator = Validator::make(
            ['email' => 'acme@client-example.com'],
            ['email' => [new TenantUnique('customers', 'email', $existingCustomer->id)]]
        );

        $this->assertFalse($validator->fails(), 'Update scenario ignoring current record ID must pass');
    }
}
