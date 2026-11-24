<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID     = 'unpaid';
    case EXPIRED    = 'expired';
    case PAID       = 'paid';
    case REFUNDED   = 'refunded';
}