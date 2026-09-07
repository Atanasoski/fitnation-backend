# 020 — Weekly Summary email

**Area:** back-end / new capability (email)
**Severity:** feature
**Status:** ready — decisions settled in the design session of 2026-09-07
**Builds on:** [018](018-push-notifications-phase-one.md) (scheduled-rule shape, sent record), [019](019-unfinished-account-nudges.md) (shared local-hour helpers, mail scaffold), `App\Services\FitnessMetrics\WeeklyProgress` (the numbers)
**Vocabulary:** [Weekly Summary](../../CONTEXT.md#weekly-summary), [Weekly Progress](../../CONTEXT.md#weekly-progress), [Completed Session](../../CONTEXT.md#completed-session), [Unit System](../../CONTEXT.md#unit-system). Use these words.
**Mobile:** none. Email only — the app has no week-summary surface yet.

## Goal

Every Monday morning a user who trained last week, or the week before, gets
one email: how the week went against the previous one. The numbers already
exist (`WeeklyProgress`); this issue delivers them on a schedule and lets the
user turn the email off.

## Decisions (do not reopen)

| # | Decision |
|---|---|
| W1 | **Monday 08:00 local**, covering the Mon–Sun week just ended. `WeeklyProgress`'s rule that the week in progress is never the subject is kept. |
| W2 | Recipients: users with ≥ 1 Completed Session in the week just ended **or** the one before. Zero in both → nothing; the Inactivity Nudge owns them. |
| W3 | **Email only.** `via()` = `['database', 'mail']`. No push (nothing in the app to open onto). |
| W4 | Content v1: workouts this week vs last, total volume in the user's Unit System with the % change, total time, trend. **No Personal Records** in v1 — they are derived per session and would cost N queries per user. |
| W5 | This is the first email that is not about the user's account, so it is the first that can be **unsubscribed**. One-click, signed, no login: `GET /email/weekly-summary/unsubscribe/{user}?signature=…` → sets the preference, shows a plain confirmation page. `List-Unsubscribe` + `List-Unsubscribe-Post` headers on every summary. |
| W6 | The preference lives in a new **`users.notification_settings` JSON column** — `{"weekly_summary_email": true}` for now — the seed of per-category preferences (a later issue). Readable/writable through the existing `PATCH /notification-settings`; `UserResource` exposes it as `notification_settings`. |
| W7 | Push Switch (`push_enabled`) is **not** consulted: it governs push, this is mail. `weekly_summary_email` is the only gate. |
| W8 | Timezone: the user's latest Device, else home. `WeeklyProgress::for()` gains an `asOf` (the user's local Monday) so "last full week" is the user's week, not the server's. |

## Copy — `lang/en/notifications.php` → `weekly_summary`

Subject: **Your week: {n} workouts** (`{n}` = 0 reads "Your week: no workouts"
— it still goes out when the previous week had some).

Body blocks, in order:
1. **{n} workouts** — {±m} vs last week
2. **{volume} {unit} lifted** — {±x}% (omitted when both weeks are 0)
3. **{t} min training**
4. One line by trend: up → "More than the week before. Keep the streak."; same
   → "Same as last week. Consistency is the whole game."; down → "A lighter
   week. Next one's yours."; zero this week with some last week → "Nothing
   logged this week. Your plan is where you left it."
5. Button **Open the app** → `/get`. Footer: **Unsubscribe from weekly
   summaries** → W5 link.

Volume is a Training Weight total: convert at this boundary with the existing
unit machinery (ADR-0001), round to a whole unit, never show decimals.

## Modules

### `App\Services\FitnessMetrics\WeeklyProgress` — small change

`for(User $user, ?CarbonInterface $asOf = null)`: every `Carbon::now()` becomes
`$asOf ?? now()`. Existing callers pass nothing; `FitnessMetricsPayloadTest`
must stay green untouched (it locks the payload).

### `App\Services\Notifications\WeeklySummaries`

`dueAt(CarbonImmutable $now): Collection<int, WeeklySummaryCandidate>` —
users for whom `$now` in their timezone is Monday in the 08:00 hour, who have
`weekly_summary_email` on, who have ≥ 1 Completed Session between
`startOfWeek(now - 2 weeks)` and `endOfWeek(now - 1 week)` in their timezone,
and who have no `WeeklySummary` row created since that Monday 00:00 local
(the sent record; same idempotency as the others). Shares the local-hour
helpers from 019. Constant query count.

The candidate carries the user and the `WeeklyProgress::for($user, $asOf)`
array — computed once here so the mail does not recompute it.

### `App\Notifications\WeeklySummary`

`ShouldQueue`; `via()` = `['database', 'mail']`; `toArray()` = the numbers it
sent (workouts, previous, volume, unit, time, trend) so the row is a record of
what the user was told; `toMail()` → `App\Mail\WeeklySummaryMail`.

### `App\Mail\WeeklySummaryMail`

Same scaffold as 019's mail. Adds:

```php
->withSymfonyMessage(fn (Email $m) => $m->getHeaders()
    ->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>")
    ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click'))
```

### Unsubscribe

- Migration `add_notification_settings_to_users_table`: `json('notification_settings')->nullable()`.
  `User::$casts` → `'array'`; accessor `notificationSetting(string $key, bool $default)`.
  Null column = all defaults.
- `GET /email/weekly-summary/unsubscribe/{user}` — `signed` middleware, **no
  auth**, web routes. Sets `weekly_summary_email = false`, renders
  `emails.unsubscribed` (one sentence, partner-branded, link to `/get`).
  Also accept `POST` on the same route for `List-Unsubscribe-Post`.
- `PATCH /notification-settings` accepts `notification_settings.weekly_summary_email`
  (boolean) alongside `push_enabled`; `UserResource` gains
  `notification_settings: {weekly_summary_email: bool}`. Additive; the mobile
  app ignores it until the preferences issue.

### `App\Console\Commands\SendWeeklySummaries`

`notifications:weekly-summaries`, `everyFifteenMinutes()`,
`withoutOverlapping()`, `onOneServer()`. It is cheap to run when it is not
Monday — the first query is "which timezones are at Monday 08:00" and the
answer is usually none.

## Tests

**WeeklyProgress** — `for($user, $asOf)` with a fixed `asOf` returns the same
payload as `for($user)` under `travelTo($asOf)`; existing tests untouched.

**WeeklySummaries**
- Monday 06:00 UTC, Skopje user (08:00 local) with 3 sessions last week ⇒ due,
  payload `current_week_workouts = 3`
- same user at 07:00 UTC (09:00) ⇒ not due; Tuesday ⇒ not due
- sessions only in the week before last ⇒ due (with `current = 0`)
- no sessions in either week ⇒ not due
- `weekly_summary_email = false` ⇒ not due; `push_enabled = false` ⇒ **still due** (W7)
- sent this Monday ⇒ not due at the 08:45 run
- a user whose Device is in `America/New_York` is due at 12:00 UTC on Monday
- a session on Sunday 23:30 local counts for that week (boundary in local time)
- query count constant across 10 vs 40 users

**Mail**
- subject has the count; volume rendered in imperial for an imperial user and
  metric for a metric user, whole numbers; `List-Unsubscribe` headers present
  and the URL is signed
- unsubscribe GET with a valid signature flips the setting and renders the
  page; a tampered signature ⇒ 403; POST works too
- `PATCH /notification-settings` round-trips `weekly_summary_email`; `GET /user`
  reflects it

**Command** — sends exactly the due set; second run in the hour sends nothing.

## Order of work

1. `WeeklyProgress::for($user, $asOf)` + test. Own commit.
2. `notification_settings` column, accessor, `PATCH`/`UserResource`, unsubscribe
   route + page + tests. Own commit — this is the piece a later preferences
   issue builds on.
3. `WeeklySummaries` + candidate + tests.
4. Mailable + view + notification + copy + headers + tests.
5. Command + schedule.

One branch, `feat/weekly-summary-email`, one PR.

## Notes

- Sending is bursty by design: every Skopje user at 08:00 Monday. Mail goes
  through the queue (`ShouldQueue`); Resend's rate limits are far above this
  volume, but keep the notification queued rather than sending inline from the
  command.
- Gmail and Yahoo require one-click unsubscribe headers for senders above a
  volume threshold; adding them from day one costs nothing.
- The `notifications` row per summary is what a future in-app inbox would
  show; that is why `toArray()` records the numbers, not just "sent".
