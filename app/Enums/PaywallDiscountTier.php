<?php

namespace App\Enums;

/**
 * How much a partner's members save on the subscription (GLOSSARY: Paywall
 * Discount). Each tier is its own monthly and yearly product in both stores,
 * grouped in a RevenueCat offering named after the tier. Stored as
 * partners.paywall_discount_tier; null is the standard price.
 */
enum PaywallDiscountTier: int
{
    case Ten = 10;
    case Twenty = 20;
    case Thirty = 30;
    case Fifty = 50;

    /** The RevenueCat offering holding this tier's products, e.g. "discount-30". */
    public function offeringId(): string
    {
        return "discount-{$this->value}";
    }

    /** "30% off", as the admin reads it. */
    public function label(): string
    {
        return "{$this->value}% off";
    }
}
