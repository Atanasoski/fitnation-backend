# Gym Signups skip email verification

Every registration carries a `partner_id`, and a partner subdomain's whole job is to funnel the gym's customers into the mobile app; a blocking verify-email step in the middle of that funnel cost signups. We decided that `POST /api/register` marks the user verified at creation (`email_verified_at = now()`, set before the `Registered` event fires, which also suppresses the verification mail) for every partner **except** the House Partner (`Partner::isHouse()`). House Partner signups — people joining without a gym vouching for them — keep the full verification flow.

## Consequences

- The trade-off is accepted deliberately: a gym customer with a typo'd email has an unreachable account (no password reset), but the gym can correct it with support, and social sign-ins already skipped verification the same way.
- Neither frontend gate changes: web and mobile both key off `email_verified_at`, which is simply already set for Gym Signups. Pre-existing unverified users are left as-is (no backfill); they still verify via the old flow.
- `User` keeps `MustVerifyEmail` for the House Partner path — do not "clean it up".
