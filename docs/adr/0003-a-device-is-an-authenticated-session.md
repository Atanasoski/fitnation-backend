---
status: accepted
---

# A Device is an authenticated session

A push-capable [Device](../../CONTEXT.md#device) is bound one-to-one to the
Sanctum personal access token it was registered under, and is deleted with it.
There is no independent device lifecycle: logging out, revoking a token, or
deleting an account ends the Device as a side effect, and the mobile app has no
"unregister" call to make.

We chose this over a free-standing `devices` table keyed only by user because
every Sanctum token in this codebase is created with the same name
(`'auth-token'`, `Api/AuthController`), so the token row is the only thing that
already identifies *one phone signed in as one user*. Binding to it gives us
that identity for free, and removes the failure the standalone shape invites:
the app logs out, the unregister request is lost, and a phone that now belongs
to someone else keeps receiving the previous user's notifications.

## Considered Options

- **Standalone devices table (`user_id`, `push_token`), unregister on logout.**
  Rejected: unregister is a best-effort network call made at the moment the
  user is leaving, which is exactly when it is most likely to be dropped.
  `AuthContext.logout()` already tolerates a failed server logout and proceeds
  locally; an unregister would have to as well, leaving a live token behind.
- **One push token per user, overwritten on each login.** Rejected: a user
  with a phone and a tablet is ordinary, and the last device to log in would
  silence the other.
- **Bind to the Sanctum token (chosen).** Costs one foreign key with
  `cascadeOnDelete` and a rule that re-registering a push token under a new
  session moves it (a phone has one owner at a time). Gains: no unregister
  endpoint, no orphaned tokens, account deletion (`UserController::destroy`
  already calls `$user->tokens()->delete()`) needs no new code.

## Consequences

- A user who is logged in on the web has no Device — only mobile sessions
  register one. That is correct; the web cannot receive push.
- A token the push relay reports dead (`DeviceNotRegistered`) deletes the
  Device **but not the Sanctum token**: an uninstalled app is not a request to
  be logged out elsewhere. The session simply has no Device any more, and will
  get one again if the app is reinstalled and reopened.
- If Sanctum token expiry is ever enabled (`expires_at` is nullable and unused
  today), pruning expired tokens will prune their Devices too, which is the
  intended behaviour.
