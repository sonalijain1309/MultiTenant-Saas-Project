<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebProductCrudTest extends TestCase
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

    public function test_guest_is_redirected_to_login_when_accessing_products(): void
    {
        $response = $this->get('/products');
        $response->assertRedirect('/login');

        $createResponse = $this->get('/products/create');
        $createResponse->assertRedirect('/login');

        $postResponse = $this->post('/products', ['name' => 'Guest Product']);
        $postResponse->assertRedirect('/login');
    }

    public function test_authenticated_tenant_user_can_view_products_list(): void
    {
        $response = $this->actingAs($this->apexOwner)->get('/products');

        $response->assertOk();
        $response->assertSee('Product Catalog');
        $response->assertSee('Cloud Workspace License');
        $response->assertSee('APX-CLD-01');

        // BlueWave product must NOT appear on Apex's product list
        $response->assertDontSee('GPS Tracking Unit');
    }

    public function test_product_search_and_filtering_works(): void
    {
        // 1. Search by name
        $searchNameResponse = $this->actingAs($this->apexOwner)->get('/products?search=Security');
        $searchNameResponse->assertOk();
        $searchNameResponse->assertSee('Security Shield Addon');
        $searchNameResponse->assertDontSee('Cloud Workspace License');

        // 2. Search by SKU
        $searchSkuResponse = $this->actingAs($this->apexOwner)->get('/products?search=APX-API-03');
        $searchSkuResponse->assertOk();
        $searchSkuResponse->assertSee('Enterprise API Gateway');
        $searchSkuResponse->assertDontSee('Cloud Workspace License');

        // 3. Filter by availability
        $availableResponse = $this->actingAs($this->apexOwner)->get('/products?status=available');
        $availableResponse->assertOk();
        $availableResponse->assertSee('Cloud Workspace License');
    }

    public function test_tenant_user_can_view_create_product_page(): void
    {
        $response = $this->actingAs($this->apexOwner)->get('/products/create');

        $response->assertOk();
        $response->assertSee('Add New Product');
        $response->assertSee('Product Name');
        $response->assertSee('Stock Keeping Unit (SKU)');
        $response->assertSee('Selling Price ($)');
    }

    public function test_tenant_can_create_product_with_valid_data(): void
    {
        $payload = [
            'name' => 'Automated Data Backup',
            'sku' => 'APX-BKUP-04',
            'price' => 29.99,
            'cost_price' => 10.00,
            'stock' => 50,
            'description' => 'Nightly cloud backup solution.',
            'is_available' => '1',
        ];

        $response = $this->actingAs($this->apexOwner)->post('/products', $payload);

        $product = Product::where('sku', 'APX-BKUP-04')->first();
        $this->assertNotNull($product);
        $this->assertEquals($this->apexTenant->id, $product->tenant_id);

        $response->assertRedirect(route('products.show', $product));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->apexTenant->id,
            'sku' => 'APX-BKUP-04',
            'name' => 'Automated Data Backup',
            'price' => 29.99,
        ]);
    }

    public function test_product_creation_enforces_plan_quota(): void
    {
        // Limit Apex's plan product limit to current count
        $currentCount = Product::where('tenant_id', $this->apexTenant->id)->count();
        $this->apexTenant->plan->update(['max_products' => $currentCount]);

        $response = $this->actingAs($this->apexOwner)->post('/products', [
            'name' => 'Over Quota Product',
            'sku' => 'OVER-QUOTA-01',
            'price' => 19.99,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('products', [
            'sku' => 'OVER-QUOTA-01',
        ]);
    }

    public function test_product_creation_validates_required_fields_and_tenant_sku_uniqueness(): void
    {
        // 1. Missing required fields
        $response = $this->actingAs($this->apexOwner)->post('/products', []);
        $response->assertSessionHasErrors(['name', 'sku', 'price']);

        // 2. Duplicate SKU within same tenant ('APX-CLD-01' exists in Apex)
        $duplicateResponse = $this->actingAs($this->apexOwner)->post('/products', [
            'name' => 'Duplicate License',
            'sku' => 'APX-CLD-01',
            'price' => 99.00,
        ]);
        $duplicateResponse->assertSessionHasErrors(['sku']);
    }

    public function test_same_sku_can_be_created_in_different_tenants(): void
    {
        // 'APX-CLD-01' exists in Apex; BlueWave should be allowed to use this SKU in their tenant
        $response = $this->actingAs($this->blueWaveOwner)->post('/products', [
            'name' => 'BlueWave Cloud Service',
            'sku' => 'APX-CLD-01',
            'price' => 59.00,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->blueWaveTenant->id,
            'sku' => 'APX-CLD-01',
        ]);
    }

    public function test_tenant_can_view_product_details_and_sales_activity(): void
    {
        $product = Product::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        $response = $this->actingAs($this->apexOwner)->get("/products/{$product->id}");

        $response->assertOk();
        $response->assertSee($product->name);
        $response->assertSee($product->sku);
        $response->assertSee('Units Sold');
        $response->assertSee('Recent Order Transactions');
    }

    public function test_tenant_can_update_product(): void
    {
        $product = Product::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        // 1. View edit form
        $editFormResponse = $this->actingAs($this->apexOwner)->get("/products/{$product->id}/edit");
        $editFormResponse->assertOk();
        $editFormResponse->assertSee($product->name);

        // 2. Submit update with same SKU to verify unique ignore logic
        $updateResponse = $this->actingAs($this->apexOwner)->put("/products/{$product->id}", [
            'name' => 'Cloud Workspace License Pro',
            'sku' => $product->sku,
            'price' => 59.99,
            'cost_price' => 18.00,
            'stock' => 120,
            'is_available' => '1',
        ]);

        $updateResponse->assertRedirect(route('products.show', $product));
        $updateResponse->assertSessionHas('status');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Cloud Workspace License Pro',
            'price' => 59.99,
            'stock' => 120,
        ]);
    }

    public function test_owner_and_manager_can_delete_product(): void
    {
        $product1 = Product::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        // Manager can delete
        $managerResponse = $this->actingAs($this->apexManager)->delete("/products/{$product1->id}");
        $managerResponse->assertRedirect(route('products.index'));
        $managerResponse->assertSessionHas('status');
        $this->assertSoftDeleted('products', ['id' => $product1->id]);

        // Owner can delete
        $product2 = Product::where('tenant_id', $this->apexTenant->id)->whereNull('deleted_at')->firstOrFail();
        $ownerResponse = $this->actingAs($this->apexOwner)->delete("/products/{$product2->id}");
        $ownerResponse->assertRedirect(route('products.index'));
        $this->assertSoftDeleted('products', ['id' => $product2->id]);
    }

    public function test_staff_cannot_delete_product(): void
    {
        $product = Product::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        $response = $this->actingAs($this->apexStaff)->delete("/products/{$product->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_cross_tenant_product_access_is_blocked(): void
    {
        $blueWaveProduct = Product::where('tenant_id', $this->blueWaveTenant->id)->firstOrFail();

        // Apex user cannot view BlueWave product
        $showResponse = $this->actingAs($this->apexOwner)->get("/products/{$blueWaveProduct->id}");
        $this->assertTrue(in_array($showResponse->status(), [403, 404], true));

        // Apex user cannot edit BlueWave product
        $editResponse = $this->actingAs($this->apexOwner)->get("/products/{$blueWaveProduct->id}/edit");
        $this->assertTrue(in_array($editResponse->status(), [403, 404], true));

        // Apex user cannot update BlueWave product
        $updateResponse = $this->actingAs($this->apexOwner)->put("/products/{$blueWaveProduct->id}", [
            'name' => 'Hacked Product',
            'sku' => 'HACK-SKU-01',
            'price' => 1.00,
        ]);
        $this->assertTrue(in_array($updateResponse->status(), [403, 404], true));

        // Apex user cannot delete BlueWave product
        $deleteResponse = $this->actingAs($this->apexOwner)->delete("/products/{$blueWaveProduct->id}");
        $this->assertTrue(in_array($deleteResponse->status(), [403, 404], true));
    }

    public function test_api_v1_products_endpoints_remain_intact(): void
    {
        // Sanctum login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@apex.test',
            'password' => 'password',
        ]);
        $loginResponse->assertOk();
        $token = $loginResponse->json('token');

        // Sanctum API GET /api/v1/products
        $apiResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products');

        $apiResponse->assertOk();
        $apiResponse->assertJsonStructure(['data', 'meta']);
    }
}
