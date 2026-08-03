# Feature Specification: Core Ticketing Data Model

**Feature Branch**: `N/A (no git repository initialized)`

**Created**: 2026-08-03

**Status**: Draft

## Clarifications

### Session 2026-08-03

- Q: Is an Attendee identified by a canonical unique identity (e.g., one record per email, reused across orders), or can multiple Attendee records exist for the same person? → A: One record per email (unique) — email is enforced unique, and repeat purchases by the same email reuse the same Attendee record.
- Q: What is the canonical set of Order lifecycle statuses the schema must support? → A: pending, paid, failed, refunded, cancelled.
- Q: If an order is refunded or cancelled after tickets were already issued, should those tickets automatically become invalid for check-in? → A: Yes, auto-void on refund — tickets are marked invalid/voided and check-in must reject them, even if previously unused. *(Amended below: `/speckit-analyze` found "cancelled after issuance" unreachable given FR-007's cancelled-is-pre-payment definition, so FR-013 was narrowed to `refunded` only.)*
- Q: What is the canonical set of Event statuses the schema must support? → A: draft, published, sold_out, completed, cancelled.

**Input**: User description: "Design and implement the core MySQL database schema for the ticketing system: attendees, events, ticket_types, orders, order_items, tickets, staff, audit_logs, and payment_events tables. Use CHAR(36) UUID primary keys for all tables except audit_logs, which uses BIGINT auto-increment and is append-only (no UPDATE/DELETE permitted at the application layer). Ticket types have an available_quantity denormalized column plus a version column for optimistic locking to prevent overselling. Orders store a transaction_hash for payment idempotency and track ip_address/user_agent for fraud detection. Tickets store a unique qr_code, check-in status, and audit fields (checked_in_at, checked_in_by). All tables use InnoDB engine, utf8mb4 charset, utf8mb4_unicode_ci collation. Foreign keys use ON DELETE RESTRICT except where explicitly noted (order_items cascades from orders, payment_events sets order_id null). Soft deletes (deleted_at) on attendees, events, ticket_types, orders, staff for GDPR/POPIA compliance. Create corresponding Eloquent models with defined relationships (Event hasMany TicketType, Order hasMany OrderItem, OrderItem hasMany Ticket, etc.), and add indexes for the most common query patterns: event status+date, ticket availability by event, order lookup by attendee/status/stripe_payment_intent_id, ticket lookup by qr_code. No business logic in this spec — schema and models only."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Reliable Ticket Inventory, No Overselling (Priority: P1)

The platform must never sell more tickets of a given type than actually exist, even when many people try to buy the last remaining tickets at the same moment.

**Why this priority**: Overselling a ticket type is the single most damaging and hardest-to-reverse failure for a ticketed event — it means turning away or refunding a paying attendee. Every other capability depends on inventory being trustworthy.

**Independent Test**: Simulate two purchase attempts submitted at the same instant for the last available ticket of a type; confirm exactly one succeeds and the other is cleanly rejected, with available inventory ending at zero (never negative).

**Acceptance Scenarios**:

1. **Given** a ticket type has exactly 1 ticket remaining, **When** two purchase attempts are submitted concurrently, **Then** only one attempt succeeds and the other is rejected with available inventory remaining at 0.
2. **Given** a ticket type has 0 tickets remaining, **When** a purchase is attempted, **Then** the attempt is rejected and no ticket is issued.

---

### User Story 2 - Auditable Order & Payment History (Priority: P1)

Every order and every payment notification related to it must be permanently recorded in a way that can be reconstructed later for financial reconciliation or dispute resolution, and that record can never be silently altered or erased.

**Why this priority**: This system moves real money for a public event. Without a tamper-evident history, a disputed charge, a refund question, or an accounting discrepancy cannot be resolved with confidence.

**Independent Test**: Create an order, record several payment notifications and a status change against it, then confirm the full sequence of events can be retrieved in order and that none of the historical entries can be modified or removed.

**Acceptance Scenarios**:

1. **Given** an order has been created, **When** a payment notification is received for it, **Then** the notification is stored as a new permanent entry rather than overwriting any prior entry.
2. **Given** an administrative action affects an order or a ticket, **When** the action completes, **Then** a permanent record of who performed the action and when is retained and cannot subsequently be edited or deleted.

---

### User Story 3 - Fraud Signal Capture on Orders (Priority: P2)

Each order retains the contextual information (originating network address, device/browser signature, and a payment idempotency reference) needed to detect duplicate charges and flag suspicious purchasing patterns.

**Why this priority**: Fraud and duplicate-charge detection depend entirely on this contextual data being captured at the moment of purchase — it cannot be reconstructed after the fact.

**Independent Test**: Submit the same payment confirmation twice (e.g., after a network retry); confirm only one order/charge is ever recorded, and that the retained order shows the originating network and device information.

**Acceptance Scenarios**:

1. **Given** a payment confirmation for a specific transaction has already been recorded, **When** the same confirmation is received again, **Then** no second order is created for that transaction.
2. **Given** an order is created, **When** it is later reviewed, **Then** the originating network address and device/browser information captured at purchase time are available.

---

### User Story 4 - GDPR/POPIA-Compliant Data Removal (Priority: P2)

An attendee, event, ticket type, order, or staff record can be removed from active use in response to a privacy request or discontinued offering, without destroying or breaking the historical orders, tickets, or audit records that reference it.

**Why this priority**: The system must satisfy privacy-erasure obligations without corrupting the financial and audit history the business is separately obligated to retain — these two requirements must coexist.

**Independent Test**: Mark an attendee who has completed orders as removed; confirm their past orders, tickets, and audit entries remain fully intact and retrievable, while the attendee no longer appears in active/listing views.

**Acceptance Scenarios**:

1. **Given** an attendee has one or more past orders, **When** the attendee record is removed, **Then** their past orders and tickets remain queryable and unaffected.
2. **Given** an event, ticket type, or staff record is removed, **When** existing orders or tickets still reference it, **Then** the removal succeeds without breaking those references and the record is excluded from active listings.

---

### User Story 5 - Fast, Unambiguous Ticket Check-In (Priority: P3)

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

*(Four edge cases that merely restated an already-covered Acceptance Scenario — concurrent last-ticket purchase, duplicate payment retry, repeat ticket scan, refund-after-issuance voiding — were pruned here by `/speckit-analyze`; each remains fully covered under its User Story's Acceptance Scenarios above.)*

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST represent an event with a lifecycle status of exactly one of: draft (not yet public), published (on sale/visible), sold_out (visible but no inventory left), completed (event date passed), or cancelled — plus a schedule, to support listing and filtering upcoming or on-sale events.
- **FR-002**: System MUST represent an event's schedule as a start date and an end date, supporting either a single-day event (start and end date the same) or a two-day event (end date one calendar day after the start date); durations beyond two days are out of scope for this feature.
- **FR-003**: System MUST allow an event to define one or more ticket types, each with its own price and available quantity.
- **FR-004**: System MUST guarantee that a ticket type's available quantity never goes negative and is never reduced below zero, even under concurrent purchase attempts.
- **FR-005**: System MUST record one order per checkout attempt, linked to the purchasing attendee, and capture the originating network address and device/user-agent information at the time of purchase.
- **FR-006**: System MUST guarantee that a given payment transaction is never applied to more than one order, even if its confirmation is received more than once.
- **FR-007**: System MUST track each order's lifecycle status as exactly one of: pending (awaiting payment), paid (payment captured), failed (payment declined/errored), refunded (paid then reversed), or cancelled (abandoned/voided before payment).
- **FR-008**: System MUST itemize each order into one or more line items, each specifying a ticket type and quantity purchased.
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

### Key Entities

- **Attendee**: A person who purchases tickets, uniquely identified by email (one canonical record reused across repeat purchases); holds contact/identity information; can be removed from active use for privacy requests while past orders remain intact; has many orders.
- **Event**: A ticketed event with a name, a start date and end date spanning one or two consecutive days, a lifecycle status (draft, published, sold_out, completed, or cancelled), and venue details; can be removed from active use; has many ticket types.
- **Ticket Type**: A purchasable ticket category for an event (e.g., "VIP", "General Admission") with a price and available quantity that must never oversell; belongs to an event.
- **Order**: A single checkout/purchase transaction by an attendee; captures a payment idempotency reference, originating network/device information, and a lifecycle status (pending, paid, failed, refunded, or cancelled); has many order items.
- **Order Item**: A line item within an order representing a quantity of one ticket type purchased; belongs to an order; results in issued tickets.
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

## Assumptions

- An attendee may have multiple orders over time; attendee login/authentication is handled by a separate feature and is out of scope here.
- "Staff" is a distinct actor type from "Attendee"; role-based permissions for staff are out of scope for this feature (data model only).
- The workflow/approval process for changing a ticket type's available quantity is out of scope; this feature only ensures quantity can be represented and non-overselling enforced.
- Refund workflow/business logic is out of scope; this feature only ensures order status and payment event history can represent a refunded state.
- Per the request, "no business logic" scopes this feature to the data model and its structural guarantees (constraints, relationships, uniqueness, append-only history) rather than application workflow, pricing rules, or notification logic.
- A single currency is assumed; multi-currency support is out of scope for this feature.
- A two-day event's end date is assumed to be the calendar day immediately following its start date (no gap days); events longer than two days are out of scope for this feature.
- FR-013's automatic ticket-voiding rule describes a structural state this schema must represent (a ticket can be `voided`, and a voided ticket must reject check-in); the workflow/trigger that invokes that transition (e.g., the refund action itself) is out of scope, consistent with the "no business logic" framing above.
- Voided tickets do not automatically return their quantity to the ticket type's `available_quantity` for resale in this feature; re-crediting inventory is a business/workflow decision left to a future feature, not a gap in this schema.
- `orders.ip_address`/`user_agent` are retained indefinitely for fraud prevention even after the referencing Attendee is soft-deleted for a privacy request; this is an intentional exemption from erasure (these are order/transaction fraud-signal fields, not attendee identity fields), not an oversight.
