# 01 — Partner edit: four colours, logo, live preview

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md). Prototype: partner variant A.

**What to build:** rebuild `partners/edit` and `partners/create` in the admin shell as prototype A. The form is on the left: name, slug, domain, Active, logo, and primary and secondary for light and dark. A sticky light and dark preview is on the right and updates live. All other identity columns leave the form and are never written by it. Unchecking Active works, and the House Partner is refused through the shared rule.

**Blocked by:** —

**Status:** ready-for-agent

- [ ] Characterization commit first: `PartnerManagementCharacterizationTest` covers the current update. Add a case with stored hidden columns.
- [ ] Form posts only name, slug, domain, `is_active` (with hidden 0), logo and the four colours. The four are required on the web form, prefilled from the stored value or the config default.
- [ ] Hidden columns (other colours, `font_family`, `background_pattern`) are unchanged after save. Tested.
- [ ] FormRequest rules for the hidden fields stay, because the API uses them.
- [ ] Preview uses stored or default backgrounds and text colours (`ColorHelper`), plus a white-on-primary contrast ratio.
- [ ] House Partner deactivation is refused via the rule `Admin\PartnerController::updateActive` uses (shared, not copied).
- [ ] A partner admin edits only their own partner, with the same form. Non-admins otherwise get 403.
