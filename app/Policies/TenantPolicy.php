<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->isSuperAdmin();
    }

    public function view(User $authUser, Tenant $tenant): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $tenant->id;
    }

    public function create(User $authUser): bool
    {
        return $authUser->isSuperAdmin();
    }

    public function update(User $authUser, Tenant $tenant): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        // Tenant Owner can update their own company profile settings
        return $authUser->tenant_id === $tenant->id && $authUser->isTenantOwner();
    }

    public function delete(User $authUser, Tenant $tenant): bool
    {
        return $authUser->isSuperAdmin();
    }
}
