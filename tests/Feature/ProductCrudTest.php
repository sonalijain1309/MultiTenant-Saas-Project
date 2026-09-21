<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
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

    public function test_tenant_can_list_own_products(): void
    {
        $response = $this->actingAs($this->apexOwner)->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'sku', 'name', 'description', 'price', 'cost_price', 'stock', 'is_available', 'created_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

        $productSkus = collect($response->json('data'))->pluck('sku')->all();
        $this->assertContains('APX-CLD-01', $productSkus);
        $this->assertNotContains('BW-TRK-01', $productSkus);
    }

    public function test_tenant_cannot_see_another_tenants_products(): void
    {
        $blueWaveProduct = Product::where('tenant_id', $this->blueWaveTenant->id)->firstOrFail();

        $response = $this->actingAs($this->apexOwner)->getJson("/api/v1/products/{$blueWaveProduct->id}");
        $response->assertForbidden();
    }

    public function test_create_product_succeeds(): void
    {
        $payload = [
            'sku' => 'APX-VPN-04',
            'name' => 'Managed Corporate VPN',
            'description' => 'Dedicated private network tunnel for remote teams.',
            'price' => 79.99,
            'cost_price' => 20.00,
            'stock' => 15,
            'is_available' => true,
        ];

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/products', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.sku', 'APX-VPN-04');
        $response->assertJsonPath('data.price', 79.99);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->apexTenant->id,
            'sku' => 'APX-VPN-04',
        ]);
    }

    public function test_product_creation_enforces_subscription_limit(): void
    {
        $currentCount = Product::where('tenant_id', $this->apexTenant->id)->count();
        $this->apexTenant->plan->update(['max_products' => $currentCount]);

        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/products', [
            'sku' => 'EXCESS-PROD',
            'name' => 'Excess Product',
            'price' => 10.00,
        ]);

        $response->assertForbidden();
        $response->assertJsonStructure(['message', 'resource', 'current', 'limit', 'plan']);
        $response->assertJsonPath('resource', 'products');
    }

    public function test_duplicate_sku_in_same_tenant_fails_validation(): void
    {
        // 'APX-CLD-01' exists in Apex
        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/products', [
            'sku' => 'APX-CLD-01',
            'name' => 'Duplicate License',
            'price' => 50.00,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sku']);
    }

    public function test_same_sku_in_different_tenant_succeeds(): void
    {
        // 'APX-CLD-01' belongs to Apex; BlueWave should be able to create one with the same SKU
        $response = $this->actingAs($this->blueWaveOwner)->postJson('/api/v1/products', [
            'sku' => 'APX-CLD-01',
            'name' => 'BlueWave Variant License',
            'price' => 55.00,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.sku', 'APX-CLD-01');

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->blueWaveTenant->id,
            'sku' => 'APX-CLD-01',
        ]);
    }

    public function test_update_product_succeeds_and_retains_same_sku(): void
    {
        $product = Product::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        $response = $this->actingAs($this->apexOwner)->putJson("/api/v1/products/{$product->id}", [
            'sku' => $product->sku,
            'name' => 'Updated Product Title',
            'price' => 129.50,
            'stock' => 80,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Product Title');
        $response->assertJsonPath('data.price', 129.50);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Title',
            'price' => 129.50,
        ]);
    }

    public function test_delete_product_authorization_by_role(): void
    {
        $product1 = Product::where('tenant_id', $this->apexTenant->id)->firstOrFail();

        // Staff CANNOT delete product
        $responseStaff = $this->actingAs($this->apexStaff)->deleteJson("/api/v1/products/{$product1->id}");
        $responseStaff->assertForbidden();

        // Manager CAN delete product
        $responseManager = $this->actingAs($this->apexManager)->deleteJson("/api/v1/products/{$product1->id}");
        $responseManager->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product1->id]);

        // Owner CAN delete product
        $product2 = Product::where('tenant_id', $this->apexTenant->id)->whereNull('deleted_at')->firstOrFail();
        $responseOwner = $this->actingAs($this->apexOwner)->deleteJson("/api/v1/products/{$product2->id}");
        $responseOwner->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product2->id]);
    }

    public function test_cross_tenant_product_manipulation_rejected(): void
    {
        $blueWaveProduct = Product::where('tenant_id', $this->blueWaveTenant->id)->firstOrFail();

        $updateResponse = $this->actingAs($this->apexOwner)->putJson("/api/v1/products/{$blueWaveProduct->id}", [
            'name' => 'Malicious Update',
        ]);
        $updateResponse->assertForbidden();

        $deleteResponse = $this->actingAs($this->apexOwner)->deleteJson("/api/v1/products/{$blueWaveProduct->id}");
        $deleteResponse->assertForbidden();
    }

    public function test_tenant_id_cannot_be_spoofed_in_product_creation(): void
    {
        $response = $this->actingAs($this->apexOwner)->postJson('/api/v1/products', [
            'sku' => 'SPOOF-01',
            'name' => 'Spoof Attempt Product',
            'price' => 19.99,
            'tenant_id' => $this->blueWaveTenant->id,
        ]);

        $response->assertCreated();
        $createdId = $response->json('data.id');

        $this->assertDatabaseHas('products', [
            'id' => $createdId,
            'tenant_id' => $this->apexTenant->id,
        ]);
        $this->assertDatabaseMissing('products', [
            'id' => $createdId,
            'tenant_id' => $this->blueWaveTenant->id,
        ]);
    }

    public function test_product_search_and_availability_filter(): void
    {
        // Search by SKU
        $searchResponse = $this->actingAs($this->apexOwner)->getJson('/api/v1/products?search=APX-CLD');
        $searchResponse->assertOk();
        $this->assertCount(1, $searchResponse->json('data'));
        $this->assertEquals('APX-CLD-01', $searchResponse->json('data.0.sku'));

        // Search by Name
        $nameSearchResponse = $this->actingAs($this->apexOwner)->getJson('/api/v1/products?search=Security');
        $nameSearchResponse->assertOk();
        $this->assertCount(1, $nameSearchResponse->json('data'));
        $this->assertEquals('APX-SEC-02', $nameSearchResponse->json('data.0.sku'));

        // Filter availability
        $availableResponse = $this->actingAs($this->apexOwner)->getJson('/api/v1/products?is_available=1');
        $availableResponse->assertOk();
        $this->assertNotEmpty($availableResponse->json('data'));
    }
}
