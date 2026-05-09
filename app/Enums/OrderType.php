<?php

namespace App\Enums;

enum OrderType: string
{
    case Daycare = 'daycare';
    case Boarding = 'boarding';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Daycare => 'Daycare',
            self::Boarding => 'Boarding',
            self::Invoice => 'Invoice',
        };
    }
}
