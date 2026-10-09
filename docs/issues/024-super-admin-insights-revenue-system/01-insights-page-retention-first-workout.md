# 01 — Insights page: Training tab, range, retention and time to first workout

**Parent:** [024 — Super-admin panel: Insights, Revenue, and the rest of System](../024-super-admin-insights-revenue-system.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** `/admin/insights` stops being a placeholder.
- The page has a **Training | Revenue** tab strip. Revenue is a route that renders an empty tab for now; ticket 04 fills it.
- One fixed **7 / 30 / 90** range control applies to the whole Training tab. It is kept in `?range=`, defaults to 30, and an invalid value falls back to 30.
- New `Insights` module, shaped like `Overview`: one static `summary(int $days)`, a docblock describing the structure, cached for 10 minutes per range, app users only, Completed Sessions only. Constants: retention weeks 1, 2, 4, 8.
- The first two cards, in the prototype's question-wall layout (headline answer, one chart, one footnote):
  - **Retention by signup week:** the week-4 share is the headline. Each week's denominator only counts signups old enough to have reached it.
  - **Time to first workout:** the median hours, compared with the range before, plus the six buckets including "not yet".
- Set up the ApexCharts approach every later card reuses: colours read from tokens, light and dark steps as in the spec, visible value labels, re-theme on the dark toggle.

**Blocked by:** None — can start immediately.

**Status:** done

- [x] `Insights::summary` returns `retention` and `first_workout` as the spec defines them. Tested with frozen time and factory fixtures, covering the Seam 1 retention and first-workout cases. Staff never count. Range 7 vs 90 changes what's included.
- [x] Page renders both cards for a super admin at each range and at an invalid range. Partner admin and plain user get 403.
- [x] Sidebar Insights entry still active on both tabs. Dark mode readable.
