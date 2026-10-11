<?php

namespace App\Services\Access;

use App\Enums\AccessSource;
use App\Enums\SubscriptionStore;
use App\Models\Partner;
use Carbon\CarbonInterface;

/**
 * One user's Access Source plus the facts behind it, for a chip and its
 * detail line. Which facts are set depends on the source: subscription
 * states carry product, period, store, cancelledAt and until (the end of the
 * paid period); Sponsored carries the sponsor and until (null: no end date);
 * Signup Trial carries until; Complimentary carries until and grantedBy (the admin who made the latest
 * grant, when the admin change record has one); None carries nothing.
 */
final class Access
{
    public function __construct(
        public readonly AccessSource $source,
        public readonly ?CarbonInterface $until = null,
        public readonly ?string $productId = null,
        public readonly ?string $period = null,
        public readonly ?SubscriptionStore $store = null,
        public readonly ?CarbonInterface $cancelledAt = null,
        public readonly ?Partner $sponsor = null,
        public readonly ?string $grantedBy = null,
    ) {}

    /**
     * e.g. "Yearly · App Store · renews 12 Mar 2027".
     */
    public function detail(): string
    {
        $date = fn (?CarbonInterface $at) => $at?->format('j M Y');
        $store = $this->store?->label();
        $line = fn (?string ...$parts) => implode(' · ', array_filter($parts));

        return match ($this->source) {
            AccessSource::Subscribed => $line($this->period, $store, 'renews '.$date($this->until)),
            AccessSource::Trial => $line('Trial', $store, 'ends '.$date($this->until)),
            AccessSource::Cancelled => $line($this->period, $store, $this->cancelledAt ? 'cancelled '.$date($this->cancelledAt) : null, 'paid until '.$date($this->until)),
            AccessSource::BillingIssue => $line($this->period, $store, 'payment failed, store retrying', 'paid until '.$date($this->until)),
            AccessSource::Paused => $line($this->period, $store, 'paused at period end', 'paid until '.$date($this->until)),
            AccessSource::Sponsored => $line($this->sponsor?->name.' pays', $this->until ? 'sponsorship until '.$date($this->until) : 'no end date'),
            AccessSource::SignupTrial => 'Signup Trial ends '.$date($this->until),
            AccessSource::Complimentary => $line('Complimentary until '.$date($this->until), $this->grantedBy ? 'granted by '.$this->grantedBy : null),
            AccessSource::None => 'No subscription, no sponsor, no Complimentary Access',
        };
    }
}
