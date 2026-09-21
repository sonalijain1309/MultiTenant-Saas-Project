<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $authUser): bool
    {
        return true;
    }

    public function view(User $authUser, Customer $customer): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $customer->tenant_id;
    }

    public function create(User $authUser): bool
    {
        return true;
    }

    public function update(User $authUser, Customer $customer): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $customer->tenant_id;
    }

    public function delete(User $authUser, Customer $customer): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        // Only Owners and Managers can delete customers
        return $authUser->tenant_id === $customer->tenant_id && $authUser->canManageUsers();
    }
}
