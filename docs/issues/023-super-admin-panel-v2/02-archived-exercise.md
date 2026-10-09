# 02 — Archived Exercise: delete never destroys history

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md). Term: [Archived Exercise](../../../CONTEXT.md#archived-exercise).

**What to build:** a nullable `archived_at` on `workout_exercises`, and one module in `app/Services/Exercise/` that owns delete-or-archive. A used exercise (any template row, session exercise or set log) is archived. An unused one is hard-deleted. Restore clears it. Add an `available()` scope and apply it to the catalogue listing and search, the pickers, the partner library, `syncDefaultExercises` and the generators. Do **not** apply it to relations, show-by-id or session detail. Web destroy and the API destroy both go through the module. Do not use `SoftDeletes`.

**Blocked by:** —

**Status:** done

- [x] Characterization commit first: lock the current admin exercise destroy and the API destroy (no tests today).
- [x] Migration adds `archived_at`, nullable and indexed.
- [x] Module: archive if used, delete if not, restore. Bulk variant returns archived and deleted counts.
- [x] `available()` is applied at every listing and picker site above. The generator candidate set excludes archived exercises.
- [x] `GET /api/exercises/{id}`, session detail and template resources still return archived exercises.
- [x] Tests: the four fixtures from Seam 2 in the spec. Referencing rows survive archiving.
- [x] Release note line: archived exercises leave the app's search and pickers.

**Release note:** Archived exercises disappear from the app's exercise search and pickers. Deleting an exercise someone used now archives it, and their plans and history keep it. `DELETE /api/exercises/{id}` then answers "Exercise archived successfully".

**Done notes:** module `App\Services\Exercise\ExerciseArchive` (`archiveOrDelete`, `archiveOrDeleteMany`, `restore`) returning `ArchiveOutcome`. Restore and bulk have no HTTP route yet; ticket 03 (gallery) adds them. The user-plan and library-plan show pages compute a picker list (`$workoutExerciseData`) that no view renders; `available()` is applied there, but only the workout page's picker is observable and tested.
