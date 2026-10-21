<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseBookPeriodType: string
{
    case FORTNIGHTLY = 'fortnightly';
    case MONTHLY = 'monthly';
}
