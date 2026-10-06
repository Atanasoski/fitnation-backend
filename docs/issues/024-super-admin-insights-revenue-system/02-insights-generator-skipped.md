# 02 — Insights: generator quality and most-skipped exercises

**Parent:** [024 — Super-admin panel: Insights, Revenue, and the rest of System](../024-super-admin-insights-revenue-system.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** Two more cards on the Training tab, both following the range.
- **Are generated workouts any good?** Sessions created in the range, split by `is_auto_generated`. For each group: completed %, swapped % and cancelled %. A regenerated draft (cancelled, and pointed to by another session's `replaced_session_id`) counts as **swapped**, not cancelled. The headline compares the two completion rates.
- **Which exercises get skipped?** Over Completed Sessions completed in the range: per exercise, how often it was included and how often it had no set log. Keep exercises included at least 100 times (a constant). Show the top 10 by rate, each linking to its catalogue entry.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] `generator` and `skipped` keys in `Insights::summary`, tested per Seam 1: swapped vs cancelled, generated vs other, cancelled sessions not counted as skipped, below-minimum excluded.
- [ ] Cards render with the shared chart setup; the skipped list is a ranked bar list, not a chart.
