# 04 — Partner Overrides editable by the super admin

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md). Term: [Partner Override](../../../CONTEXT.md#partner-override).

**What to build:** in the exercise slide-over, list every partner linked to the exercise with its override (description, image, video). The super admin can edit or clear each one, and can link the exercise to a partner or unlink it (confirm: unlinking hides it from that partner's members). Extract the override write from `ExerciseController::updatePartnerExercises` into one module (files via `PartnerExerciseFileService`). The partner-admin route and a new admin route (`/admin/exercises/{exercise}/partners/{partner}`) both call it. Reads stay on `PartnerExerciseView`.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] Characterization commit first: the partner-admin override update (description, image, video, remove_video).
- [ ] Shared override-write module. The partner-admin controller now calls it, and the characterization stays green.
- [ ] Admin routes: update or clear an override, link, unlink. Admin only.
- [ ] Tests: super admin edits an override and `PartnerExerciseView` reflects it. Clear restores the catalogue values. Link and unlink. Non-admins get 403.
- [ ] `PartnerExercisePagesTest` stays green.
