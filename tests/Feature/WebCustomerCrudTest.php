<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebCustomerCrudTest extends TestCase
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

    public function test_guest_is_redirected_to_login_when_accessing_customers(): void
    {
        $response = $this->get('/customers');
        $response->assertRedirect('/login');

        $createResponse = $this->get('/customers/create');
        $createResponse->assertRedirect('/login');

        $postResponse = $this->post('/customers', ['name' => 'Guest Customer']);
        $postResponse->assertRedirect('/login');
    }

    public function test_authenticated_tenant_user_can_view_customers_list(): void
    {
        $response = $this->actingAs($this->apexOwner)->get('/customers');

        $response->assertOk();
        $response->assertSee('Customer Directory');
        $response->assertSee('Acme Corp');
        $response->assertSee('Stellar Tech');

        // BlueWave customer must NOT be visible on Apex list
        $response->assertDontSee('Harbor Freight Inc');
    }

    public function test_customer_search_and_filtering_works(): void
    {
        // Search by name
        $searchResponse = $this->actingAs($this->apexOwner)->get('/customers?search=Acme');
        $searchResponse->assertOk();
        $searchResponse->assertSee('Acme Corp');
        $searchResponse->assertDontSee('Stellar Tech');

        // Filter by status
        $statusResponse = $this->actingAs($this->apexOwner)->get('/customers?status=active');
        $statusResponse->assertOk();
        $statusResponse->assertSee('Acme Corp');
    }

    public function test_tenant_user_can_view_create_customer_page(): void
    {
        $response = $this->actingAs($this->apexOwner)->get('/customers/create');

        $response->assertOk();
        $response->assertSee('Add New Customer');
        $response->assertSee('Customer / Contact Name');
        $response->assertSee('Email Address');
    }

    public function test_tenant_can_create_customer_with_valid_data(): void
    {
        $payload = [
            'name' => 'Delta Aerospace',
            'email' => 'contact@delta-aero.test',
            'phone' => '+1-555-8888',
            'company_name' => 'Delta Aerospace Inc',
            'city' => 'Seattle',
            'address' => '700 Aviation Way',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->apexOwner)->post('/customers', $payload);

        $customer = Customer::where('email', 'contact@delta-aero.test')->first();
        $this->assertNotNull($customer);
        $this->assertEquals($this->apexTenant->id, $customer->tenant_id);

        $response->assertRedirect(route('customers.show', $customer));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->apexTenant->id,
            'email' => 'contact@delta-aero.test',
            'company_name' => 'Delta Aerospace Inc',
        ]);
    }

    public function test_customer_creation_enforces_plan_quota(): void
    {
        // Set Apex plan customer limit to current count
        $currentCount = Customer::where('tenant_id', $this->apexTenant->id)->count();
        $this->apexTenant->plan->update(['max_customers' => $currentCount]);

        $response = $this->actingAs($this->apexOwner)->post('/customers', [
            'name' => 'Over Quota Customer',
            'email' => 'overquota@test.com',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('customers', [
            'email' => 'overquota@test.com',
        ]);
    }

    public function test_customer_creation_validates_required_fields_and_tenant_email_uniqueness(): void
    {
        // 1. Missing required name & email
        $response = $this->actingAs($this->apexOwner)->post('/customers', []);
        $response->assertSessionHasErrors(['name', 'email']);

        // 2. Duplicate email within same tenant ('acme@client-example.com' exists in Apex)
        $duplicateResponse = $this->actingAs($this->apexOwner)->post('/customers', [
            'name' => 'Duplicate Acme',
            'email' => 'acme@client-example.com',
        ]);
        $duplicateResponse->assertSessionHasErrors(['email']);
    }

    public function test_same_email_can_be_created_in_different_tenants(): void
    {
        // 'acme@client-example.com' exists in Apex; BlueWave should be able to create one with the same email
        $response = $this->actingAs($this->blueWaveOwner)->post('/customers', [
            'name' => 'Acme BlueWave Branch',
            'email' => 'acme@client-example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->blueWaveTenant->id,
            'email' => 'acme@client-example.com',
        ]);
    }

    public function test_tenant_can_view_customer_details_and_order_history(): void
    {
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        $response = $this->actingAs($this->apexOwner)->get("/customers/{$customer->id}");

        $response->assertOk();
        $response->assertSee($customer->name);
        $response->assertSee($customer->email);
        $response->assertSee('Order History');
    }

    public function test_tenant_can_update_customer(): void
    {
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        // View edit form
        $editFormResponse = $this->actingAs($this->apexOwner)->get("/customers/{$customer->id}/edit");
        $editFormResponse->assertOk();
        $editFormResponse->assertSee($customer->name);

        // Submit update
        $updateResponse = $this->actingAs($this->apexOwner)->put("/customers/{$customer->id}", [
            'name' => 'Acme Corporation Updated',
            'email' => $customer->email, // keep same email to verify ignore logic
            'city' => 'Denver',
            'status' => 'active',
        ]);

        $updateResponse->assertRedirect(route('customers.show', $customer));
        $updateResponse->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Acme Corporation Updated',
            'city' => 'Denver',
        ]);
    }

    public function test_owner_and_manager_can_delete_customer(): void
    {
        $customer1 = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        // Manager can delete
        $managerResponse = $this->actingAs($this->apexManager)->delete("/customers/{$customer1->id}");
        $managerResponse->assertRedirect(route('customers.index'));
        $managerResponse->assertSessionHas('status');
        $this->assertSoftDeleted('customers', ['id' => $customer1->id]);

        // Owner can delete
        $customer2 = Customer::where('tenant_id', $this->apexTenant->id)->whereNull('deleted_at')->firstOrFail();
        $ownerResponse = $this->actingAs($this->apexOwner)->delete("/customers/{$customer2->id}");
        $ownerResponse->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted('customers', ['id' => $customer2->id]);
    }

    public function test_staff_cannot_delete_customer(): void
    {
        $customer = Customer::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        $response = $this->actingAs($this->apexStaff)->delete("/customers/{$customer->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_cross_tenant_customer_access_is_blocked(): void
    {
        $blueWaveCustomer = Customer::where('tenant_id', $this->blueWaveTenant->id)->firstOrFail();

        // Apex user cannot view BlueWave customer
        $showResponse = $this->actingAs($this->apexOwner)->get("/customers/{$blueWaveCustomer->id}");
        $this->assertTrue(in_array($showResponse->status(), [403, 404], true));

        // Apex user cannot edit BlueWave customer
        $editResponse = $this->actingAs($this->apexOwner)->get("/customers/{$blueWaveCustomer->id}/edit");
        $this->assertTrue(in_array($editResponse->status(), [403, 404], true));

        // Apex user cannot update BlueWave customer
        $updateResponse = $this->actingAs($this->apexOwner)->put("/customers/{$blueWaveCustomer->id}", [
            'name' => 'Hacked Name',
            'email' => 'hacked@test.com',
        ]);
        $this->assertTrue(in_array($updateResponse->status(), [403, 404], true));

        // Apex user cannot delete BlueWave customer
        $deleteResponse = $this->actingAs($this->apexOwner)->delete("/customers/{$blueWaveCustomer->id}");
        $this->assertTrue(in_array($deleteResponse->status(), [403, 404], true));
    }

    public function test_api_v1_customers_endpoints_remain_intact(): void
    {
        // Sanctum login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@apex.test',
            'password' => 'password',
        ]);
        $loginResponse->assertOk();
        $token = $loginResponse->json('token');

        // Sanctum API GET /api/v1/customers
        $apiResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customers');

        $apiResponse->assertOk();
        $apiResponse->assertJsonStructure(['data', 'meta']);
    }
}
