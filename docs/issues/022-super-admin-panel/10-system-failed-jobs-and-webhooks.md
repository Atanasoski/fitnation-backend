# 10 — System: failed jobs and webhooks

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md).

**What to build:** The System page lists failed queue jobs (job, queue, when, first line of the error) with Retry and Delete, and failed RevenueCat webhook calls with Replay one / Replay all.

**Blocked by:** 01.

**Status:** ready-for-agent

- [ ] Failed jobs listed newest first; Retry puts the job back on the queue; Delete removes it
- [ ] Failed webhook calls (exception set) listed; Replay clears the exception and dispatches the processing job — same logic as `ReplayFailedRevenueCatWebhooks`, shared, not copied (command keeps working)
- [ ] Empty states when nothing failed
- [ ] Feature tests with `Queue::fake`; non-admins 403
