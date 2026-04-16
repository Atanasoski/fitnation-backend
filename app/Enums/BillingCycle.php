<?php

namespace App\Enums;

use Carbon\Carbon;
use Carbon\CarbonInterface;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';

    public function periodEndFrom(CarbonInterface $startsAt): Carbon
    {
        $date = Carbon::parse($startsAt)->copy();

        return match ($this) {
            self::Monthly => $date->addMonth(),
            self::Quarterly => $date->addMonths(3),
            self::SemiAnnual => $date->addMonths(6),
            self::Annual => $date->addYear(),
        };
    }
}
