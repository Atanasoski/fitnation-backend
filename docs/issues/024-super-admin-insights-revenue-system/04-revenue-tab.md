# 04 — Revenue tab

**Parent:** [024 — Super-admin panel: Insights, Revenue, and the rest of System](../024-super-admin-insights-revenue-system.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** The Revenue tab under Insights (prototype Revenue B, but **USD only**, with no currency switcher). Prefactor first, in its own commit: make the product → plan (monthly or yearly) mapping in `AccessSources` shared, so there is no second copy.
- New `Revenue::summary()`: current state only, `environment = production` rows only, cached 10 min. It returns sandbox_excluded, paying (monthly and yearly), trials, billing_issue (through `AccessSources::constrain`), expected_monthly_usd, by_store_and_plan, conversion per plan, and unknown_price. Definitions are in the spec. **`price` is RevenueCat's USD price**: never pair it with `currency`.
- Page:
  - four tiles: Expected Monthly Revenue (USD, labelled as RevenueCat's USD conversion), paying, in trial, and billing issue (linking to the Users list `access=billing_issue`);
  - a stacked bar of where the money comes from (store × monthly/yearly);
  - trial → paid per plan, with its assumption in a footnote;
  - the sandbox count;
  - the dashed "not in this version" note.
- Add a model docblock or column comment saying `price` is USD.

**Blocked by:** 01 (tab strip and route)

**Status:** ready-for-agent

- [ ] Prefactor commit: shared plan mapping; existing AccessSource tests green.
- [ ] `Revenue::summary` tested per Seam 2: yearly ÷ 12, a trial contributes 0, a null price is counted but earns nothing, sandbox excluded, expired not paying, billing-issue parity with the Users filter, conversion fixtures, Play `product:base_plan` ids.
- [ ] Tab renders for a super admin; 403 for others.
