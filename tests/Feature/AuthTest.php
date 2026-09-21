<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_valid_credentials_succeed_and_issue_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@apex.test',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'user' => [
                'id',
                'name',
                'email',
                'role',
                'role_label',
                'tenant_id',
                'tenant' => [
                    'id',
                    'name',
                    'slug',
                ],
            ],
            'token',
            'token_type',
        ]);

        $this->assertEquals('Bearer', $response->json('token_type'));
        $this->assertEquals('tenant_owner', $response->json('user.role'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_invalid_password_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@apex.test',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
        $response->assertJson([
            'message' => 'Invalid email or password.',
        ]);
    }

    public function test_unknown_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertUnauthorized();
        $response->assertJson([
            'message' => 'Invalid email or password.',
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::where('email', 'staff@apex.test')->firstOrFail();
        $user->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@apex.test',
            'password' => 'password',
        ]);

        $response->assertUnauthorized();
        $response->assertJson([
            'message' => 'Your account has been deactivated. Please contact support.',
        ]);
    }

    public function test_authenticated_user_can_retrieve_profile_via_me(): void
    {
        $user = User::where('email', 'manager@apex.test')->firstOrFail();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => 'manager@apex.test',
                'role' => 'manager',
                'role_label' => 'Manager',
                'tenant_id' => $user->tenant_id,
            ],
        ]);
    }

    public function test_unauthenticated_me_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::where('email', 'owner@apex.test')->firstOrFail();
        $token = $user->createToken('logout-test-token')->plainTextToken;

        // Verify token works
        $check = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');
        $check->assertOk();

        // Logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');
        $logoutResponse->assertOk();
        $logoutResponse->assertJson(['message' => 'Logged out successfully.']);

        // Token must now be rejected
        $retryResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');
        $retryResponse->assertUnauthorized();
    }
}
