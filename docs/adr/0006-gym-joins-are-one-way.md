---
status: accepted
---

# Gym Joins are one-way

A Gym Join moves a customer from the House Partner to a Partner when they accept a Join Prompt. We decided the move is one-way: the API endpoint only accepts House → active Partner, never Partner → House or Partner → Partner. The app offers no way back and the restriction is not advertised in the Join Prompt; a customer who wants to move again emails support, and staff use the existing admin "move to partner" action, which can move anyone anywhere.

We chose this because the lock-in is the product we sell to gyms: a member whose app belongs to their gym (branding, program library, later member pricing) is a member the gym keeps. Free in-app switching would also reopen the abuse surface we deliberately left out of the MVP (claiming a gym's benefits from the sidewalk, gym-hopping for perks).

The alternative — symmetric switching with a cooldown — was rejected as it dilutes the gym pitch and costs the same support tooling anyway.

## Consequences

- `POST /api/me/partner` returns 403 for any caller not on the House Partner. There is deliberately no "leave gym" endpoint; do not add one without revisiting this ADR.
- Mistaken joins (GPS bleed from a 150–200 m radius, trial-day visitors) are permanent until support intervenes. Accepted: the admin reassign action is the escape hatch, and a low-prominence "to change gyms, contact support" line (settings/FAQ) is the only in-app mention.
- Declining a Join Prompt ("Not interested") is also permanent per Partner and has no in-app re-entry in the MVP — the same support path covers it. "Maybe later" (and any implicit dismissal) is only a 24-hour snooze and carries no permanence.
- A staff reassignment does not regenerate the customer's program; a program generated under one Partner may reference Exercises another Partner doesn't offer.
