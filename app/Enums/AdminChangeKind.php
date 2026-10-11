<?php

namespace App\Enums;

/**
 * What a super admin changes about a user by hand, each written to the admin
 * change record (App\Models\AdminChange): Complimentary Access (grant,
 * extend, end), an early end of a Signup Trial, or a partner change.
 */
enum AdminChangeKind: string
{
    case ComplimentaryAccess = 'complimentary_access';
    case SignupTrial = 'signup_trial';
    case PartnerChange = 'partner_change';

    public function label(): string
    {
        return match ($this) {
            self::ComplimentaryAccess => 'Complimentary Access',
            self::SignupTrial => 'Signup Trial',
            self::PartnerChange => 'Partner change',
        };
    }

    /**
     * Whether the change is to Free Access, recorded with an until-date
     * (null: ended now) rather than a from → to partner.
     */
    public function isAccessChange(): bool
    {
        return $this !== self::PartnerChange;
    }
}
