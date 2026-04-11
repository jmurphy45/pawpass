<?php

namespace App\Enums;

enum OrderType: string
{
    case Daycare  = 'daycare';
    case Boarding = 'boarding';

    public function label(): string
    {
        return match($this) {
            self::Daycare  => 'Daycare',
            self::Boarding => 'Boarding',
        };
    }
}
