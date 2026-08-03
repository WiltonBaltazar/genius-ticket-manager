# Requirements Quality Checklist: Core Ticketing Data Model

**Purpose**: Broad pre-implementation review of spec.md (and its alignment with plan.md/data-model.md/constitution.md) — validating that requirements are complete, clear, consistent, and testable before running `/speckit-tasks`. Author self-check, standard depth.
**Created**: 2026-08-03
**Feature**: [spec.md](../spec.md)

**Note**: This checklist tests the requirements themselves, not any implementation. Items are questions about what is/isn't written, not verification steps.

## Requirement Completeness

- [ ] CHK001 Is a maximum retention period (or an explicit "retain indefinitely") stated for `audit_logs` and `payment_events`? [Gap, Non-Functional]
- [ ] CHK002 Does the spec state whether email is a mandatory field for every Attendee record, given FR-023 relies on it as the sole identity key? [Gap, Spec §FR-023]
- [x] CHK003 Does the spec define whether a refunded or cancelled order's tickets return their quantity to the ticket type's available inventory for resale? [Gap, Spec §FR-004, §FR-013] — Resolved: Assumptions now state voided tickets do not auto-return quantity; re-crediting is deferred to a future feature.
- [ ] CHK004 Are requirements defined for what happens to existing orders/tickets when an event's start or end date changes after ticket sales have begun? [Gap, Edge Case]
- [ ] CHK005 Is the transition of an Event's status to `completed` specified as automatic (date-driven) or a manual staff action? [Gap, Spec §FR-001]

## Requirement Clarity

- [ ] CHK006 Is the concurrency level (e.g., number of simultaneous purchase attempts) that SC-001's no-oversell guarantee must hold under quantified? [Clarity/Measurability, Spec §SC-001]
- [ ] CHK007 Are SC-004's and SC-006's latency targets tied to a stated data volume or concurrent-load assumption, so "under 1 second" / "under 2 seconds" is testable against a known baseline? [Clarity/Measurability, Spec §SC-004, §SC-006]
- [ ] CHK008 Is "active" (as in "active orders or tickets still reference it", FR-018) precisely defined — e.g., does a soft-deleted order still count as blocking a hard delete? [Ambiguity, Spec §FR-017, §FR-018]
- [ ] CHK009 Is "payment-processor reference" (FR-019) defined precisely enough to confirm an order maps to exactly one such reference, not potentially several? [Clarity, Spec §FR-019]

## Requirement Consistency

- [x] CHK010 Does FR-013's "cancelled after issuance" scenario conflict with FR-007's own definition of `cancelled` as "abandoned/voided before payment" — can a cancelled order ever have issued tickets? [Conflict, Spec §FR-007 vs §FR-013] — Resolved: FR-013 narrowed to `refunded` only; `cancelled` no longer triggers voiding.
- [x] CHK011 Does FR-013 (automatic ticket voiding triggered by an order status change) conflict with the Assumptions section's statement that this feature excludes application workflow/business logic? [Conflict, Spec §FR-013 vs §Assumptions] — Resolved: new Assumption distinguishes the structural state (in scope) from the triggering workflow (out of scope).
- [ ] CHK012 Is the constitution's reference to a single unified "users" table for soft-delete support reconciled with the spec's split into distinct Attendee and Staff entities? [Consistency, Spec vs constitution.md Principle IV]
- [ ] CHK013 Are the Order status set (FR-007) and the Ticket status set (implied by FR-011–FR-013) kept clearly distinct wherever both are discussed, avoiding a reader inferring one set of values from the other? [Consistency, Spec §FR-007, §FR-011-013]

## Acceptance Criteria Quality

- [ ] CHK014 Can SC-002's claim of a "complete, unbroken, unmodifiable history" for 100% of orders be objectively verified by a non-technical reviewer, or does it require implementation access? [Measurability, Spec §SC-002]
- [ ] CHK015 Does SC-005 ("zero errors and zero loss") define what counts as an "error" for measurement purposes? [Clarity, Spec §SC-005]
- [ ] CHK016 Is there a measurable acceptance criterion tied specifically to FR-021's multi-day event date-matching behavior, or do the success criteria only cover single-day lookups? [Gap, Spec §SC-006 vs §FR-021]

## Scenario Coverage

- [ ] CHK017 Is the "browse/find an event" scenario explicitly declared out of scope for this spec, or left ambiguous? [Gap, Scope Boundary]
- [ ] CHK018 Are exception-flow requirements defined for a payment notification whose event type doesn't match any known/expected value? [Gap, Exception Flow]
- [ ] CHK019 Are recovery-flow requirements defined for correcting an erroneous check-in (e.g., staff checked in the wrong ticket)? [Gap, Recovery Flow]

## Edge Case Coverage

- [x] CHK020 Does the spec explicitly reject non-consecutive two-day events (e.g., Friday + Sunday), or is that merely unaddressed rather than ruled out? [Ambiguity, Spec §FR-002, §Assumptions] — Already resolved: Assumptions explicitly state "no gap days."
- [x] CHK021 Is there a requirement covering two attendees registering with the same email at the same instant — the identity-uniqueness race condition, distinct from the ticket-inventory race in SC-001? [Gap, Edge Case] — Resolved: added as an Edge Case bullet; covered by task T068.
- [ ] CHK022 Is it specified whether an order can exist with zero order items, or is at least one order item always guaranteed? [Gap, Edge Case, Spec §FR-008]

## Non-Functional Requirements

- [ ] CHK023 Are data-volume/scale assumptions (expected attendee, order, and ticket counts) stated in the spec itself, rather than only in the downstream implementation plan? [Gap, Non-Functional, Spec vs plan.md]
- [ ] CHK024 Are requirements defined for who may query or export attendee PII outside of the erasure/soft-delete flow, or is that explicitly out of scope? [Gap, Non-Functional]

## Dependencies & Assumptions

- [x] CHK025 Is the assumption "an attendee may have multiple orders over time" cross-checked against FR-023's one-record-per-email rule to confirm the two don't conflict? [Consistency, Spec §Assumptions vs §FR-023] — Verified consistent: multiple orders all reference the same canonical attendee_id; no conflict.
- [ ] CHK026 Is the dependency on a not-yet-specified future attendee-authentication feature documented with enough detail to know whether the Attendee entity here must anticipate any authentication-related fields? [Dependency, Spec §Assumptions]

## Ambiguities & Conflicts

- [x] CHK027 Is retaining IP address/user-agent data on an order after its Attendee is removed for a privacy request explicitly reconciled with GDPR/POPIA data-minimization expectations? [Ambiguity, Spec §FR-005 vs §FR-017] — Resolved: new Assumption states this is an intentional fraud-prevention exemption from erasure.
- [x] CHK028 Does the spec clarify whether a ticket can reach `voided` from `checked_in` as well as from `unused` (FR-012/FR-013 imply both), and if so, confirm that dual-path transition is intentional? [Ambiguity, Spec §FR-012, §FR-013] — Confirmed intentional: data-model.md's state-transition note and task T058 both explicitly cover both paths.

## Notes

- Focus: broad general pass across data integrity, security/compliance, and schema/entity coverage (author's choice over narrower single-topic options).
- Depth: standard — thorough pre-implementation review, not an exhaustive formal gate.
- Audience/timing: author self-check, before running `/speckit-tasks`.
- Check items off as resolved; where an item surfaces a real gap/conflict, resolve it by editing `spec.md` (via `/speckit-clarify` or a direct edit) before generating tasks — this checklist does not modify the spec itself.
- 2026-08-03 re-validation (pre-`/speckit-implement` gate): 8/28 resolved via `/speckit-analyze` remediation (CHK003, CHK010, CHK011, CHK020, CHK021, CHK025, CHK027, CHK028). The remaining 20 are lower-impact scope/documentation questions (retention periods, scale stated in spec text, exception/recovery-flow narratives, precise-term definitions) appropriate to defer past this schema-only feature rather than blockers to implementation.
