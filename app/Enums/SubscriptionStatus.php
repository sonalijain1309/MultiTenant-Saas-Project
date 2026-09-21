<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case TRIALING = 'trialing';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PAST_DUE => 'Past Due',
            self::CANCELED => 'Canceled',
            self::TRIALING => 'Trialing',
        };
    }

    public function isValid(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRIALING], true);
    }

    public function canCreateResources(): bool
    {
        return $this->isValid();
    }
}
