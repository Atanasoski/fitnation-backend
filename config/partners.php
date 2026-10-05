<?php

return [

    /*
    |--------------------------------------------------------------------------
    | House Partner
    |--------------------------------------------------------------------------
    |
    | The partner that is Fit Nation itself. Everyone who joins without a gym
    | belongs to it: social sign-in without (or with an inactive) partner, and
    | web registration without an invitation. Admin and partner-admin
    | accounts are the only ones with no partner.
    |
    */

    'house_partner_id' => (int) env('HOUSE_PARTNER_ID', 1),

];
