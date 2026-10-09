<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionPeriodType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionStore;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * What the store says about a user's subscription, as one immutable value that
 * {@see SubscriptionRecord::apply()} writes onto the user's row.
 *
 * It is deliberately not a RevenueCat webhook event: a webhook handler and a
 * REST snapshot of the subscriber both describe a state, and both must leave
 * the same row. The event time travels separately, as an argument to apply().
 *
 * Two shapes:
 *  - a purchase ({@see purchased()}, {@see renewed()}) says what was bought,
 *    from which store, until when — it may create the row;
 *  - a status change ({@see cancelled()}, {@see expired()}, …) only moves an
 *    existing row and never creates one.
 *
 * Store and period type arrive as RevenueCat's strings, in either case
 * (`APP_STORE` from a webhook, `app_store` from the REST API); the mapping to
 * our enums lives here and nowhere else.
 */
final class SubscriptionState
{
    /**
     * @param  array<string, mixed>  $purchase  columns a purchase reports; empty for a status change
     * @param  array<string, Carbon|null>  $dates  expires_at / cancelled_at this state sets; absent = unchanged
     */
    private function __construct(
        public readonly SubscriptionStatus $status,
        private readonly array $purchase = [],
        private readonly array $dates = [],
        private readonly bool $isNewPurchase = false,
    ) {}

    /**
     * A new purchase: everything about the row is taken from it, including the
     * price (null if not reported) and the purchase time (now if not reported).
     */
    public static function purchased(
        string $productId,
        ?string $store,
        ?string $periodType,
        ?float $price,
        ?string $currency,
        ?Carbon $purchasedAt,
        ?Carbon $expiresAt,
        string $environment,
    ): self {
        return new self(
            SubscriptionStatus::Active,
            self::purchaseColumns($productId, $store, self::periodType($periodType), $price, $currency, $purchasedAt, $environment),
            ['expires_at' => $expiresAt, 'cancelled_at' => null],
            isNewPurchase: true,
        );
    }

    /**
     * A renewal of the paid period (or a product change): a normal period from
     * here on. It continues the subscription, so the original purchase time is
     * kept and an unreported price or currency keeps the known one.
     */
    public static function renewed(
        string $productId,
        ?string $store,
        ?float $price,
        ?string $currency,
        ?Carbon $purchasedAt,
        ?Carbon $expiresAt,
        string $environment,
    ): self {
        return new self(
            SubscriptionStatus::Active,
            self::purchaseColumns($productId, $store, SubscriptionPeriodType::Normal, $price, $currency, $purchasedAt, $environment),
            ['expires_at' => $expiresAt, 'cancelled_at' => null],
        );
    }

    /** Auto-renew turned off: access continues until the current expiry. */
    public static function cancelled(Carbon $at): self
    {
        return new self(SubscriptionStatus::Cancelled, dates: ['cancelled_at' => $at]);
    }

    /** The money went back: the paid period ends at $at, not at its expiry. */
    public static function refunded(Carbon $at): self
    {
        return new self(SubscriptionStatus::Expired, dates: ['cancelled_at' => $at, 'expires_at' => $at]);
    }

    public static function uncancelled(): self
    {
        return new self(SubscriptionStatus::Active, dates: ['cancelled_at' => null]);
    }

    public static function expired(): self
    {
        return new self(SubscriptionStatus::Expired);
    }

    public static function billingIssue(): self
    {
        return new self(SubscriptionStatus::BillingIssue);
    }

    /** Android only: the pause takes effect at the current expiry. */
    public static function paused(): self
    {
        return new self(SubscriptionStatus::Paused);
    }

    /**
     * Only the two stores we sell through. Anything else is null, and a
     * purchase from it cannot be built: callers reject it up front rather than
     * record it as some other store.
     */
    public static function store(?string $store): ?SubscriptionStore
    {
        return match (strtoupper((string) $store)) {
            'APP_STORE' => SubscriptionStore::AppStore,
            'PLAY_STORE' => SubscriptionStore::PlayStore,
            default => null,
        };
    }

    public static function periodType(?string $type): SubscriptionPeriodType
    {
        return match (strtoupper((string) $type)) {
            'TRIAL' => SubscriptionPeriodType::Trial,
            'INTRO' => SubscriptionPeriodType::Intro,
            'PROMOTIONAL' => SubscriptionPeriodType::Promotional,
            default => SubscriptionPeriodType::Normal,
        };
    }

    /** @internal read by SubscriptionRecord */
    public function isPurchase(): bool
    {
        return $this->purchase !== [];
    }

    /** @internal read by SubscriptionRecord */
    public function isNewPurchase(): bool
    {
        return $this->isNewPurchase;
    }

    /**
     * @internal read by SubscriptionRecord
     *
     * @return array<string, mixed>
     */
    public function purchase(): array
    {
        return $this->purchase;
    }

    /**
     * @internal read by SubscriptionRecord
     *
     * @return array<string, Carbon|null>
     */
    public function dates(): array
    {
        return $this->dates;
    }

    /** @return array<string, mixed> */
    private static function purchaseColumns(
        string $productId,
        ?string $store,
        SubscriptionPeriodType $periodType,
        ?float $price,
        ?string $currency,
        ?Carbon $purchasedAt,
        string $environment,
    ): array {
        $mapped = self::store($store)
            ?? throw new InvalidArgumentException(sprintf('Store %s is not one we sell through', json_encode($store)));

        return [
            'product_id' => $productId,
            'store' => $mapped,
            'period_type' => $periodType,
            'price' => $price,
            'currency' => $currency,
            'purchased_at' => $purchasedAt,
            'environment' => strtolower($environment),
        ];
    }
}
