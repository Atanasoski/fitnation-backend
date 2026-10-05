# 02 — Archived Exercise: delete never destroys history

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md). Term: [Archived Exercise](../../../CONTEXT.md#archived-exercise).

**What to build:** a nullable `archived_at` on `workout_exercises`, and one module in `app/Services/Exercise/` that owns delete-or-archive. A used exercise (any template row, session exercise or set log) is archived. An unused one is hard-deleted. Restore clears it. Add an `available()` scope and apply it to the catalogue listing and search, the pickers, the partner library, `syncDefaultExercises` and the generators. Do **not** apply it to relations, show-by-id or session detail. Web destroy and the API destroy both go through the module. Do not use `SoftDeletes`.

**Blocked by:** —

**Status:** ready-for-agent

- [ ] Characterization commit first: lock the current admin exercise destroy and the API destroy (no tests today).
- [ ] Migration adds `archived_at`, nullable and indexed.
- [ ] Module: archive if used, delete if not, restore. Bulk variant returns archived and deleted counts.
- [ ] `available()` is applied at every listing and picker site above. The generator candidate set excludes archived exercises.
- [ ] `GET /api/exercises/{id}`, session detail and template resources still return archived exercises.
- [ ] Tests: the four fixtures from Seam 2 in the spec. Referencing rows survive archiving.
- [ ] Release note line: archived exercises leave the app's search and pickers.
