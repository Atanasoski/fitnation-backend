<?php

namespace App\Enums;

/**
 * Why a user has free access until users.grace_period_ends_at: the Signup
 * Trial every new user gets once, or Complimentary Access an admin granted
 * (CONTEXT.md). Stored as users.free_access_kind.
 */
enum FreeAccessKind: string
{
    case SignupTrial = 'signup_trial';
    case Complimentary = 'complimentary';
}
