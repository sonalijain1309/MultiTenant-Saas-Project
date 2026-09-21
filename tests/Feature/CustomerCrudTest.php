<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
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

    public function test_tenant_can_list_own_customers(): void
    {
        $response = $this->actingAs($this->apexOwner)->getJson('/api/v1/customers');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'phone', 'company_name', 'address', 'city', 'status', 'created_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

        $customerEmails = collect($response->json('data'))->pluck('email')->all();
        $this->assertContains('acme@client-example.com', $customerEmails);
        $this->assertNotContains('harbor@client-example.com', $customerEmails);
    }

    public function test_tenant_cannot_see_another_tenants_customers(): void
    {
        $blueWaveCustomer = Customer::where('tenant_id', $this->blueWaveTenant->id)->firstOrFail();

        // Direct show endpoint with another tenant's customer ID is rejected by policy (403)
        $response = $this->actingAs($this->apexOwner)->getJson("/api/v1/customers/{$blueWaveCustomer->id}");
        $response->assertForbidden();
    }

    public function test_create_customer_succeeds(): void
    {
        $payload = [
            'name' => 'John Global Logistics',
            'email' => 'john@logistics-corp.test',
            'phone' => '+1-555-9999',
            'company_name' => 'Logistics Global Inc',
            'city' => 'Atlanta',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/customers', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'john@logistics-corp.test');
        $response->assertJsonPath('data.name', 'John Global Logistics');

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->apexTenant->id,
            'email' => 'john@logistics-corp.test',
        ]);
    }

    public function test_customer_creation_enforces_subscription_limit(): void
    {
        // Apex is on Basic Plan (max 50 customers). Set plan limit to current count to test enforcement
        $currentCount = Customer::where('tenant_id', $this->apexTenant->id)->count();
        $this->apexTenant->plan->update(['max_customers' => $currentCount]);

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/customers', [
            'name' => 'Excess Customer',
            'email' => 'excess@customer.test',
        ]);

        $response->assertForbidden();
        $response->assertJsonStructure(['message', 'resource', 'current', 'limit', 'plan']);
        $response->assertJsonPath('resource', 'customers');
    }

    public function test_duplicate_email_in_same_tenant_fails_validation(): void
    {
        // 'acme@client-example.com' already exists in Apex
        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/customers', [
            'name' => 'Acme Duplicate',
            'email' => 'acme@client-example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_same_email_in_different_tenant_succeeds(): void
    {
        // 'acme@client-example.com' exists in Apex, but BlueWave should be able to create a customer with same email
        $response = $this->actingAs($this->blueWaveOwner)->postJson('/api/v1/customers', [
            'name' => 'Acme BlueWave Client',
            'email' => 'acme@client-example.com',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'acme@client-example.com');

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->blueWaveTenant->id,
            'email' => 'acme@client-example.com',
        ]);
    }

    public function test_update_customer_succeeds_and_retains_same_email(): void
    {
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        $response = $this->actingAs($this->apexOwner)->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Acme Corp Updated Name',
            'email' => $customer->email, // keeping current email should pass TenantUnique ignore check
            'city' => 'San Jose',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Acme Corp Updated Name');
        $response->assertJsonPath('data.city', 'San Jose');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Acme Corp Updated Name',
            'city' => 'San Jose',
        ]);
    }

    public function test_delete_customer_authorization_by_role(): void
    {
        $customer1 = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        // Staff CANNOT delete customer
        $responseStaff = $this->actingAs($this->apexStaff)->deleteJson("/api/v1/customers/{$customer1->id}");
        $responseStaff->assertForbidden();

        // Manager CAN delete customer
        $responseManager = $this->actingAs($this->apexManager)->deleteJson("/api/v1/customers/{$customer1->id}");
        $responseManager->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $customer1->id]);

        // Owner CAN delete customer
        $customer2 = Customer::where('tenant_id', $this->apexTenant->id)->whereNull('deleted_at')->firstOrFail();
        $responseOwner = $this->actingAs($this->apexOwner)->deleteJson("/api/v1/customers/{$customer2->id}");
        $responseOwner->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $customer2->id]);
    }

    public function test_cross_tenant_customer_manipulation_rejected(): void
    {
        $blueWaveCustomer = Customer::where('tenant_id', $this->blueWaveTenant->id)->firstOrFail();

        // Apex user cannot update BlueWave customer (403)
        $updateResponse = $this->actingAs($this->apexOwner)->putJson("/api/v1/customers/{$blueWaveCustomer->id}", [
            'name' => 'Hacked Name',
        ]);
        $updateResponse->assertForbidden();

        // Apex user cannot delete BlueWave customer (403)
        $deleteResponse = $this->actingAs($this->apexOwner)->deleteJson("/api/v1/customers/{$blueWaveCustomer->id}");
        $deleteResponse->assertForbidden();
    }

    public function test_tenant_id_cannot_be_spoofed_in_customer_creation(): void
    {
        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/customers', [
            'name' => 'Spoof Attempt Customer',
            'email' => 'spoof@test.com',
            'tenant_id' => $this->blueWaveTenant->id, // Malicious attempt to assign to another tenant
        ]);

        $response->assertCreated();
        $createdId = $response->json('data.id');

        // Verify it was assigned to Apex and NOT BlueWave
        $this->assertDatabaseHas('customers', [
            'id' => $createdId,
            'tenant_id' => $this->apexTenant->id,
        ]);
        $this->assertDatabaseMissing('customers', [
            'id' => $createdId,
            'tenant_id' => $this->blueWaveTenant->id,
        ]);
    }

    public function test_customer_search_and_pagination(): void
    {
        // Search by name
        $searchResponse = $this->actingAs($this->apexOwner)->getJson('/api/v1/customers?search=Acme');
        $searchResponse->assertOk();
        $this->assertCount(1, $searchResponse->json('data'));
        $this->assertEquals('Acme Corp', $searchResponse->json('data.0.name'));

        // Pagination with per_page
        $pageResponse = $this->actingAs($this->apexOwner)->getJson('/api/v1/customers?per_page=2&page=1');
        $pageResponse->assertOk();
        $this->assertCount(2, $pageResponse->json('data'));
        $this->assertEquals(2, $pageResponse->json('meta.per_page'));
    }
}
