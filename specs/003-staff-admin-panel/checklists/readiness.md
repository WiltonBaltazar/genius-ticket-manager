# Readiness Checklist: Staff Admin Panel (Events, Ticket Types & Orders)

**Purpose**: Validate the quality (completeness, clarity, consistency, measurability) of the requirements in `spec.md` — and, where a planning decision stands in for a requirement the spec never made explicit, in `plan.md`/`data-model.md`/`research.md` — across three focus areas: authorization/RBAC, data-integrity/migration safety, and admin UX/Filament form requirements. Standard depth: a practical pass, not an exhaustive enumeration of every conceivable case.
**Created**: 2026-08-04
**Feature**: [spec.md](../spec.md) · [plan.md](../plan.md) · [data-model.md](../data-model.md) · [research.md](../research.md)

**Note**: This checklist tests whether the *requirements are well-written*, not whether the implementation works. Items are questions about the spec/plan text itself.

## Authorization & RBAC Requirement Quality

- [ ] CHK001 - Are permission requirements defined for every combination of the 4 roles × 3 resources (Event, Ticket Type, Order) × action (view/create/edit/delete)? [Completeness, Spec §FR-004, data-model.md Role→Resource matrix]
- [x] CHK002 - Is the behavior for a staff account with no role assigned (`null` or an unrecognized value) specified in `spec.md` itself, rather than only decided later in `research.md`/`data-model.md`? [Gap, Traceability, Spec §FR-004] — **Resolved**: FR-004 now states this directly ("A staff account whose role does not match one of these four values... MUST be treated as having the same access as gate operator").
- [ ] CHK003 - Are requirements defined for what happens to a staff member's active admin-panel session if their account or role changes mid-session? [Gap, Spec Edge Cases]
- [ ] CHK004 - Are requirements defined for two staff members editing the same Event or Ticket Type concurrently? [Gap, Spec Edge Cases]
- [ ] CHK005 - Is "internal notes visible only to staff" (FR-006) precise about whether that means *all* staff roles or only roles with view/edit access to that event? [Ambiguity, Spec §FR-006]
- [ ] CHK006 - Are the "view orders" role restrictions consistent across FR-015 (field visibility), FR-018 (resource-level restriction), and FR-019 (dashboard visibility)? [Consistency, Spec §FR-015, §FR-018, §FR-019]
- [ ] CHK007 - Do FR-010 (event delete restricted to super admin) and User Story 2's acceptance scenarios agree on exactly which roles may even attempt event deletion? [Consistency, Spec §FR-010, User Story 2 AS4]
- [ ] CHK008 - Is SC-002's "100% of unauthorized attempts refused... across all four roles" specific enough to derive a complete, enumerable test matrix from spec.md alone (without consulting plan.md)? [Measurability, Spec §SC-002]
- [ ] CHK009 - Is SC-006 ("staff never see a navigation entry or action control for a resource their role cannot use") specific about which surfaces are in scope — panel navigation, per-record action buttons, and the dashboard widget — or does it leave one of those surfaces ambiguous? [Clarity, Spec §SC-006]

## Data Integrity & Migration Requirement Quality

- [x] CHK010 - Does the spec or plan define what happens to any pre-existing Event rows whose `status` uses a value from the old `EventStatus` set (`sold_out`, `completed`, `cancelled`) once the enum is realigned to the four spec'd values? [Gap, research.md §2] — **Resolved**: research.md §2 now documents a pre-existing-row safety check (confirmed no such rows can exist pre-launch) plus a defensive backfill statement added to the migration as cheap insurance.
- [x] CHK011 - Is `end_date`'s auto-derivation from `start_date` (data-model.md, `events` table) traceable to an explicit requirement in spec.md, or does it exist only as a planning-level invention with no spec-level acceptance scenario covering it? [Traceability, Gap, Spec §FR-006] — **Resolved**: spec.md's Assumptions now states the staff-entered date/time is the sole source of truth and any other internal scheduling bookkeeping is derived automatically, without naming the implementation-level column.
- [ ] CHK012 - Are requirements defined for what staff see or can do when a hero image upload fails or exceeds an (undefined) size/format limit? [Gap, Spec Edge Cases]
- [ ] CHK013 - Is the sales-window edge case ("sales end date before sales start date") resolved into an actual validation requirement, or does it remain only a posed-but-unanswered question in Edge Cases? [Gap, Spec Edge Cases]
- [ ] CHK014 - Is FR-014's "no floating-point rounding or precision loss" objectively verifiable given spec.md's own Assumptions defer the storage representation to planning? [Measurability, Spec §FR-014, Assumptions]
- [ ] CHK015 - Is "once any tickets for it have sold" (FR-013) unambiguous for the boundary case where a partial refund could raise `available_quantity` back toward `total_quantity` — does the requirement say whether the lock re-opens? [Ambiguity, Spec §FR-013, Edge Cases]
- [ ] CHK016 - Do the Key Entities field lists for Event and Ticket Type stay consistent with the Functional Requirements field lists (FR-006, FR-011) after the Clarifications session's edits to both? [Consistency, Spec §Key Entities, §FR-006, §FR-011]

## Admin UX / Filament Form & Table Requirement Quality

- [ ] CHK017 - Is required-vs-optional explicitly stated for every EventResource form field named in FR-006 (e.g., is the hero image required or optional)? [Clarity, Spec §FR-006, Assumptions]
- [ ] CHK018 - Are empty-state requirements defined for the Events list and the Orders list when no records exist yet? [Gap, Coverage]
- [ ] CHK019 - Is User Story 5 AS2's "reloads the dashboard" phrasing explicit about whether any live/real-time update is expected, or does it deliberately scope that out? [Clarity, Spec User Story 5 AS2]
- [ ] CHK020 - Are table sort/pagination/default-ordering requirements specified for the Events list, or left entirely to implementation discretion? [Gap]
- [ ] CHK021 - Is the rich-text description field's behavior when left empty (nullable per data-model.md) traceable to an explicit spec statement, or only inferred? [Gap, Spec §FR-006]

## Non-Functional Requirements

- [ ] CHK022 - Are accessibility (WCAG) requirements for the admin panel stated directly in spec.md, or only inherited implicitly from the constitution without a spec-level cross-reference? [Traceability, Gap, Constitution Principle V]
- [ ] CHK023 - Are any performance expectations (page load, dashboard query time under realistic order volume) stated, or is this intentionally left unscoped? [Gap]
- [x] CHK024 - Is the audit-logging exclusion for Event/Ticket Type CRUD (Clarifications, Assumptions) traceable to the constitution's Principle IV language, so a reviewer can confirm it isn't an overlooked compliance gap rather than a deliberate scope boundary? [Traceability, Spec Assumptions, Constitution Principle IV] — **Resolved**: the Assumptions bullet now explicitly cites "this project's governing constitution's data-integrity principle" and states it's a deliberate, confirmed scope boundary.

## Scenario & Edge Case Coverage

- [ ] CHK025 - Are exception/recovery requirements defined for a failed Event or Ticket Type save beyond generic field-level validation errors (e.g., a mid-submit server error)? [Gap, Exception Flow]
- [ ] CHK026 - Are requirements defined for a staff member opening an edit form for an Event or Ticket Type that another staff member deleted moments earlier (stale-record edit)? [Gap, Edge Case]
- [ ] CHK027 - Is the "order with zero line items" edge case (Spec Edge Cases) resolved into a stated display requirement, or left open? [Gap, Spec Edge Cases]
- [ ] CHK028 - Is the total-quantity-lock race condition ("edit attempted at the exact moment the first sale is recorded," Spec Edge Cases) resolved into a stated requirement (e.g., which side wins), or left open? [Gap, Spec Edge Cases]

## Dependencies & Assumptions

- [ ] CHK029 - Is the assumption that "the gate operator role already exists in the system for other purposes" (Spec Assumptions) distinguishable from a requirement this feature must itself satisfy — i.e., is it clear this feature doesn't need to create that role's other behavior? [Clarity, Spec Assumptions]
- [ ] CHK030 - Is FR-020's "placeholder, non-production credentials" specific enough to prevent an accidental production seed with guessable credentials (e.g., does it require the credentials be environment-gated or documented separately)? [Clarity, Risk, Spec §FR-020, Assumptions]
- [ ] CHK031 - Is the dependency on feature 001's already-shipped `events`/`ticket_types`/`staff` schema explicitly acknowledged in spec.md, so a reader unfamiliar with feature 001 understands this feature only *extends* existing tables? [Dependency, Spec]

## Ambiguities & Conflicts

- [x] CHK032 - Does FR-004's "super admin: full access, including delete" conflict with the absence of any delete requirement for Ticket Types anywhere else in spec.md (FR-011 only lists create/edit)? [Conflict, Spec §FR-004, §FR-011] — **Resolved**: FR-004 now scopes "including delete" explicitly to events ("the only delete action this feature defines for any resource"), and FR-011 explicitly states no role can delete a ticket type through this panel.
- [ ] CHK033 - Is there a stated resolution for "standard CRUD actions" (spec.md Assumptions) applying fully to Events but only partially ("narrower... rules") to Ticket Types, in a way a reader would find without also reading the Assumptions section? [Ambiguity, Spec §FR-009 vs Assumptions]

## Notes

- Focus areas selected: Authorization/RBAC, Data integrity/migration safety, Admin UX/Filament forms, plus a broad general-readiness pass — all four were requested together, so this checklist treats them as one combined pass rather than separate files.
- Depth: Standard (thorough, practical — not an exhaustive formal gate).
- Audience/timing: author self-review before running `/speckit-tasks` (default, since no PR/reviewer context exists yet for this feature).
- CHK002, CHK010, CHK011, CHK024, and CHK032 surfaced the same class of finding: real decisions already made and justified in `research.md`/`plan.md` during Phase 0/1 planning that weren't traceable back to an explicit spec.md requirement. **2026-08-04: all five resolved** — spec.md's FR-004, FR-011, and Assumptions were updated to state them explicitly, and research.md §2 gained a pre-existing-row safety check plus a defensive migration statement for the `EventStatus` realignment. See each item above for what changed.
- Check items off as completed; add findings inline as you resolve each one.
