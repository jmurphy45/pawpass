<?php

namespace App\Enums;

enum PaymentType: string
{
    case Full    = 'full';
    case Deposit = 'deposit';
    case Balance = 'balance';
    case Charge  = 'charge';

    public function label(): string
    {
        return match($this) {
            self::Full    => 'Full',
            self::Deposit => 'Deposit',
            self::Balance => 'Balance',
            self::Charge  => 'Charge',
        };
    }
}
