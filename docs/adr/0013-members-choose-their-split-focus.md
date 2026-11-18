# 13. Members choose their Split Focus; gender does not

Date: 2026-11-18

## Status

Accepted

## Context

A Program is generated from a Split — the catalogued set of weekly training days, keyed by training days per week and Split Focus. All three Split Focus variants (balanced, upper focus, lower focus) have been seeded for 1 through 7 training days since the splits table was introduced: 84 rows.

Only one of them was reachable. The Split Focus was derived from the member's gender — `Female ⇒ lower focus`, everyone else ⇒ `balanced` — in two places, the program generation service and the admin workout preview. The rule was invisible: no screen in any client mentioned it, and no member could override it.

So a woman who wanted to prioritise upper body could not get an upper-focus Program, a man who wanted a leg-focused Program could not get one, and neither was ever told why their week looked the way it did. Two thirds of the curated Split content was unreachable by the entire member base.

## Decision

**Split Focus is the member's own choice**, stored on their profile beside the other four plan inputs (fitness goal, training experience, training days per week, session duration). The clients ask for it during onboarding and let the member change it afterwards.

**Gender no longer takes part in Program generation**, in either place that derived a focus from it. It is not retained as a fallback: keeping it would leave the rule alive in code and surprise a member who cleared their setting. Gender remains on the profile and keeps its other role — an input to fitness metrics and starting weights.

**A null Split Focus means Balanced** — the week the majority already received.

The stored enum values are unchanged (`balanced`, `upper_focus`, `lower_focus`). The mild redundancy of `split_focus: "upper_focus"` is not worth a data migration across the 84 seeded Split rows and the admin CRUD that writes them.

## Consequences

- **New members no longer default to a lower-focus Program on the strength of their gender.** Everyone starts Balanced unless they choose otherwise. Accepted deliberately: a defensible default that the member can see and change beats an invisible one they cannot.
- **Existing profiles are written to their current effective focus by a one-off operation run against production after this ships**, not by a migration, so nobody's next refresh silently changes shape. Between the schema change landing and that operation running, a member whose Program was lower-focus by gender who refreshes receives a Balanced one.
- **Two thirds of the seeded Split catalog becomes reachable**, and therefore becomes content that must be maintained. The weekly volume audit now covers the 4-day upper-focus and lower-focus Splits as well as the balanced one.
- **A member training 1 or 2 days a week who picks Upper Body gets no lower-body training at all.** The live upper-focus Splits at those frequencies are Upper Body, and Push / Pull. Accepted deliberately (#146): an emphasis cannot meaningfully differ from balanced in a one- or two-day week — adding a leg day would make upper focus identical to the balanced Split at both frequencies — and this is an explicit member choice rather than a silent default. Do not "fix" it by adding a leg day; that removes the choice instead of honouring it.
- **At 4 training days, the lower-focus and balanced Splits were the same four day types in a different order**, delivering identical weekly volume, so picking Lower Body changed nothing. Being corrected in the live catalog to a three-leg-day shape (#146).
- A Split Focus is not accepted as a per-request generation preference. The clients save the profile and then regenerate; a request-level override would be a second way to say the same thing.
- **Do not reintroduce gender-derived personalisation of Split selection.** It reads as a helpful touch and is the exact behaviour this decision removed. `SplitFocusTest` asserts that two members differing only in gender are given the same Program.
