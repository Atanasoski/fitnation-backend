# 018 — Push notifications, phase one: Devices, the Expo channel, and the Inactivity Nudge

**Area:** back-end / new capability
**Severity:** feature (not a defect)
**Status:** ready — every decision below is settled; see the grilling of 2026-09-02
**Decides:** [ADR-0003](../adr/0003-a-device-is-an-authenticated-session.md)
**Pairs with:** `front-end/docs/specs/0012-push-notifications-phase-one.md` — the mobile half. The API contract in *Endpoints* below is shared between the two; change it in both or neither.
**Prerequisite:** a scheduler and a queue worker running in production. Neither exists today — see [DEPLOY_LIGHTSAIL.md](../DEPLOY_LIGHTSAIL.md).
**Vocabulary:** [Device](../../CONTEXT.md#device), [Push Token](../../CONTEXT.md#push-token), [Inactivity Nudge](../../CONTEXT.md#inactivity-nudge), [Completed Session](../../CONTEXT.md#completed-session). Use these words.

## Goal

Ship the rails, and one real notification on them. After this issue:

- the mobile app can register itself as a Device and be pushed to;
- the server can send a push to a user through one seam, `ExpoChannel`;
- dead tokens prune themselves;
- a user who goes quiet gets an Inactivity Nudge at 3, 7 and 14 days, at 18:00
  their time, and can turn push off;
- every sent notification is on record.

Not in this issue: workout-day reminders, per-category preferences, an inbox
endpoint, partner broadcasts, the web app. Phase two.

## Decisions (do not reopen)

| # | Decision |
|---|---|
| D1 | Transport is the **Expo Push Service** (`https://exp.host/--/api/v2/push/send`). Server never handles APNs/FCM tokens. Expo *push security* is on: requests carry `Authorization: Bearer ${EXPO_ACCESS_TOKEN}`. |
| D2 | A **Device is a Sanctum token** — `devices.personal_access_token_id`, unique, `cascadeOnDelete`. No unregister endpoint. [ADR-0003](../adr/0003-a-device-is-an-authenticated-session.md). |
| D3 | A Push Token re-registered under a different Sanctum token **moves**: the old Device row is deleted, the new one created. `devices.push_token` is unique. |
| D4 | **Timezone lives on the Device**, not the user. The user's timezone is that of their most recently seen Device. Missing → `config('notifications.default_timezone')` = `Europe/Skopje`. |
| D5 | One global switch, **`users.push_enabled`** (bool, default true). Off ⇒ nothing is sent; Devices are kept. |
| D6 | **Sent notifications are persisted** via Laravel's `database` channel (`notifications` table) alongside the push. The rows are the idempotency source for the nudge ladder — no `last_nudged_at` columns. |
| D7 | Expo `DeviceNotRegistered` ⇒ **delete the Device, keep the Sanctum token**. |
| D8 | **`NOTIFICATIONS_ENABLED`** env flag. Off ⇒ `ExpoChannel` logs instead of sending, scheduler still runs (so it can be observed). Devices carry `build_profile`; production only pushes to `production` Devices. |
| D9 | Inactivity is measured from the last Completed Session's `completed_at`, else `users.onboarding_completed_at`. Ladder 3 / 7 / 14 days, 18:00 Device-local, then silence until a session is completed. Skip while onboarding incomplete, while a `draft`/`active` session exists, while `push_enabled` is false. |
| D10 | Nudge taps open `fitnation://dashboard`. |
| D11 | Copy lives in `lang/en/notifications.php`. |

## Schema

Three migrations.

**`create_devices_table`**

```php
Schema::create('devices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('personal_access_token_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('push_token')->unique();           // ExponentPushToken[...]
    $table->string('platform', 10);                    // ios | android
    $table->string('timezone', 64)->nullable();        // IANA
    $table->string('app_version', 32)->nullable();     // 1.0.5
    $table->string('build_profile', 32)->nullable();   // development | preview | production
    $table->string('device_name')->nullable();
    $table->timestamp('last_seen_at');
    $table->timestamps();
});
```

`user_id` is denormalised from the token's `tokenable_id` so the scheduler can
query Devices by user without joining through a polymorphic column. Keep them
consistent in the one place Devices are written (`DeviceRegistration`, below).
Users are soft-deleted, so the cascade on `user_id` fires only on a hard delete;
the cascade on the token is what does the work day to day, and
`UserController::destroy` already deletes the tokens.

**`add_push_enabled_to_users_table`** — `boolean('push_enabled')->default(true)`.

**`create_notifications_table`** — `php artisan make:notifications-table`, as
shipped. Do not customise it.

## Modules

Follow the house rules in [README](README.md): modules in
`app/Services/<Area>/`, named for the concept, own their own loading, docblocks
in the tone of `SessionDetail`.

### `App\Models\Device`

Relations: `user()`, `token()` (`belongsTo PersonalAccessToken`). Scope
`pushable()` = joined to a user with `push_enabled` and, when
`config('notifications.only_build_profile')` is set, matching `build_profile`.
No business logic here beyond `timezone(): CarbonTimeZone` with the D4
fallback.

### `App\Services\Notifications\DeviceRegistration`

The single write path for Devices. `register(User $user, PersonalAccessToken
$token, DeviceRegistrationData $data): Device`, in one transaction:

1. delete any Device whose `push_token` matches but whose
   `personal_access_token_id` differs (D3);
2. `updateOrCreate` on `personal_access_token_id` with the payload and
   `last_seen_at = now()`.

`DeviceRegistrationData` is a readonly DTO built by the form request; validation
happens there, not here.

### `App\Notifications\Channels\ExpoChannel`

The seam. `send($notifiable, Notification $notification)`:

- collects the notifiable's `devices()->pushable()` Push Tokens; none ⇒ return;
- calls `$notification->toExpo($notifiable): ExpoMessage` — a small value
  object (`title`, `body`, `data`, `sound`, `channelId`, `badge`);
- POSTs to Expo in chunks of 100 with the access token, `Http::retry(3, 500)`;
- stores the returned **ticket ids** in the `notifications.data` JSON of the
  row the `database` channel just wrote — `data.expo_tickets: {device_id:
  ticket_id}`. No separate `push_tickets` table: the receipt job reads the
  rows it needs by `created_at` window, and one table fewer is the point;
- a ticket that comes back with an error immediately (Expo does this for some
  `DeviceNotRegistered` tokens) is handled exactly as a receipt error, below.

When `NOTIFICATIONS_ENABLED` is false, log the would-be request at `info` and
return.

Register the channel in `AppServiceProvider` via
`Notification::extend('expo', …)`. Notifications declare
`via(): ['database', 'expo']` — database first, so the row exists before the
ticket ids are written to it.

### `App\Jobs\FetchExpoReceipts`

Scheduled every 15 minutes. Selects `notifications` rows created between 15 and
90 minutes ago that carry `expo_tickets`, POSTs the ticket ids to
`/--/api/v2/push/getReceipts` in chunks of 1000, and for each receipt:

- `ok` ⇒ nothing;
- `DeviceNotRegistered` ⇒ delete that Device (D7);
- any other error ⇒ `Log::warning` with the ticket, device id and message.

Then strips `expo_tickets` from the row so it is not fetched again. Receipts
expire on Expo's side after 24 hours, so the 90-minute upper bound leaves
slack for a stalled worker without letting the window grow unbounded.

### `App\Services\Notifications\Inactivity`

The Inactivity Nudge rule, as a read. `dueAt(CarbonImmutable $now):
Collection<int, InactivityNudgeCandidate>` — every user who, evaluated at `$now`,
is in a Device timezone where the local time is 18:00 (within the run's
15-minute slot), whose inactivity in whole days is ≥ 3, and whose ladder step
for that inactivity has not been sent:

```
step  = 14 if days >= 14 else 7 if days >= 7 else 3 if days >= 3 else none
sent  = notifications where type = InactivityNudge::class
        and notifiable = user
        and created_at > (last completed_at ?? onboarding_completed_at)
        and data.step = step
due   = step is not none and sent is empty and not excluded
```

Exclusions per D9. Users with no Device are not candidates (nothing to send to,
and no timezone). The whole thing is one query per timezone bucket, not one per
user — start from `devices` grouped by `timezone`, filter buckets to those at
18:00 local, then join users and aggregate their last completed session. Write
it, then check it with `DB::enableQueryLog()` in the test: the count must not
grow with the number of users.

### `App\Notifications\InactivityNudge`

`ShouldQueue`. Constructor takes `int $step`. `via()` = `['database', 'expo']`.
`toArray()` = `['step' => $step]` plus whatever the inbox will want later
(`title`, `body`, `url`). `toExpo()` reads `lang/en/notifications.php` keys
`inactivity.{step}.title` / `.body`, `data.url = 'fitnation://dashboard'`,
`channelId = 'default'`.

### `App\Console\Commands\SendInactivityNudges`

`notifications:inactivity` — thin: `Inactivity::dueAt(now())` then
`$user->notify(new InactivityNudge($step))` per candidate. Scheduled
`everyFifteenMinutes()` in `routes/console.php`, `withoutOverlapping()`,
`onOneServer()`.

### `App\Console\Commands\PushTest`

`push:test {user} {--title=} {--body=}` — sends an ad-hoc notification through
the same channel to one user's Devices and prints the tickets. For humans
holding a phone; not scheduled.

## Endpoints

Under `auth:sanctum`. Both are added to `API_DOCUMENTATION.md`.

**`PUT /api/devices`** — idempotent upsert for the calling session.

Request:
```json
{
  "push_token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]",
  "platform": "ios",
  "timezone": "Europe/Skopje",
  "app_version": "1.0.5",
  "build_profile": "production",
  "device_name": "iPhone 15"
}
```
Rules: `push_token` required, `regex:/^Expo(nent)?PushToken\[[\w-]+\]$/`, max
255; `platform` required, `in:ios,android`; `timezone` nullable, `timezone`
rule; the rest nullable strings. Response `200 {"data": DeviceResource}` where
`DeviceResource` is `id, platform, timezone, app_version, last_seen_at`. The
Sanctum token comes from `$request->user()->currentAccessToken()`.

**`PATCH /api/notification-settings`** — `{"push_enabled": bool}`, required
boolean. Response `200 {"user": UserResource}`.

**`UserResource`** gains `push_enabled`. Note the resource is also consumed by
the web app; an additive key is safe.

No `DELETE`. See ADR-0003 for why that is a decision and not an omission.

## Config

`config/notifications.php`:

```php
return [
    'enabled' => env('NOTIFICATIONS_ENABLED', false),
    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'send_url' => 'https://exp.host/--/api/v2/push/send',
        'receipts_url' => 'https://exp.host/--/api/v2/push/getReceipts',
    ],
    'only_build_profile' => env('NOTIFICATIONS_BUILD_PROFILE'), // 'production' in prod, null elsewhere
    'default_timezone' => 'Europe/Skopje',
    'inactivity' => ['ladder' => [3, 7, 14], 'local_hour' => 18],
];
```

`.env.example` gains the three env keys, commented.

## Copy — `lang/en/notifications.php`

```php
'inactivity' => [
    3  => ['title' => 'Ready when you are',      'body' => "It's been 3 days. Your next workout is waiting."],
    7  => ['title' => 'A week off',              'body' => 'Pick it back up today — even one session counts.'],
    14 => ['title' => 'We miss you at the gym',  'body' => "Two weeks is a long time. Let's get one in."],
],
```

## Tests

`tests/Feature/` per `CLAUDE.md`. Fake Expo with `Http::fake()`; never hit the
network. Freeze time with `Carbon::setTestNow` / `$this->travelTo`.

**Devices**
- registering creates a Device bound to the calling token, with `user_id`
  matching
- registering again from the same token updates in place (new tz, new version,
  `last_seen_at` moves) — one row
- the same `push_token` from a second token moves: old row gone, new row present
- logging out deletes the Device (token delete cascades)
- `DELETE /user` deletes the Devices
- invalid token format, unknown platform, bad tz ⇒ 422
- web-style session with no Device: `PUT /devices` from a token still works
  (no assumption that a Device pre-exists)

**Settings**
- `PATCH /notification-settings` flips `push_enabled`; `GET /user` reflects it

**ExpoChannel**
- a notification `via ['database','expo']` writes one `notifications` row and
  one Expo request per 100 tokens, with the bearer header
- `push_enabled = false` on a user who is nonetheless notified directly (e.g.
  `push:test`) ⇒ database row written, **no** Expo request — the `pushable()`
  scope returns no Devices. The channel does not otherwise consult the flag;
  keeping such users out of the nudge altogether is `Inactivity`'s job and is
  asserted there.
- `NOTIFICATIONS_ENABLED=false` ⇒ no request, one log line
- `only_build_profile=production` ⇒ a `development` Device gets nothing
- ticket ids land in `notifications.data.expo_tickets`

**Receipts**
- `DeviceNotRegistered` receipt deletes the Device and leaves the token
- `ok` receipt leaves everything; `expo_tickets` is removed from the row
- rows older than 90 minutes are not fetched

**Inactivity** — the bulk of the value; be thorough
- day 2 ⇒ no candidate; day 3 at 18:00 local ⇒ candidate with step 3
- 18:00 in `Europe/Skopje` is 16:00 UTC: at 16:00 UTC the Skopje user is due
  and a `UTC` user is not; at 18:00 UTC the reverse
- a user whose Device has no timezone is evaluated in `Europe/Skopje`
- a user with two Devices in different timezones uses the most recently seen
- step 3 sent ⇒ day 4, 5, 6 not a candidate; day 7 ⇒ step 7; day 14 ⇒ step 14;
  day 21 ⇒ nothing
- a Completed Session on day 10 resets: step 3 becomes due again on day 13
  (i.e. `created_at > last completed_at` is what makes old rows irrelevant)
- zero sessions ⇒ measured from `onboarding_completed_at`
- onboarding incomplete ⇒ excluded; `active` session exists ⇒ excluded;
  `push_enabled=false` ⇒ excluded; no Device ⇒ excluded
- a `cancelled` session does not count as training (Completed Session is the
  only definition)
- query count does not grow with user count (10 users vs 100 users, same
  number of queries)

**Command** — `notifications:inactivity` notifies exactly the candidates, and
running it twice in the same slot sends nothing the second time.

## Order of work

1. Migrations, `Device` model, `DeviceRegistration`, `PUT /devices`,
   settings endpoint, `UserResource` key. Tests. PR 1.
2. `config/notifications.php`, `ExpoChannel`, `ExpoMessage`, `PushTest`
   command, receipts job + schedule. Tests. PR 2. At this point a human can
   `push:test` a real phone running the spec-0012 build.
3. `Inactivity`, `InactivityNudge`, the command and its schedule, copy. Tests.
   PR 3.
4. Production: follow [DEPLOY_LIGHTSAIL.md](../DEPLOY_LIGHTSAIL.md), set
   `NOTIFICATIONS_ENABLED=true`, `NOTIFICATIONS_BUILD_PROFILE=production`.

Branch per PR, `feat/push-devices`, `feat/push-expo-channel`,
`feat/push-inactivity-nudge`. `./vendor/bin/pint` on your own files only.

## Notes

- `Api/AuthController` creates every token as `'auth-token'`. Leave that; the
  Device row is where a session's identity now lives. Do **not** start naming
  tokens after devices as a side quest.
- `WorkoutSessionController::complete()` is not touched. Phase two's
  event-driven notifications will want a `WorkoutSessionCompleted` event
  there; do not add it speculatively.
- Expo's rate limit is 600 notifications/second/project — irrelevant at this
  scale, but chunking at 100 is the documented request maximum, not an
  optimisation.
- `phpunit.xml` pins `DB_DATABASE=muscle_hustle`; do not run these tests in
  parallel with another agent's run.
- New domain terms were added to `CONTEXT.md` during the design session. If
  implementation forces a change to one of them, change the glossary in the
  same PR and say so in the description.
