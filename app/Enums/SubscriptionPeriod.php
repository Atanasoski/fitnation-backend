<?php

namespace App\Enums;

/**
 * The billing period a subscription's product renews on. Products are named
 * for their period (config/entitlements.php): App Store sends the bare id
 * (`…premium.yearly`), Google Play `<product id>:<base plan id>`
 * (`…premium.monthly:monthly`). This is the one place that reads it.
 */
enum SubscriptionPeriod: string
{
    use HasValueLabel;

    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public static function fromProductId(string $productId): ?self
    {
        return match (true) {
            str_contains($productId, 'yearly') => self::Yearly,
            str_contains($productId, 'monthly') => self::Monthly,
            default => null,
        };
    }
}
