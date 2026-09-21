<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $superAdmin;
    protected User $tenantAOwner;
    protected User $tenantAManager;
    protected User $tenantAStaff;
    protected User $tenantBOwner;
    protected Plan $basicPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->tenantA = Tenant::where('slug', 'apex-dynamics')->firstOrFail();
        $this->tenantB = Tenant::where('slug', 'bluewave-logistics')->firstOrFail();
        $this->basicPlan = Plan::where('slug', 'basic')->firstOrFail();

        $this->superAdmin = User::where('email', 'admin@saas-platform.test')->firstOrFail();
        $this->tenantAOwner = User::where('email', 'owner@apex.test')->firstOrFail();
        $this->tenantAManager = User::where('email', 'manager@apex.test')->firstOrFail();
        $this->tenantAStaff = User::where('email', 'staff@apex.test')->firstOrFail();
        $this->tenantBOwner = User::where('email', 'owner@bluewave.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_tenant_owner_can_list_and_create_users_within_own_tenant(): void
    {
        $response = $this->actingAs($this->tenantAOwner)
            ->getJson('/api/v1/users');

        $response->assertOk();
        // Apex has 3 seeded users
        $response->assertJsonCount(3, 'data');

        // Create a new staff member within Apex's Basic plan quota (Apex limit is 5, currently 3)
        $createResponse = $this->actingAs($this->tenantAOwner)
            ->postJson('/api/v1/users', [
                'name' => 'New Staff Member',
                'email' => 'newstaff@apex.test',
                'password' => 'password123',
                'role' => UserRole::STAFF->value,
            ]);

        $createResponse->assertCreated();
        $createResponse->assertJson([
            'message' => 'User created successfully.',
            'user' => [
                'email' => 'newstaff@apex.test',
                'role' => 'staff',
                'tenant_id' => $this->tenantA->id,
            ],
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newstaff@apex.test',
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    public function test_staff_cannot_create_or_manage_users(): void
    {
        // 1. Staff cannot list users (Policy denies viewAny)
        $listResponse = $this->actingAs($this->tenantAStaff)
            ->getJson('/api/v1/users');
        $listResponse->assertForbidden();

        // 2. Staff cannot create users (Policy denies create)
        $createResponse = $this->actingAs($this->tenantAStaff)
            ->postJson('/api/v1/users', [
                'name' => 'Illegal User',
                'email' => 'illegal@apex.test',
                'password' => 'password123',
                'role' => UserRole::STAFF->value,
            ]);
        $createResponse->assertForbidden();

        // 3. Staff cannot delete users
        $deleteResponse = $this->actingAs($this->tenantAStaff)
            ->deleteJson("/api/v1/users/{$this->tenantAManager->id}");
        $deleteResponse->assertForbidden();
    }

    public function test_manager_cannot_create_super_admin_or_tenant_owner(): void
    {
        // Privilege escalation: Manager attempting to create a Super Admin
        $response = $this->actingAs($this->tenantAManager)
            ->postJson('/api/v1/users', [
                'name' => 'Escalated Admin',
                'email' => 'escalated@apex.test',
                'password' => 'password123',
                'role' => UserRole::SUPER_ADMIN->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['role']);

        // Privilege escalation: Manager attempting to create a Tenant Owner
        $ownerResponse = $this->actingAs($this->tenantAManager)
            ->postJson('/api/v1/users', [
                'name' => 'Escalated Owner',
                'email' => 'owner2@apex.test',
                'password' => 'password123',
                'role' => UserRole::TENANT_OWNER->value,
            ]);

        $ownerResponse->assertUnprocessable();
        $ownerResponse->assertJsonValidationErrors(['role']);
    }

    public function test_cross_tenant_user_access_is_strictly_forbidden(): void
    {
        // Tenant A Owner attempting to view Tenant B Owner
        $response = $this->actingAs($this->tenantAOwner)
            ->getJson("/api/v1/users/{$this->tenantBOwner->id}");

        $response->assertForbidden();

        // Tenant A Owner attempting to delete Tenant B Owner
        $deleteResponse = $this->actingAs($this->tenantAOwner)
            ->deleteJson("/api/v1/users/{$this->tenantBOwner->id}");

        $deleteResponse->assertForbidden();
    }

    public function test_tenant_user_cannot_switch_tenant_via_header_on_user_endpoints(): void
    {
        // Tenant A Owner attempts to pass X-Tenant-ID for Tenant B
        $response = $this->actingAs($this->tenantAOwner)
            ->withHeader('X-Tenant-ID', (string) $this->tenantB->id)
            ->getJson('/api/v1/users');

        $response->assertForbidden();
        $response->assertJson([
            'message' => 'Tenant access denied. You cannot switch tenant context.',
        ]);
    }

    public function test_role_escalation_via_update_is_blocked(): void
    {
        // Staff attempts to update own role to Super Admin
        $response = $this->actingAs($this->tenantAStaff)
            ->putJson("/api/v1/users/{$this->tenantAStaff->id}", [
                'role' => UserRole::SUPER_ADMIN->value,
            ]);

        // FormRequest authorize checks canManageUsers, rejecting staff entirely
        $response->assertForbidden();
    }

    public function test_user_creation_enforces_subscription_plan_user_limit(): void
    {
        // BlueWave is on Free plan (max_users = 1). Currently has 2 seeded users (limit reached).
        $response = $this->actingAs($this->tenantBOwner)
            ->postJson('/api/v1/users', [
                'name' => 'Excess User',
                'email' => 'excess@bluewave.test',
                'password' => 'password123',
                'role' => UserRole::STAFF->value,
            ]);

        $response->assertForbidden();
        $response->assertJson([
            'message' => 'User limit reached for your current plan.',
            'resource' => 'users',
            'limit' => 1,
            'plan' => 'Free',
        ]);

        // Upgrade BlueWave to Basic plan (max_users = 5)
        $this->tenantB->update(['plan_id' => $this->basicPlan->id]);
        $this->tenantB->activeSubscription->update(['plan_id' => $this->basicPlan->id]);

        // Retrying user creation after upgrade now succeeds
        $upgradeResponse = $this->actingAs($this->tenantBOwner)
            ->postJson('/api/v1/users', [
                'name' => 'Allowed User',
                'email' => 'allowed@bluewave.test',
                'password' => 'password123',
                'role' => UserRole::STAFF->value,
            ]);

        $upgradeResponse->assertCreated();
        $upgradeResponse->assertJson([
            'user' => [
                'email' => 'allowed@bluewave.test',
                'tenant_id' => $this->tenantB->id,
            ],
        ]);
    }

    public function test_super_admin_can_access_all_users(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/users');

        $response->assertOk();
        // Super Admin sees users across all tenants plus themselves (3 Apex + 2 BlueWave + 1 SuperAdmin = 6)
        $this->assertGreaterThanOrEqual(6, $response->json('meta.total'));
    }
}
