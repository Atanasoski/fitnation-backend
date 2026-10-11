# 09 — Mobile: Signup Trial copy from the backend

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** The app talks only about the trial we run, with its length from the backend.

**Blocked by:** 05

**Branch:** `fix/subscription-hardening` in `front-end` (off `dev`). Work in `front-end/apps/mobile`; read its `CLAUDE.md` first. The back-end tickets it needs are already on the back-end branch.

**Status:** done

- [x] Store-trial copy ("7-Day") removed from the paywall.
- [x] End of onboarding shows "N days free" from `subscription.signup_trial_days` when above zero.
- [x] Profile card: Signup Trial → "Free trial · N days left"; Complimentary → "Free access until {date}"; no card while `subscription.enforced` is false.
- [x] Unit-tested (prior art: `paywallCopy.test.ts`). `pnpm test` and typecheck green.

## Notes / handoff

front-end `3fd1c23`, `46ce7bb`, `241847b` (onboarding promise hidden while not enforced, decided 2026-10-09). Open: test fixture `sub()` duplicated across 4 test files.
