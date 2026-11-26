<?php

namespace App\Enums;

enum BookingSource: string
{
    case VILLA_WEB = 'villa_web';
    case MARKETPLACE = 'marketplace';
    case ERP_MANUAL = 'erp_manual';
}
