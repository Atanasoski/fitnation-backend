<?php

namespace App\Enums;

/**
 * What a partner is to the business: the House Partner (Fit Nation itself), a
 * Sponsoring Partner (on the sponsor plan), or a plain partner. See
 * Partner::kind().
 */
enum PartnerKind: string
{
    case House = 'house';
    case Sponsoring = 'sponsoring';
    case Plain = 'plain';

    public function label(): string
    {
        return match ($this) {
            self::House => 'House Partner',
            self::Sponsoring => 'Sponsoring Partner',
            self::Plain => 'Partner',
        };
    }
}
