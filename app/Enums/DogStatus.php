<?php

namespace App\Enums;

enum DogStatus: string
{
    case Active    = 'active';
    case Inactive  = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::Active    => 'Active',
            self::Inactive  => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    public function tooltip(): string
    {
        return match($this) {
            self::Active    => 'Dog can be checked in and purchase credits normally.',
            self::Inactive  => 'Dog is no longer a client. Check-in and purchases are blocked.',
            self::Suspended => 'Temporarily blocked from check-in and purchases (e.g., behaviour concern, lapsed vaccinations).',
        };
    }

    public function isEligible(): bool
    {
        return $this === self::Active;
    }
}
