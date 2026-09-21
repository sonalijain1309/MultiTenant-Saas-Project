<?php

namespace Tests\Feature;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenancyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $tenantAUser;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->tenantA = Tenant::where('slug', 'apex-dynamics')->firstOrFail();
        $this->tenantB = Tenant::where('slug', 'bluewave-logistics')->firstOrFail();
        $this->tenantAUser = User::where('email', 'owner@apex.test')->firstOrFail();
        $this->superAdmin = User::where('email', 'admin@saas-platform.test')->firstOrFail();

        // Register test endpoint equipped with tenant identification and status verification
        Route::middleware(['tenant.identify', 'tenant.active'])->get('/_test/tenant-context', function () {
            $context = app(TenantContext::class);
            return response()->json([
                'tenant_id' => $context->id(),
                'tenant_slug' => $context->getTenant()?->slug,
            ]);
        });

        // Register test endpoint equipped with ONLY tenant.identify
        Route::middleware(['tenant.identify'])->get('/_test/tenant-identify-only', function () {
            $context = app(TenantContext::class);
            return response()->json([
                'tenant_id' => $context->id(),
                'tenant_slug' => $context->getTenant()?->slug,
            ]);
        });
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_authenticated_tenant_user_resolves_their_tenant(): void
    {
        $response = $this->actingAs($this->tenantAUser)
            ->getJson('/_test/tenant-context');

        $response->assertOk();
        $response->assertJson([
            'tenant_id' => $this->tenantA->id,
            'tenant_slug' => 'apex-dynamics',
        ]);
    }

    public function test_tenant_user_cannot_switch_to_another_tenant_via_header(): void
    {
        // Tenant A user maliciously sends Tenant B's ID in header
        $response = $this->actingAs($this->tenantAUser)
            ->withHeader('X-Tenant-ID', (string) $this->tenantB->id)
            ->getJson('/_test/tenant-context');

        $response->assertForbidden();
        $response->assertJson([
            'message' => 'Tenant access denied. You cannot switch tenant context.',
        ]);
    }

    public function test_super_admin_can_explicitly_resolve_tenant_via_header(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withHeader('X-Tenant-ID', (string) $this->tenantB->id)
            ->getJson('/_test/tenant-context');

        $response->assertOk();
        $response->assertJson([
            'tenant_id' => $this->tenantB->id,
            'tenant_slug' => 'bluewave-logistics',
        ]);
    }

    public function test_unauthenticated_request_can_resolve_tenant_via_header(): void
    {
        $response = $this->withHeader('X-Tenant-ID', (string) $this->tenantA->id)
            ->getJson('/_test/tenant-context');

        $response->assertOk();
        $response->assertJson([
            'tenant_id' => $this->tenantA->id,
            'tenant_slug' => 'apex-dynamics',
        ]);
    }

    public function test_suspended_tenant_is_rejected(): void
    {
        $this->tenantA->update(['status' => TenantStatus::SUSPENDED]);

        $response = $this->actingAs($this->tenantAUser)
            ->getJson('/_test/tenant-context');

        $response->assertForbidden();
        $response->assertJson([
            'message' => 'Tenant account is suspended. Please contact platform support.',
        ]);
    }

    public function test_canceled_tenant_is_rejected(): void
    {
        $this->tenantA->update(['status' => TenantStatus::CANCELED]);

        $response = $this->actingAs($this->tenantAUser)
            ->getJson('/_test/tenant-context');

        $response->assertForbidden();
        $response->assertJson([
            'message' => 'Tenant account has been canceled.',
        ]);
    }

    public function test_trialing_tenant_is_allowed(): void
    {
        $this->tenantA->update(['status' => TenantStatus::TRIALING]);

        $response = $this->actingAs($this->tenantAUser)
            ->getJson('/_test/tenant-context');

        $response->assertOk();
    }
}
