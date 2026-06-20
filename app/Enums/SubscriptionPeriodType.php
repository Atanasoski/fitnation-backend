<?php

namespace App\Enums;

enum SubscriptionPeriodType: string
{
    case Normal = 'normal';
    case Trial = 'trial';
    case Intro = 'intro';
    case Promotional = 'promotional';
}
