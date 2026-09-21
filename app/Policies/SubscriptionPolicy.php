<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->isSuperAdmin() || $authUser->isTenantOwner();
    }

    public function view(User $authUser, Subscription $subscription): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $subscription->tenant_id && $authUser->isTenantOwner();
    }

    public function create(User $authUser): bool
    {
        return $authUser->isSuperAdmin() || $authUser->isTenantOwner();
    }

    public function update(User $authUser, Subscription $subscription): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $subscription->tenant_id && $authUser->isTenantOwner();
    }

    public function delete(User $authUser, Subscription $subscription): bool
    {
        return $authUser->isSuperAdmin();
    }
}
