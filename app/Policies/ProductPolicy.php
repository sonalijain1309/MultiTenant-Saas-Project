<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $authUser): bool
    {
        return true;
    }

    public function view(User $authUser, Product $product): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $product->tenant_id;
    }

    public function create(User $authUser): bool
    {
        return true;
    }

    public function update(User $authUser, Product $product): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $product->tenant_id;
    }

    public function delete(User $authUser, Product $product): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $product->tenant_id && $authUser->canManageUsers();
    }
}
