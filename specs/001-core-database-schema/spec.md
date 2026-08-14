# Feature Specification: Core Ticketing Data Model

**Feature Branch**: `main` (no dedicated feature branch created — no git hook configured for automatic branch creation; corrected 2026-08-04, this line originally read "N/A (no git repository initialized)" from before the repository existed)

**Created**: 2026-08-03

**Status**: Draft

## Clarifications

### Session 2026-08-03

- Q: Is an Attendee identified by a canonical unique identity (e.g., one record per email, reused across orders), or can multiple Attendee records exist for the same person? → A: One record per email (unique) — email is enforced unique, and repeat purchases by the same email reuse the same Attendee record.
- Q: What is the canonical set of Order lifecycle statuses the schema must support? → A: pending, paid, failed, refunded, cancelled.
- Q: If an order is refunded or cancelled after tickets were already issued, should those tickets automatically become invalid for check-in? → A: Yes, auto-void on refund — tickets are marked invalid/voided and check-in must reject them, even if previously unused. *(Amended below: `/speckit-analyze` found "cancelled after issuance" unreachable given FR-007's cancelled-is-pre-payment definition, so FR-013 was narrowed to `refunded` only.)*
- Q: What is the canonical set of Event statuses the schema must support? → A: draft, published, sold_out, completed, cancelled.

### Session 2026-08-04

- Q: The in-progress M-Pesa/offline payment correction (2026-08-04, see data-model.md) had already given `orders` three payment-reference columns — `payment_reference`, `mpesa_checkout_request_id`, `proof_of_payment_path` — but today's update request names only one, `mpesa_transaction_reference`. How should these reconcile? → A: Rename only — `payment_reference` is renamed to `mpesa_transaction_reference`; `mpesa_checkout_request_id` and `proof_of_payment_path` are unchanged, since they serve distinct purposes (STK-push correlation and offline proof-of-payment storage respectively) that today's update never asked to remove.
- Q: `ticket_types` gets a `version` column so its shared `available_quantity` is guaranteed never to oversell under concurrency (FR-004/SC-001), but the request says nothing about concurrency control for a pricing phase's `quantity_sold` vs its `quantity_cap`. Should the per-phase cap get that same hard, zero-tolerance guarantee, or is a softer best-effort cap acceptable? → A: Hard guarantee — a phase's cap gets the same zero-tolerance concurrency guarantee as the shared pool; the data model needs its own concurrency-control mechanism for it, analogous to `ticket_types.version`.
- Q: The request doesn't say whether `tier_order` must be unique per ticket type; without that, FR-025's "resolve the lowest tier_order" rule has no defined tie-breaker if two phases collide. Should the schema enforce uniqueness? → A: DB-enforced unique — a uniqueness constraint on (ticket type, sequence position) makes a duplicate impossible to create, so the tie case can never occur.
- Q: SC-004/SC-006 give concrete numeric performance targets, but SC-008 (pricing-phase resolution speed) only said "speeds suitable for interactive checkout." Should it get an explicit number too? → A: Yes, under 1 second — matching SC-004's ticket check-in target, since tier resolution is a similarly simple, single-row lookup.

**Input**: User description: "Design and implement the core MySQL database schema for the ticketing system: attendees, events, ticket_types, orders, order_items, tickets, staff, audit_logs, and payment_events tables. Use CHAR(36) UUID primary keys for all tables except audit_logs, which uses BIGINT auto-increment and is append-only (no UPDATE/DELETE permitted at the application layer). Ticket types have an available_quantity denormalized column plus a version column for optimistic locking to prevent overselling. Orders store a transaction_hash for payment idempotency and track ip_address/user_agent for fraud detection. Tickets store a unique qr_code, check-in status, and audit fields (checked_in_at, checked_in_by). All tables use InnoDB engine, utf8mb4 charset, utf8mb4_unicode_ci collation. Foreign keys use ON DELETE RESTRICT except where explicitly noted (order_items cascades from orders, payment_events sets order_id null). Soft deletes (deleted_at) on attendees, events, ticket_types, orders, staff for GDPR/POPIA compliance. Create corresponding Eloquent models with defined relationships (Event hasMany TicketType, Order hasMany OrderItem, OrderItem hasMany Ticket, etc.), and add indexes for the most common query patterns: event status+date, ticket availability by event, order lookup by attendee/status/stripe_payment_intent_id, ticket lookup by qr_code. No business logic in this spec — schema and models only."

**Update input (2026-08-04)**: "Design and implement the core MySQL database schema for the ticketing system: attendees, events, ticket_types, ticket_price_tiers, orders, order_items, tickets, staff, audit_logs, and payment_events tables. Use CHAR(36) UUID primary keys for all tables except audit_logs, which uses BIGINT auto-increment and is append-only (no UPDATE/DELETE permitted at the application layer). ticket_types represent a ticket category (e.g. "General Admission", "VIP") with ONE shared inventory pool: available_quantity (denormalized) plus a version column for optimistic locking to prevent overselling — pricing phases do NOT get separate inventory pools. Add a ticket_price_tiers table (id, ticket_type_id FK, name e.g. "Early Bird"/"Phase 2"/"Last Chance", price in cents, tier_order integer for sequencing, starts_at, nullable ends_at, nullable quantity_cap for capping a tier at N tickets independent of the shared pool, quantity_sold denormalized counter, timestamps) so a single ticket_type can be sold at different, automatically-progressing prices over time without admins manually flipping status or creating duplicate ticket types per phase. A ticket_type must have at least one tier; the "current" tier for purchase purposes is resolved as the lowest tier_order row where starts_at <= now, (ends_at is null or ends_at > now), and (quantity_cap is null or quantity_sold < quantity_cap) — falling through to the next tier by tier_order if the active one is time-expired or cap-exhausted. Orders store a transaction_hash for payment idempotency, a payment_method column constrained to "mpesa" or "whatsapp_offline" (no other gateways — this system does not integrate Stripe or any card processor), a nullable mpesa_transaction_reference (unique), and track ip_address/user_agent for fraud detection. Order_items snapshot both ticket_type_id and the resolved ticket_price_tier_id plus unit_price at time of purchase, so historical orders are unaffected by later tier edits and reporting can break down sales by pricing phase. Tickets store a unique qr_code, check-in status, and audit fields (checked_in_at, checked_in_by). All tables use InnoDB engine, utf8mb4 charset, utf8mb4_unicode_ci collation. Foreign keys use ON DELETE RESTRICT except where explicitly noted (order_items cascades from orders, payment_events sets order_id null, ticket_price_tiers cascades from ticket_types). Soft deletes (deleted_at) on attendees, events, ticket_types, orders, staff for GDPR/POPIA compliance. Create corresponding Eloquent models with defined relationships (Event hasMany TicketType, TicketType hasMany TicketPriceTier, TicketType hasMany OrderItem, Order hasMany OrderItem, OrderItem belongsTo TicketPriceTier, OrderItem hasMany Ticket, etc.), and add indexes for the most common query patterns: event status+date, ticket type availability by event, active tier lookup by ticket_type_id+starts_at+ends_at, order lookup by attendee/status/mpesa_transaction_reference, ticket lookup by qr_code. No business logic in this spec beyond the tier-resolution rule stated above — schema and models only."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Reliable Ticket Inventory, No Overselling (Priority: P1)

The platform must never sell more tickets of a given type than actually exist, even when many people try to buy the last remaining tickets at the same moment.

**Why this priority**: Overselling a ticket type is the single most damaging and hardest-to-reverse failure for a ticketed event — it means turning away or refunding a paying attendee. Every other capability depends on inventory being trustworthy.

**Independent Test**: Simulate two purchase attempts submitted at the same instant for the last available ticket of a type; confirm exactly one succeeds and the other is cleanly rejected, with available inventory ending at zero (never negative).

**Acceptance Scenarios**:

1. **Given** a ticket type has exactly 1 ticket remaining, **When** two purchase attempts are submitted concurrently, **Then** only one attempt succeeds and the other is rejected with available inventory remaining at 0.
2. **Given** a ticket type has 0 tickets remaining, **When** a purchase is attempted, **Then** the attempt is rejected and no ticket is issued.

---

### User Story 2 - Ticket Types Sell at Automatically-Progressing Prices Over Time (Priority: P1)

A single ticket type (e.g., "General Admission") can be sold at different prices over time — an "Early Bird" price, then a "Phase 2" price, then a "Last Chance" price — with the system automatically determining which price applies at the moment of purchase, based on timing and an optional per-phase cap, without staff manually toggling anything or creating duplicate ticket types per phase.

**Why this priority**: Charging the wrong price at checkout is a direct, immediate financial-correctness failure — as consequential as overselling — and every downstream order/ticket/reporting capability depends on the right price having been resolved and permanently recorded at the moment of purchase.

**Independent Test**: Configure a ticket type with two pricing phases — one whose window has already ended, one that is currently active — and confirm the phase resolved for a purchase right now is the active one, not the expired one; then confirm resolution correctly skips a phase whose sales cap has been reached.

**Acceptance Scenarios**:

1. **Given** a ticket type has one pricing phase whose start time has passed and whose end time (if any) has not, **When** the current purchasable price is resolved, **Then** that phase's price is returned.
2. **Given** a ticket type has an earlier phase whose end time has passed and a later phase whose start time has passed, **When** the current purchasable price is resolved, **Then** the later, still-active phase's price is returned, never the expired one.
3. **Given** a ticket type has a phase with a sales cap that has been reached, **When** the current purchasable price is resolved, **Then** resolution falls through, in sequence order, to the next phase that is neither expired nor capped out.
4. **Given** a ticket type has no pricing phase defined yet, **When** it is configured for sale, **Then** the system requires at least one pricing phase to exist.
5. **Given** an order has already been placed under a specific pricing phase, **When** that phase's price is later edited or a new phase is added, **Then** the historical order's recorded price and phase reference remain unchanged.

---

### User Story 3 - Auditable Order & Payment History (Priority: P1)

Every order and every payment notification related to it must be permanently recorded in a way that can be reconstructed later for financial reconciliation or dispute resolution, and that record can never be silently altered or erased.

**Why this priority**: This system moves real money for a public event. Without a tamper-evident history, a disputed charge, a refund question, or an accounting discrepancy cannot be resolved with confidence.

**Independent Test**: Create an order, record several payment notifications and a status change against it, then confirm the full sequence of events can be retrieved in order and that none of the historical entries can be modified or removed.

**Acceptance Scenarios**:

1. **Given** an order has been created, **When** a payment notification is received for it, **Then** the notification is stored as a new permanent entry rather than overwriting any prior entry.
2. **Given** an administrative action affects an order or a ticket, **When** the action completes, **Then** a permanent record of who performed the action and when is retained and cannot subsequently be edited or deleted.

---

### User Story 4 - Fraud Signal Capture on Orders (Priority: P2)

Each order retains the contextual information (originating network address, device/browser signature, and a payment idempotency reference) needed to detect duplicate charges and flag suspicious purchasing patterns.

**Why this priority**: Fraud and duplicate-charge detection depend entirely on this contextual data being captured at the moment of purchase — it cannot be reconstructed after the fact.

**Independent Test**: Submit the same payment confirmation twice (e.g., after a network retry); confirm only one order/charge is ever recorded, and that the retained order shows the originating network and device information.

**Acceptance Scenarios**:

1. **Given** a payment confirmation for a specific transaction has already been recorded, **When** the same confirmation is received again, **Then** no second order is created for that transaction.
2. **Given** an order is created, **When** it is later reviewed, **Then** the originating network address and device/browser information captured at purchase time are available.

---

### User Story 5 - GDPR/POPIA-Compliant Data Removal (Priority: P2)

An attendee, event, ticket type, order, or staff record can be removed from active use in response to a privacy request or discontinued offering, without destroying or breaking the historical orders, tickets, or audit records that reference it.

**Why this priority**: The system must satisfy privacy-erasure obligations without corrupting the financial and audit history the business is separately obligated to retain — these two requirements must coexist.

**Independent Test**: Mark an attendee who has completed orders as removed; confirm their past orders, tickets, and audit entries remain fully intact and retrievable, while the attendee no longer appears in active/listing views.

**Acceptance Scenarios**:

1. **Given** an attendee has one or more past orders, **When** the attendee record is removed, **Then** their past orders and tickets remain queryable and unaffected.
2. **Given** an event, ticket type, or staff record is removed, **When** existing orders or tickets still reference it, **Then** the removal succeeds without breaking those references and the record is excluded from active listings.

---

### User Story 6 - Fast, Unambiguous Ticket Check-In (Priority: P3)

Event staff can scan a ticket's unique code at the door and immediately get a clear valid, invalid, or already-used result, with a durable record of which staff member checked the ticket in and when.

**Why this priority**: Entry-point check-in is highly visible and time-sensitive on event day, but depends on the inventory and order integrity established by higher-priority stories, so it is sequenced after them.

**Independent Test**: Scan a valid, unused ticket's code and confirm it is marked checked-in with the staff member and timestamp recorded; scan the same code again and confirm it is now reported as already used.

**Acceptance Scenarios**:

1. **Given** a ticket has not yet been checked in, **When** its code is scanned by staff, **Then** it is marked checked-in with the staff member and timestamp recorded.
2. **Given** a ticket has already been checked in, **When** its code is scanned again, **Then** the system reports it as already used rather than checking it in a second time.
3. **Given** a ticket's order has been refunded, **When** the ticket's code is scanned, **Then** check-in is rejected and the ticket is reported as invalid, even if it was never previously used.

---

### Edge Cases

- An attendee requests erasure after completing orders and using (checking in) tickets.
- A ticket type is discontinued while tickets already issued from it still exist.
- Removal of an event, ticket type, attendee, or staff record is attempted while active orders or tickets still reference it.
- A payment notification arrives referencing an order that has since been removed.
- A staff record associated with historical check-ins or audit entries is later removed.
- A two-day event is queried or filtered by date on either of its two days, or checked in on its second day (must correctly match/allow check-in on both days, not just the start date).
- Two checkout attempts using the same email address are submitted at the same instant (identity-uniqueness race, distinct from the ticket-inventory race in User Story 1).
- A ticket type's pricing phases are all either not yet started, already expired, or capped out at the same moment, so no phase currently qualifies for purchase (distinct from the shared inventory pool being at zero — a phase can be unavailable while inventory remains, or vice versa).
- Two purchases against the same capped pricing phase are submitted at the same instant, right at the phase's remaining cap (a phase-level race distinct from User Story 1's ticket-type-level inventory race).

*(Four edge cases that merely restated an already-covered Acceptance Scenario — concurrent last-ticket purchase, duplicate payment retry, repeat ticket scan, refund-after-issuance voiding — were pruned here by `/speckit-analyze`; each remains fully covered under its User Story's Acceptance Scenarios above. A fifth — two pricing phases for the same ticket type sharing a sequence position — was resolved by the 2026-08-04 Clarifications into FR-030, which makes the collision structurally impossible rather than an edge case to handle.)*

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST represent an event with a lifecycle status of exactly one of: draft (not yet public), published (on sale/visible), sold_out (visible but no inventory left), completed (event date passed), or cancelled — plus a schedule, to support listing and filtering upcoming or on-sale events.
- **FR-002**: System MUST represent an event's schedule as a start date and an end date, supporting either a single-day event (start and end date the same) or a two-day event (end date one calendar day after the start date); durations beyond two days are out of scope for this feature.
- **FR-003**: System MUST allow an event to define one or more ticket types, each with its own shared, non-overselling available quantity.
- **FR-004**: System MUST guarantee that a ticket type's available quantity never goes negative and is never reduced below zero, even under concurrent purchase attempts.
- **FR-005**: System MUST record one order per checkout attempt, linked to the purchasing attendee, and capture the originating network address and device/user-agent information at the time of purchase.
- **FR-006**: System MUST guarantee that a given payment transaction is never applied to more than one order, even if its confirmation is received more than once.
- **FR-007**: System MUST track each order's lifecycle status as exactly one of: pending (awaiting payment), paid (payment captured), failed (payment declined/errored), refunded (paid then reversed), or cancelled (abandoned/voided before payment).
- **FR-008**: System MUST itemize each order into one or more line items, each specifying a ticket type, the specific pricing phase resolved at purchase time, and quantity purchased.
- **FR-009**: System MUST issue one individual ticket per unit purchased in an order line item.
- **FR-010**: System MUST assign each issued ticket a unique scannable code that never collides with any other ticket, past or present.
- **FR-011**: System MUST record whether each ticket has been checked in, and if so, the timestamp and the staff member who performed the check-in.
- **FR-012**: System MUST detect and reject repeat check-in attempts against a ticket that has already been checked in.
- **FR-013**: System MUST automatically mark a ticket as voided/invalid when the order it belongs to transitions to refunded, and MUST reject check-in attempts against a voided ticket regardless of its prior check-in state.
- **FR-014**: System MUST record every payment-related notification received for an order (e.g., authorization, capture, failure, refund) as a permanent, ordered entry, independent of the order's current status.
- **FR-015**: System MUST retain an order's payment notification history and audit trail permanently, even if the order, attendee, or staff record it references is later removed from active use.
- **FR-016**: System MUST maintain a permanent, append-only record of significant administrative and system actions that cannot be edited or removed after being written, by any party including administrators.
- **FR-017**: System MUST allow attendee, event, ticket type, order, and staff records to be removed from active use (e.g., for a privacy request or discontinued offering) without deleting or breaking historical orders, tickets, or audit records that reference them.
- **FR-018**: System MUST prevent an event, ticket type, or attendee from being permanently and irrecoverably deleted while active orders or tickets still reference it.
- **FR-019**: System MUST support looking up orders by purchasing attendee, by current status, or by payment-processor reference, at speeds suitable for interactive use by support and finance staff.
- **FR-020**: System MUST support looking up an individual ticket by its scannable code at speeds suitable for real-time check-in at an event entrance.
- **FR-021**: System MUST support listing and filtering events by status and date — including matching an event on every calendar day it spans for two-day events — and determining real-time ticket availability for a given event.
- **FR-022**: System MUST distinguish staff records from attendee records, and every check-in or other auditable staff action MUST be attributable to a specific staff member.
- **FR-023**: System MUST enforce a single canonical attendee record per email address; a purchase using an email address that already has an attendee record MUST reuse that existing record rather than creating a duplicate.
- **FR-024**: System MUST allow a ticket type to define one or more sequentially-ordered pricing phases, each with its own price, and MUST require every ticket type to have at least one pricing phase.
- **FR-025**: System MUST resolve a ticket type's current purchasable pricing phase as the earliest-sequenced phase whose start time has passed, whose end time (if any) has not yet passed, and whose sales cap (if any) has not yet been reached — automatically advancing, in sequence order, to the next qualifying phase when the current one expires or is capped out.
- **FR-026**: System MUST track how many tickets have sold under each individual pricing phase, independently of a ticket type's shared inventory pool, to support an optional per-phase sales cap.
- **FR-027**: System MUST record, on each order line item, exactly which pricing phase (in addition to which ticket type) was resolved at the moment of purchase, along with the price charged at that time — so historical orders remain accurate even if a phase's price is later edited or new phases are added, and so sales can be broken down by pricing phase in reporting.
- **FR-028**: System MUST record each order's payment method as exactly one of: mpesa (Vodacom M-Pesa mobile money, confirmed via an asynchronous callback) or whatsapp_offline (a manually-confirmed offline payment, with proof of payment submitted via WhatsApp); no other payment gateway or card processor is supported.
- **FR-029**: System MUST guarantee that a pricing phase's sales cap, when set, is never exceeded, even under concurrent purchase attempts against that phase — the same zero-tolerance guarantee FR-004 requires for a ticket type's shared available quantity.
- **FR-030**: System MUST prevent two pricing phases belonging to the same ticket type from ever sharing the same sequence position, so FR-025's resolution rule always has an unambiguous lowest-sequenced qualifying phase.

### Key Entities

- **Attendee**: A person who purchases tickets, uniquely identified by email (one canonical record reused across repeat purchases); holds contact/identity information; can be removed from active use for privacy requests while past orders remain intact; has many orders.
- **Event**: A ticketed event with a name, a start date and end date spanning one or two consecutive days, a lifecycle status (draft, published, sold_out, completed, or cancelled), and venue details; can be removed from active use; has many ticket types.
- **Ticket Type**: A purchasable ticket category for an event (e.g., "VIP", "General Admission") with ONE shared, non-overselling available quantity across all of its pricing phases; belongs to an event; has one or more pricing phases (at least one required).
- **Ticket Price Tier**: A sequentially-ordered pricing phase for a ticket type (e.g., "Early Bird", "Phase 2", "Last Chance") with a name, a price, a sequence position, a start time, an optional end time, an optional non-overselling sales cap independent of the shared inventory pool, and a running count of tickets sold under it; belongs to exactly one ticket type. The currently purchasable phase is resolved by sequence position, timing, and cap — it does NOT have its own separate inventory pool, but its own sales cap is held to the same zero-tolerance concurrency guarantee as the shared pool (FR-029).
- **Order**: A single checkout/purchase transaction by an attendee; captures a payment idempotency reference (`transaction_hash`), the payment method used (M-Pesa or WhatsApp-confirmed offline — no other gateway), a payment reference once confirmed, originating network/device information, and a lifecycle status (pending, paid, failed, refunded, or cancelled); has many order items.
- **Order Item**: A line item within an order representing a quantity of one ticket type, purchased under one specific, resolved pricing phase, with the price charged at that moment recorded independently of the phase's current price; belongs to an order; results in issued tickets.
- **Ticket**: An individual, uniquely-coded admission credential issued from an order item; tracks its check-in status (unused, checked-in, or voided) and which staff member checked it in and when; automatically voided if its order is later refunded.
- **Staff**: An internal user, distinct from attendees, who can perform administrative or check-in actions; can be removed from active use while historical actions attributed to them remain intact.
- **Audit Log**: A permanent, append-only record of significant administrative and system actions; never modified or removed once written.
- **Payment Event**: A permanent record of a single payment-processor notification related to an order; retained even if the referenced order is later removed.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Under concurrent purchase attempts for the same ticket type, zero overselling incidents occur — issued tickets never exceed configured availability.
- **SC-002**: 100% of orders can be traced through a complete, unbroken, unmodifiable history of every payment notification and status change from creation to final state.
- **SC-003**: Duplicate payment confirmations for the same transaction produce zero duplicate orders or duplicate charges.
- **SC-004**: Staff can determine a ticket's check-in validity at the door in under 1 second per scan.
- **SC-005**: A privacy erasure request for an attendee completes with zero errors and zero loss of historical order or financial records.
- **SC-006**: Support and finance staff can locate any order by attendee, status, or payment reference, and any ticket by its scannable code, in under 2 seconds.
- **SC-007**: Under concurrent purchase attempts against a capped pricing phase, that phase's sales never exceed its cap, and resolution always falls through to the correct next phase with zero incorrectly-priced charges.
- **SC-008**: For a ticket type with any number of historical, current, and future pricing phases, exactly one current purchasable phase (or none, if none currently qualifies) is resolved correctly every time, in under 1 second.

## Assumptions

- An attendee may have multiple orders over time; attendee login/authentication is handled by a separate feature and is out of scope here.
- "Staff" is a distinct actor type from "Attendee"; role-based permissions for staff are out of scope for this feature (data model only).
- The workflow/approval process for changing a ticket type's available quantity is out of scope; this feature only ensures quantity can be represented and non-overselling enforced.
- Refund workflow/business logic is out of scope; this feature only ensures order status and payment event history can represent a refunded state.
- Per the request, "no business logic" scopes this feature to the data model and its structural guarantees (constraints, relationships, uniqueness, append-only history) rather than application workflow, pricing rules, or notification logic — except for the pricing-phase resolution rule itself (FR-025), which the update request explicitly asked to be captured as a stated rule rather than left entirely to a future feature.
- A single currency is assumed for both ticket type and pricing-phase amounts; multi-currency support is out of scope for this feature.
- A two-day event's end date is assumed to be the calendar day immediately following its start date (no gap days); events longer than two days are out of scope for this feature.
- FR-013's automatic ticket-voiding rule describes a structural state this schema must represent (a ticket can be `voided`, and a voided ticket must reject check-in); the workflow/trigger that invokes that transition (e.g., the refund action itself) is out of scope, consistent with the "no business logic" framing above.
- Voided tickets do not automatically return their quantity to the ticket type's `available_quantity` for resale in this feature; re-crediting inventory is a business/workflow decision left to a future feature, not a gap in this schema.
- `orders.ip_address`/`user_agent` are retained indefinitely for fraud prevention even after the referencing Attendee is soft-deleted for a privacy request; this is an intentional exemption from erasure (these are order/transaction fraud-signal fields, not attendee identity fields), not an oversight.
- The application-level workflow that actually increments a pricing phase's sold count (and the ticket type's shared `available_quantity`) at the moment of purchase is out of scope for this schema-only feature, consistent with the existing "no business logic" framing — this feature only ensures both counters can be represented and structurally support the resolution rule in FR-025.
- Per the 2026-08-04 Clarifications, `quantity_sold`'s concurrency guarantee (FR-029) is held to the same standard as `available_quantity`'s (FR-004); the data model is expected to give `ticket_price_tiers` its own concurrency-control mechanism (e.g., an optimistic-locking version column, mirroring `ticket_types.version`) rather than a plain, race-prone increment — the exact mechanism is a planning-level decision, not mandated here.
- When no pricing phase currently qualifies for a ticket type (all not-yet-started, expired, or capped out), the schema does not itself prevent that state from existing; whether the ticket type is then treated as unavailable for purchase is an application/workflow decision left to a future feature, not a schema-level constraint.
- `mpesa_transaction_reference` (2026-08-04 Clarifications) replaces the column previously documented as `payment_reference` in this feature's own planning artifacts; `mpesa_checkout_request_id` and `proof_of_payment_path` are unchanged and retained for their distinct purposes (STK-push correlation before confirmation, and offline proof-of-payment file storage, respectively).
- Pricing-phase sequence positions are staff-assigned and DB-enforced unique per ticket type (FR-030, 2026-08-04 Clarifications); this schema-only feature stores the integer and enforces that uniqueness but does not itself define a reordering workflow (e.g., renumbering existing phases when one is inserted between two others).
