<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Authenticate credentials and issue a Sanctum token.
     *
     * @throws AuthenticationException
     */
    public function authenticate(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid email or password.');
        }

        if (! $user->is_active) {
            throw new AuthenticationException('Your account has been deactivated. Please contact support.');
        }

        // Generate Sanctum plain text token
        $token = $user->createToken('api-token')->plainTextToken;

        if ($user->tenant_id) {
            $user->load(['tenant.plan']);
        }

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Revoke the current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Get the authenticated user with loaded relations.
     */
    public function getCurrentUser(User $user): User
    {
        if ($user->tenant_id) {
            $user->load(['tenant.plan']);
        }

        return $user;
    }
}
