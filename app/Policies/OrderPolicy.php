<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $authUser): bool
    {
        return true;
    }

    public function view(User $authUser, Order $order): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $order->tenant_id;
    }

    public function create(User $authUser): bool
    {
        return true;
    }

    public function update(User $authUser, Order $order): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->tenant_id === $order->tenant_id;
    }

    public function delete(User $authUser, Order $order): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        // Only Tenant Owners can delete order records
        return $authUser->tenant_id === $order->tenant_id && $authUser->isTenantOwner();
    }
}
