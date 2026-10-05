<?php

namespace App\Enums;

/**
 * Why a user may use the app (CONTEXT.md, Access Source). The rule that
 * assigns one lives in App\Services\Admin\AccessSources.
 */
enum AccessSource: string
{
    case Subscribed = 'subscribed';
    case Trial = 'trial';
    case Cancelled = 'cancelled';
    case BillingIssue = 'billing_issue';
    case Paused = 'paused';
    case Sponsored = 'sponsored';
    case Complimentary = 'complimentary';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Cancelled => 'Cancelled, paid until',
            self::BillingIssue => 'Billing issue',
            default => ucfirst($this->value),
        };
    }
}
