<?php

namespace App\Enums;

/**
 * The two things a super admin changes about a user by hand, each written to
 * the admin change record (App\Models\AdminChange).
 */
enum AdminChangeKind: string
{
    case ComplimentaryAccess = 'complimentary_access';
    case PartnerChange = 'partner_change';

    public function label(): string
    {
        return match ($this) {
            self::ComplimentaryAccess => 'Complimentary Access',
            self::PartnerChange => 'Partner change',
        };
    }
}
