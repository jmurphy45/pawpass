<?php

namespace App\Enums;

enum OrderType: string
{
    case Daycare = 'daycare';
    case Boarding = 'boarding';
    case Invoice = 'invoice';
    case Vet = 'vet';
    case Grooming = 'grooming';
    case DaycareBooking = 'daycare_booking';

    public function label(): string
    {
        return match ($this) {
            self::Daycare => 'Daycare',
            self::Boarding => 'Boarding',
            self::Invoice => 'Invoice',
            self::Vet => 'Vet',
            self::Grooming => 'Grooming',
            self::DaycareBooking => 'Daycare Booking',
        };
    }
}
