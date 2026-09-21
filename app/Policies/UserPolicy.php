<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->canManageUsers();
    }

    /**
     * Determine whether the user can view the specific target user.
     */
    public function view(User $authUser, User $targetUser): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        // Must belong to the exact same tenant
        if ($authUser->tenant_id !== $targetUser->tenant_id) {
            return false;
        }

        // Can view own profile or if manager/owner
        return $authUser->id === $targetUser->id || $authUser->canManageUsers();
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $authUser): bool
    {
        return $authUser->canManageUsers();
    }

    /**
     * Determine whether the user can update the target user.
     */
    public function update(User $authUser, User $targetUser): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        // Cross-tenant protection
        if ($authUser->tenant_id !== $targetUser->tenant_id) {
            return false;
        }

        // Self profile update (cannot escalate role, handled in FormRequest)
        if ($authUser->id === $targetUser->id) {
            return true;
        }

        // Tenant Owner can update any user in their tenant
        if ($authUser->isTenantOwner()) {
            return true;
        }

        // Manager can only update Staff in their tenant
        if ($authUser->isManager()) {
            return $targetUser->isStaff();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the target user.
     */
    public function delete(User $authUser, User $targetUser): bool
    {
        // Cannot delete self
        if ($authUser->id === $targetUser->id) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        // Cross-tenant protection
        if ($authUser->tenant_id !== $targetUser->tenant_id) {
            return false;
        }

        // Tenant Owner can delete Managers and Staff in their tenant
        if ($authUser->isTenantOwner()) {
            return ! $targetUser->isTenantOwner();
        }

        // Manager can only delete Staff
        if ($authUser->isManager()) {
            return $targetUser->isStaff();
        }

        return false;
    }
}
