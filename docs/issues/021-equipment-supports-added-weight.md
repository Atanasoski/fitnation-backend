# 021 — Equipment types say whether an exercise takes added weight

**Area:** back-end / taxonomy
**Severity:** low (drift between client screens)
**Status:** done
**Origin:** app spec `docs/specs/0015` — the bodyweight rule had four client owners
and they disagreed about TRX.

## The decision

Whether an exercise accepts a logged weight is a fact about its equipment, so
the equipment type carries it and no client decides. `equipment_types` gains
`supports_added_weight` (boolean, default true) and `EquipmentTypeResource`
exposes it.

The seed mirrors what `ProgressionCalculatorService::getWeightIncrement()`
already assumed: **BODYWEIGHT, TRX and BAND carry no added weight**; everything
else does. BAND is a case the clients had never handled at all.

## Resolved

- Migration adds the column and backfills the three codes to `false`.
- `EquipmentTypeSeeder` uses `updateOrCreate`, so re-seeding syncs the flag onto
  existing rows; `firstOrCreate` never touched them.
- `EquipmentTypeSupportsAddedWeightTest` pins TRX specifically.
- Clients read `equipment_type.supports_added_weight` and delete their rules
  (app spec 0015 / 0023).

## Not done here

- `getWeightIncrement()` still keeps its own table of codes. It answers a
  different question (how much to step a weight by), but its zero entries and
  this flag must agree; if a future equipment type is added, set both.
