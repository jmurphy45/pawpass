<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending   = 'pending';
    case Active    = 'active';
    case PastDue   = 'past_due';
    case Unpaid    = 'unpaid';
    case Cancelled = 'cancelled';
}
