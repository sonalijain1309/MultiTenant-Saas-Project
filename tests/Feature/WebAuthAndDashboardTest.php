<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $apexOwner;
    protected User $apexStaff;
    protected User $blueWaveOwner;
    protected User $superAdmin;
    protected Tenant $apexTenant;
    protected Tenant $blueWaveTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->apexTenant = Tenant::where('slug', 'apex-dynamics')->firstOrFail();
        $this->blueWaveTenant = Tenant::where('slug', 'bluewave-logistics')->firstOrFail();

        $this->apexOwner = User::where('email', 'owner@apex.test')->firstOrFail();
        $this->apexStaff = User::where('email', 'staff@apex.test')->firstOrFail();
        $this->blueWaveOwner = User::where('email', 'owner@bluewave.test')->firstOrFail();
        $this->superAdmin = User::where('email', 'admin@saas-platform.test')->firstOrFail();
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_root_url_redirects_guest_to_login_and_authenticated_user_to_dashboard(): void
    {
        // Unauthenticated visitor hits /
        $guestResponse = $this->get('/');
        $guestResponse->assertRedirect('/login');

        // Authenticated user hits /
        $authResponse = $this->actingAs($this->apexOwner)->get('/');
        $authResponse->assertRedirect('/dashboard');
    }

    public function test_guest_can_view_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Sign In to Dashboard');
        $response->assertSee('owner@apex.test');
        $response->assertSee('Quick Demo Switcher');
    }

    public function test_authenticated_user_visiting_login_page_is_redirected_to_dashboard(): void
    {
        $response = $this->actingAs($this->apexOwner)->get('/login');

        $response->assertRedirect('/dashboard');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'owner@apex.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->apexOwner);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'owner@apex.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'nonexistent@company.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->apexStaff->update(['is_active' => false]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'staff@apex.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_authenticated_tenant_owner_can_access_dashboard_and_see_tenant_data(): void
    {
        $response = $this->actingAs($this->apexOwner)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Apex Dynamics');
        $response->assertSee('Basic Tier');
        $response->assertSee('Total Customers');
        $response->assertSee('Total Orders');
        $response->assertSee('Catalog Products');
        $response->assertSee('Cloud Workspace License');
        $response->assertSee('ORD-APX-001');
    }

    public function test_tenant_isolation_on_dashboard(): void
    {
        // 1. Apex Owner Dashboard
        $apexResponse = $this->actingAs($this->apexOwner)->get('/dashboard');
        $apexResponse->assertOk();
        $apexResponse->assertSee('Apex Dynamics');
        $apexResponse->assertSee('ORD-APX-001');
        // Must NOT see BlueWave data
        $apexResponse->assertDontSee('BlueWave Logistics');
        $apexResponse->assertDontSee('ORD-BW-001');
        $apexResponse->assertDontSee('BW-TRK-01');

        // 2. BlueWave Owner Dashboard
        $bwResponse = $this->actingAs($this->blueWaveOwner)->get('/dashboard');
        $bwResponse->assertOk();
        $bwResponse->assertSee('BlueWave Logistics');
        $bwResponse->assertSee('ORD-BW-001');
        $bwResponse->assertSee('Free Tier');
        // Must NOT see Apex data
        $bwResponse->assertDontSee('Apex Dynamics');
        $bwResponse->assertDontSee('ORD-APX-001');
        $bwResponse->assertDontSee('APX-CLD-01');
    }

    public function test_super_admin_can_access_dashboard_with_platform_overview(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Global Platform Analytics');
        $response->assertSee('Total Workspaces / Tenants');
        $response->assertSee('Apex Dynamics');
        $response->assertSee('BlueWave Logistics');
    }

    public function test_authenticated_user_can_logout(): void
    {
        $response = $this->actingAs($this->apexOwner)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_existing_api_functionality_remains_intact(): void
    {
        // API login endpoint
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@apex.test',
            'password' => 'password',
        ]);
        $loginResponse->assertOk();
        $token = $loginResponse->json('token');

        // API customer endpoint using Sanctum Bearer token
        $customersResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customers');

        $customersResponse->assertOk();
        $customersResponse->assertJsonStructure(['data', 'meta']);
    }
}
