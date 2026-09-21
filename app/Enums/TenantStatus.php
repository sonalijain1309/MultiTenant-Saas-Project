<?php

namespace App\Enums;

enum TenantStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TRIALING = 'trialing';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::TRIALING => 'In Trial',
            self::CANCELED => 'Canceled',
        };
    }

    public function isOperational(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRIALING], true);
    }
}
