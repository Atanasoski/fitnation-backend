# 019 — Unfinished Account nudges: bring back the users who never reached the app

**Area:** back-end / new capability (email)
**Severity:** feature
**Status:** ready — decisions settled in the design session of 2026-09-07
**Builds on:** [018](018-push-notifications-phase-one.md) — the `database` + `expo` channels, `Inactivity` as the shape of a scheduled rule, the sent-record as idempotency source
**Vocabulary:** [Unfinished Account](../../CONTEXT.md#unfinished-account), [Device](../../CONTEXT.md#device), [Inactivity Nudge](../../CONTEXT.md#inactivity-nudge). Use these words.
**Mobile:** none. Emails link to `/get`, which already exists.

## Goal

Two places a new user leaks out of the funnel before ever seeing a workout —
`AppNavigator` gates on `email_verified_at` first and `onboarding_completed_at`
second — and today nothing follows up on either. After this issue, an
Unfinished Account gets three emails over a week, each saying exactly what is
left to do, and then silence.

## Decisions (do not reopen)

| # | Decision |
|---|---|
| U1 | **One ladder**, measured from `users.created_at`: 1, 3 and 7 days, then stop. The step is keyed on the sent record exactly as the Inactivity Nudge does it — no new columns. |
| U2 | The **content** depends on where the user is stuck at send time: unverified → *confirm your email*, with a fresh signed verification link as the primary action; verified but not onboarded → *finish setting up*. Social sign-ins are created verified (`SocialAuthController.php:179`) and only ever get the second. |
| U3 | Channels `['database', 'mail', 'expo']`. Email is the one that reaches them; the database row is the sent record; `expo` no-ops for the usual case of no Device and is there for the reinstall / second-account cases where one exists. |
| U4 | Sent at **10:00 local** — Device timezone if any, else `notifications.default_timezone`. Same hour-wide window and quarter-hour schedule as the Inactivity Nudge; the sent record dedupes the runs. |
| U5 | Link target is **`{APP_URL}/get`** (`LandingPageController::storeRedirect`) — opens the store, and the app if installed. No custom-scheme links in email; universal links are a later spec. |
| U6 | Stops when `onboarding_completed_at` is set or the user is soft-deleted. A user who verifies between steps simply gets the *finish setting up* body next. Push Switch is **not** consulted: this is account mail, not a preference, and the user has never seen the switch. |
| U7 | Partner-branded exactly like `VerifyEmailMail`: partner name in the subject, identity colour and logo in the body, `config('app.name')` fallback. |
| U8 | These are **service emails** about an account the user created — no unsubscribe link. The ladder's three-and-done is the courtesy. |

## Copy — `lang/en/notifications.php` → `unfinished_account`

Subjects carry the partner name. `{partner}` = `$user->partner?->name ?? config('app.name')`.

| step | stuck at | subject | body lead |
|---|---|---|---|
| 1 | unverified | Confirm your email for {partner} | One tap and you're in. The link below verifies your address. |
| 1 | not onboarded | Finish setting up {partner} | Two minutes of questions and your first plan is ready. |
| 3 | unverified | Still want to train with {partner}? | Your account is waiting on one thing — confirming your email. |
| 3 | not onboarded | Your first plan is two minutes away | Tell us your goal and how often you train; we'll build the rest. |
| 7 | either | Last one from us | We won't email again about this. If you'd like to start, everything is ready. |

Primary button: unverified → **Verify email** (signed URL from
`VerifyEmail::verificationUrl`); not onboarded → **Open the app** (`/get`).
Secondary line on unverified mails: "Then open the app to finish setting up"
→ `/get`.

## Modules

### `App\Services\Notifications\UnfinishedAccounts`

`dueAt(CarbonImmutable $now): Collection<int, UnfinishedAccountCandidate>`,
same shape as `Inactivity::dueAt()` and sharing its mechanics — timezone per
user's latest Device with the home fallback, hour-wide window, ladder step from
whole local days, sent-record check bounded by the earliest `created_at` in the
batch. Differences:

- Population is **users with `onboarding_completed_at` null** (not soft-deleted
  — the default scope already does that), not users with Devices. Most have no
  Device: their timezone is the home timezone, and that is fine.
- Measured from `users.created_at`; there is no "training resets it".
- The candidate carries `stuckAt: 'unverified' | 'not_onboarded'`, read at
  evaluation time.
- No Push Switch or in-progress-session exclusions.

Extract what is genuinely shared with `Inactivity` — the timezone resolution
and the hour test at least — into `App\Services\Notifications\LocalHour` (or a
better name) rather than copying it. Do this as a refactor commit first, with
`InactivityTest` green before and after, then build on it.

### `App\Notifications\UnfinishedAccountNudge`

`ShouldQueue`; `__construct(int $step, string $stuckAt)`; `via()` =
`['database', 'mail', 'expo']`; `toArray()` = `step`, `stuck_at`, `title`,
`body`, `url`; `toMail()` returns `App\Mail\UnfinishedAccountMail`; `toExpo()`
carries the subject as title, the lead as body, `data.url` = the `/get` URL.

### `App\Mail\UnfinishedAccountMail`

Same scaffold as `VerifyEmailMail`: constructor takes the user, step, stuckAt,
primary URL; `envelope()` builds the subject from lang; `content()` views
`emails.unfinished-account` + `-text`. Reuse the header/footer/button styles
from `verify-email.blade.php` — extract them into a layout partial if that is
cheaper than a third copy.

### `App\Console\Commands\SendUnfinishedAccountNudges`

`notifications:unfinished-accounts`, `everyFifteenMinutes()`,
`withoutOverlapping()`, `onOneServer()`, next to the other two in
`routes/console.php`.

## Tests

`tests/Feature/Notifications/` as before. `Mail::fake()` alongside `Http::fake()`.

**UnfinishedAccounts**
- registered 1 day ago, unverified, 10:00 home tz ⇒ step 1, `unverified`
- verified, not onboarded ⇒ step 1, `not_onboarded`; social sign-in fixture
  never yields `unverified`
- day 0 ⇒ nothing; day 2 after step 1 sent ⇒ nothing; day 3 ⇒ step 3; day 7
  ⇒ step 7; day 30 ⇒ nothing
- verifies between step 1 and 3 ⇒ step 3 body is `not_onboarded`
- onboarding completed ⇒ never a candidate, even at day 1
- soft-deleted ⇒ never a candidate
- a user **with** a Device in `America/New_York` is due at 14:00 UTC, not at
  08:00 UTC (home tz)
- `push_enabled = false` does **not** exclude (U6)
- query count constant across 10 vs 40 users

**Notification / mail**
- step 1 unverified sends one mail with the partner name in the subject, a
  signed verification URL and the `/get` URL; the database row records
  `stuck_at`
- step 1 not-onboarded sends the *finish setting up* mail with `/get` only
- with a Device present, a push also goes out (Http sent once); without one,
  none
- the mail renders with a partner identity and without one (no partner)

**Command** — sends exactly the due set; a second run in the hour sends nothing.

## Order of work

1. Refactor commit: shared timezone/hour helpers out of `Inactivity`;
   `InactivityTest` green before and after.
2. `UnfinishedAccounts` + candidate + tests.
3. Mailable + views + notification + copy + tests.
4. Command + schedule + tests. `notifications:unfinished-accounts` appears in
   `schedule:list` on Laravel Cloud.

One branch, `feat/unfinished-account-nudges`, one PR (the refactor is its own
commit inside it).

## Notes

- `VerifyEmail::verificationUrl()` is protected on the base notification;
  either call `URL::temporarySignedRoute('verification.verify', …)` directly
  as it does, or expose a small public wrapper. Do not duplicate the expiry.
- The 10:00 default-timezone send means most of these go out at 10:00 Skopje.
  That is the correct guess for this user base; a Device, when there is one,
  overrides it.
- Users invited by a partner (`UserInvitation`) register through the same path
  and are covered; the invitation's own expiry is irrelevant once registered.
