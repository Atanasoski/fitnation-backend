# 004 — `today()` returns stale drafts and `start()` can create a second session

**Area:** back-end / API
**Severity:** medium (correctness / UX)
**Status:** open

## Problem

Two related date-scoping bugs in `app/Http/Controllers/Api/WorkoutSessionController.php`.

### 1. `today()` surfaces any old draft as "today's session"

```php
->whereIn('status', [Draft, Active])
->where(function ($query) use ($today) {
    $query->whereDate('performed_at', $today->toDateString())
        ->orWhereNull('performed_at'); // Draft sessions might not have performed_at
})
->orderByDesc('created_at')
```

Drafts are created with `performed_at = null` by `WorkoutGenerationService::generate()`, so the
`orWhereNull` branch is unbounded in time. A draft generated three weeks ago and never confirmed
is returned as today's session, ahead of a session actually performed today
(the `orderByDesc('created_at')` only helps if the stale draft is older, which it usually is not
once several drafts pile up). Regenerated-and-cancelled drafts are excluded by status, but
abandoned ones are not.

### 2. `start()` deduplicates on `Active` only

```php
$session = WorkoutSession::where('user_id', Auth::id())
    ->whereDate('performed_at', $today->toDateString())
    ->where('status', Active)
    ->first();
if (! $session) { /* create a new Active session */ }
```

If the user already has a `draft` for today (generated via `/workout-sessions/generate`), calling
`start` creates a **second**, unrelated `active` session. The user now has two sessions for the
same day and `today()` may return either. There is also no lock — two concurrent `start` calls
race and both insert.

## Proposed fix

1. `today()`: bound the draft branch by date. Either
   `orWhere(fn($q) => $q->whereNull('performed_at')->whereDate('created_at', $today))`, or
   (better) stop relying on `performed_at` being null and give drafts an intended date column —
   e.g. a `scheduled_for` date set at generation time — then match on that. Prefer whichever
   keeps `WorkoutPreviewPage` working; check the front-end draft flow first.
2. Add a `scopeForDay(Builder $q, CarbonInterface $day)` to `WorkoutSession` so `today()`,
   `start()` and the calendar all use one definition of "belongs to this day".
3. `start()`: include `Draft` in the lookup. If a draft for today exists, confirm it
   (reuse `WorkoutGenerationService::confirmSession()`) instead of creating a new session.
4. Wrap the find-or-create in `start()` in the existing transaction with a
   `lockForUpdate()` on the user's sessions for that day, or add a unique index on
   `(user_id, performed_at_date, status)` for active sessions, to close the race.
5. Consider a scheduled cleanup that cancels drafts older than N days — decide N with the
   product owner and record it in the PR.

## Acceptance criteria

- [ ] A draft created 10 days ago is **not** returned by `GET /workout-sessions/today`.
- [ ] A draft created today **is** returned by `GET /workout-sessions/today`.
- [ ] `POST /workout-sessions/start` with an existing draft for today returns that session
      (now `active`), and does not create a second row.
- [ ] Two concurrent `start` calls produce exactly one active session for the day.
- [ ] Existing `WorkoutSessionGenerationTest` / `WorkoutPreviewTest` still pass.

## Files

- `app/Http/Controllers/Api/WorkoutSessionController.php` (`today`, `start`)
- `app/Models/WorkoutSession.php` (new scope)
- `app/Services/WorkoutGenerator/WorkoutGenerationService.php`
- `database/migrations/` if `scheduled_for` or the unique index is added
- `tests/Feature/` (new `WorkoutSessionTodayTest.php`)

## Out of scope

Status-transition guards (issue 003) — but coordinate, since `start()` reusing a draft depends
on the transition rules defined there.
