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

    public function canManageTenantUsers(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::TENANT_OWNER, self::MANAGER], true);
    }

    public function canModifySubscription(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::TENANT_OWNER], true);
    }
}
