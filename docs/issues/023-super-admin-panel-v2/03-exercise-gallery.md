# 03 — Exercise gallery with slide-over editor

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md). Prototype: exercises variant C.

**What to build:** replace `exercises/admin/*` with a server-rendered gallery of cards (image, video badge, equipment, primary muscles, difficulty, priority, override count). Add facet filters with counts (region, equipment, difficulty), missing-media and archived toggles, and `q` search via `Exercise::scopeSearch`, all in the URL and paginated. `?create=1` and `?edit={id}` open a slide-over with media, basics (name, description, difficulty, selection priority 0–1000, default rest), classification (the five lookups, muscle chips: one click primary, two secondary, three clear; training styles), and the muscle-group image with Regenerate. Saves redirect back to the same gallery URL. Bulk select runs archive-or-delete (from 02). Archived cards show Restore.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Characterization commit first: admin exercise store and update (no tests today).
- [ ] `StoreExerciseRequest` accepts image and video uploads like update. Add `difficulty` (enum) and `selection_priority` (0–1000) to both requests and the controller.
- [ ] Old `exercises.show`, `exercises.edit` and `exercises.create` redirect to the gallery with the slide-over open.
- [ ] Facet counts reflect the other active filters. Filters round-trip through the URL. Tested.
- [ ] Delete in the slide-over and the bulk action use the 02 module, and the flash says archived vs deleted.
- [ ] Muscle-group image Regenerate uses `exercises.updateMuscleGroupImage`.
- [ ] Non-admins get 403 on every route.
