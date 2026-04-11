<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending           = 'pending';
    case Authorized        = 'authorized';
    case Paid              = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded          = 'refunded';
    case Failed            = 'failed';
    case Canceled          = 'canceled';
    case Disputed          = 'disputed';

    public function label(): string
    {
        return match($this) {
            self::Pending           => 'Pending',
            self::Authorized        => 'Authorized',
            self::Paid              => 'Paid',
            self::PartiallyRefunded => 'Partially Refunded',
            self::Refunded          => 'Refunded',
            self::Failed            => 'Failed',
            self::Canceled          => 'Canceled',
            self::Disputed          => 'Disputed',
        };
    }

    public function isTerminal(): bool
    {
        return match($this) {
            self::Refunded, self::Canceled, self::Disputed => true,
            default                                        => false,
        };
    }
}
