<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case TENANT_OWNER = 'tenant_owner';
    case MANAGER = 'manager';
    case STAFF = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrator',
            self::TENANT_OWNER => 'Tenant Owner',
            self::MANAGER => 'Manager',
            self::STAFF => 'Staff Member',
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    public function isTenantOwner(): bool
    {
        return $this === self::TENANT_OWNER;
    }

    public function isManager(): bool
    {
        return $this === self::MANAGER;
    }

    public function isStaff(): bool
    {
        return $this === self::STAFF;
    }

    public function canManageTenantUsers(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::TENANT_OWNER, self::MANAGER], true);
    }

    public function canModifySubscription(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::TENANT_OWNER], true);
    }

    public function canManagePlatform(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    /**
     * Check if this role is authorized to assign the given target role.
     * Prevents privilege escalation.
     */
    public function canAssignRole(UserRole $targetRole): bool
    {
        return match ($this) {
            self::SUPER_ADMIN => true,
            self::TENANT_OWNER => in_array($targetRole, [self::TENANT_OWNER, self::MANAGER, self::STAFF], true),
            self::MANAGER => $targetRole === self::STAFF,
            self::STAFF => false,
        };
    }
}
