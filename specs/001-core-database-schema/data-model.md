# Phase 1 Data Model: Core Ticketing Data Model

Derived from `spec.md` (entities, requirements, clarifications) and `research.md` (design decisions). All tables use InnoDB, utf8mb4, utf8mb4_unicode_ci. All primary keys are `CHAR(36)` ordered UUIDs (research #1) except `audit_logs`. Unless noted otherwise, foreign keys are `ON DELETE RESTRICT` (constitution default); the only exceptions are `order_items.order_id` (`CASCADE`) and `payment_events.order_id` (`SET NULL`), per the spec's explicit instruction.

## attendees

Maps to spec entity **Attendee** (FR-004, FR-017, FR-018, FR-023).

| Column | Type | Notes |
|---|---|---|
| id | CHAR(36) PK | UUID v7 |
| name | VARCHAR(255) | |
| email | VARCHAR(255) | see `email_active` below for the actual unique constraint |
| email_active | VARCHAR(255) GENERATED | `email` when `deleted_at IS NULL`, else `NULL` (research #3) |
| phone | VARCHAR(255) NULL | |
| created_at, updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | soft delete (GDPR/POPIA erasure) |

**Indexes**: `UNIQUE (email_active)`.

**Relationships**: `hasMany` Order.

**Validation rules**: `email` required, valid email format, unique among active (non-deleted) rows via `email_active` (FR-023). A checkout with an email matching an existing active row reuses that row rather than inserting a new one.

---

## events

Maps to spec entity **Event** (FR-001, FR-002, FR-021).

| Column | Type | Notes |
|---|---|---|
| id | CHAR(36) PK | UUID v7 |
| name | VARCHAR(255) | |
| description | TEXT NULL | |
| venue | VARCHAR(255) NULL | |
| start_date | DATE | |
| end_date | DATE | equals `start_date` (1-day event) or `start_date + 1 day` (2-day event) |
| status | VARCHAR(20) | backed enum `EventStatus`: `draft`, `published`, `sold_out`, `completed`, `cancelled` (research #2) |
| created_at, updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | soft delete |

**Indexes**: `INDEX (status, start_date)` — event status+date lookup pattern; `INDEX (end_date)` — supports matching a 2-day event on its second day (FR-021, edge case).

**Relationships**: `hasMany` TicketType.

**Validation rules**: `end_date >= start_date` and `end_date <= start_date + 1 day` (FR-002 — max 2 consecutive days). `status` must be one of the five enumerated values.

---

## ticket_types

Maps to spec entity **Ticket Type** (FR-003, FR-004).

| Column | Type | Notes |
|---|---|---|
| id | CHAR(36) PK | UUID v7 |
| event_id | CHAR(36) FK → events.id | ON DELETE RESTRICT |
| name | VARCHAR(255) | e.g. "VIP", "General Admission" |
| description | TEXT NULL | |
| price | DECIMAL(10,2) | |
| total_quantity | INT UNSIGNED | |
| available_quantity | INT UNSIGNED | denormalized remaining count |
| version | INT UNSIGNED DEFAULT 0 | optimistic-locking token, incremented on every write to `available_quantity` |
| created_at, updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | soft delete |

**Indexes**: `INDEX (event_id)` — ticket availability by event.

**Relationships**: `belongsTo` Event; `hasMany` OrderItem.

**Validation rules**: `available_quantity >= 0` at all times and `available_quantity <= total_quantity` (FR-004); every update to `available_quantity` must be conditioned on the row's current `version` value and increment it, so two concurrent decrements can never both succeed against the same stale read (enforces FR-004's no-oversell guarantee at the data layer — the check-and-increment logic itself is business logic and out of this feature's scope, but the `version` column it depends on is not).

---

## staff

Maps to spec entity **Staff** (FR-022).

| Column | Type | Notes |
|---|---|---|
| id | CHAR(36) PK | UUID v7 |
| name | VARCHAR(255) | |
| email | VARCHAR(255) | see `email_active` below |
| email_active | VARCHAR(255) GENERATED | same pattern as `attendees.email_active` (research #3) |
| password | VARCHAR(255) | standard Laravel authenticatable field |
| role | VARCHAR(255) NULL | descriptive only; authorization enforcement is out of scope for this feature |
| remember_token | VARCHAR(100) NULL | |
| email_verified_at | TIMESTAMP NULL | |
| created_at, updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | soft delete |

**Indexes**: `UNIQUE (email_active)`.

**Relationships**: `hasMany` Ticket (as `checkedInBy`); `hasMany` AuditLog.

**Validation rules**: `email` required, unique among active rows (same mechanism as Attendee).

---

## orders

Maps to spec entity **Order** (FR-005, FR-006, FR-007, FR-019).

| Column | Type | Notes |
|---|---|---|
| id | CHAR(36) PK | UUID v7 |
| attendee_id | CHAR(36) FK → attendees.id | ON DELETE RESTRICT |
| status | VARCHAR(20) | backed enum `OrderStatus`: `pending`, `paid`, `failed`, `refunded`, `cancelled` (research #2) |
| transaction_hash | VARCHAR(255) | payment idempotency token |
| payment_method | VARCHAR(20) | backed enum `PaymentMethod`: `mpesa`, `offline` |
| payment_reference | VARCHAR(255) NULL | canonical payment reference once confirmed — an M-Pesa receipt number or an offline confirmation reference; the FR-019/FR-006 payment-processor lookup/idempotency column |
| mpesa_checkout_request_id | VARCHAR(255) NULL | M-Pesa only: the `CheckoutRequestID` returned synchronously when STK push is initiated, used to correlate the later asynchronous callback to this order before `payment_reference` is known |
| proof_of_payment_path | VARCHAR(255) NULL | offline only: storage path to the buyer-uploaded proof-of-payment (bank deposit/transfer receipt) |
| total_amount | DECIMAL(10,2) | |
| ip_address | VARCHAR(45) | fraud detection (IPv4/IPv6) |
| user_agent | VARCHAR(512) NULL | fraud detection |
| created_by | CHAR(36) FK → staff.id NULL | ON DELETE RESTRICT; null for a customer-initiated order |
| confirmed_by | CHAR(36) FK → staff.id NULL | ON DELETE RESTRICT |
| confirmed_at | TIMESTAMP NULL | |
| refunded_by | CHAR(36) FK → staff.id NULL | ON DELETE RESTRICT |
| refunded_at | TIMESTAMP NULL | |
| created_at, updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | soft delete |

**Indexes**: `UNIQUE (transaction_hash)` — payment idempotency (FR-006); `UNIQUE (payment_reference)` — order lookup by payment reference (FR-019), constitution Principle IV mandated index; `UNIQUE (mpesa_checkout_request_id)` — idempotent correlation of an M-Pesa callback to its initiating order; `INDEX (attendee_id, status)` — order lookup by attendee/status (FR-019); `INDEX (attendee_id, created_at DESC)` — constitution Principle IV mandated index for reverse-chronological attendee order history.

**Relationships**: `belongsTo` Attendee; `hasMany` OrderItem; `hasMany` PaymentEvent.

**Validation rules / state transitions**: status starts at `pending`; valid transitions are `pending → paid`, `pending → failed`, `pending → cancelled`, `paid → refunded`. `refunded` and other terminal states do not transition further. A `transaction_hash` value is never reused across two orders (FR-006).

---

## order_items

Maps to spec entity **Order Item** (FR-008). No soft delete (not in the spec's soft-delete list).

| Column | Type | Notes |
|---|---|---|
| id | CHAR(36) PK | UUID v7 |
| order_id | CHAR(36) FK → orders.id | **ON DELETE CASCADE** (explicit exception) |
| ticket_type_id | CHAR(36) FK → ticket_types.id | ON DELETE RESTRICT |
| quantity | INT UNSIGNED | |
| unit_price | DECIMAL(10,2) | price at time of purchase, independent of the ticket type's current price |
| subtotal | DECIMAL(10,2) | `quantity * unit_price` |
| created_at, updated_at | TIMESTAMP | |

**Indexes**: `INDEX (order_id)`; `INDEX (ticket_type_id)`.

**Relationships**: `belongsTo` Order; `belongsTo` TicketType; `hasMany` Ticket.

**Validation rules**: `quantity >= 1`. Exactly `quantity` Ticket rows must exist per order item once issued (FR-009).

---

## tickets

Maps to spec entity **Ticket** (FR-009, FR-010, FR-011, FR-012, FR-013). No soft delete — lifecycle is expressed via `status`, not row deletion.

| Column | Type | Notes |
|---|---|---|
| id | CHAR(36) PK | UUID v7 |
| order_item_id | CHAR(36) FK → order_items.id | ON DELETE RESTRICT |
| ticket_type_id | CHAR(36) FK → ticket_types.id | ON DELETE RESTRICT; denormalized from order_item for fast check-in/availability lookups without an extra join |
| qr_code | VARCHAR(64) | unique scan token (research #4) |
| status | VARCHAR(20) | backed enum `TicketStatus`: `unused`, `checked_in`, `voided` (research #2) |
| checked_in_at | TIMESTAMP NULL | |
| checked_in_by | CHAR(36) FK → staff.id NULL | ON DELETE RESTRICT |
| created_at, updated_at | TIMESTAMP | |

**Indexes**: `UNIQUE (qr_code)` — ticket lookup by QR code (FR-020); `INDEX (ticket_type_id, status)` — availability/check-in queries by event.

**Relationships**: `belongsTo` OrderItem; `belongsTo` TicketType; `belongsTo` Staff (as `checkedInBy`).

**Validation rules / state transitions**: starts `unused`; `unused → checked_in` (sets `checked_in_at`/`checked_in_by`, FR-011); `unused → voided` or `checked_in → voided` when the owning order transitions to `refunded` after issuance (FR-012, FR-013 — voiding is terminal, no further transitions, and a voided ticket must reject any subsequent check-in attempt regardless of prior status). Note: a `cancelled` order cannot trigger voiding, since FR-007 defines `cancelled` as pre-payment only, before any tickets exist to void.

---

## audit_logs

Maps to spec entity **Audit Log** (FR-014, FR-016). Append-only: no `updated_at`, no soft delete — rows are never modified or removed once written (enforced at the DB grant level per research #6, outside this migration's scope).

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | strict insertion order |
| staff_id | CHAR(36) FK → staff.id NULL | ON DELETE RESTRICT; null for system-initiated actions |
| action | VARCHAR(255) | e.g. `order.refunded`, `ticket.checked_in` |
| auditable_type | VARCHAR(255) | polymorphic: model class (research #5) |
| auditable_id | CHAR(36) | polymorphic: model id |
| changes | JSON NULL | before/after diff or action-specific metadata |
| ip_address | VARCHAR(45) NULL | |
| created_at | TIMESTAMP | no `updated_at` — immutable |

**Indexes**: `INDEX (auditable_type, auditable_id)` — trace an order/ticket's full history.

**Relationships**: `belongsTo` Staff (nullable).

**Validation rules**: Insert-only. No functional requirement calls for update/delete, and none is provided.

---

## payment_events

Maps to spec entity **Payment Event** (FR-014, FR-015). No soft delete (not in the spec's soft-delete list — these are permanent by design regardless of the referenced order's state).

| Column | Type | Notes |
|---|---|---|
| id | CHAR(36) PK | UUID v7 |
| order_id | CHAR(36) FK → orders.id NULL | **ON DELETE SET NULL** (explicit exception) — retained even if the order is later removed |
| event_type | VARCHAR(100) | e.g. `authorized`, `captured`, `failed`, `refunded` |
| payload | JSON | raw processor payload (research #7) |
| occurred_at | TIMESTAMP | |
| created_at | TIMESTAMP | |

**Indexes**: `INDEX (order_id)`.

**Relationships**: `belongsTo` Order (nullable).

**Validation rules**: Every notification received is inserted as a new row; existing rows are never updated (FR-014).

---

## Entity-Relationship Summary

```
Attendee (1) ──< Order (1) ──< OrderItem (1) ──< Ticket >── (1) TicketType >── (1) Event
                   │                                              
                   ├──< PaymentEvent (order_id nullable)
                   
Staff (1) ──< Order (created_by / confirmed_by / refunded_by, each nullable)
Staff (1) ──< Ticket (checked_in_by, nullable)
Staff (1) ──< AuditLog (staff_id, nullable)
AuditLog ──> (polymorphic) Order | Ticket | Event | TicketType | Attendee | Staff
```

**Constitution index reconciliation**: Principle IV's `(event_id, status)` index tuple has no single table with both an `event_id` FK and a `status` column to index directly. It is satisfied jointly by `events(status, start_date)` (status filtering) and `ticket_types(event_id)` (per-event availability lookups) instead of one literal composite index.

**Post-implementation reconciliation (T063)**: Cross-checked against the actual implemented migrations on 2026-08-03. Two documentation-only drifts were corrected here: `attendees.phone` and `staff.role` are `VARCHAR(255)` (Laravel's default string length) rather than the originally-documented `VARCHAR(50)` — harmless and left as-is rather than altering already-tested migrations. `attendees.ip_address_last_seen` — a speculative, optional column never tied to any functional requirement or task, and never implemented — was removed from this document rather than added to the schema. Every other column, index, and constraint in this document was confirmed to match the implementation exactly.

**Payment provider correction (2026-08-04)**: The project does not use Stripe. Per the amended constitution (v2.0.0), payments are collected via M-Pesa Vodacom Mozambique (STK push initiated server-side, confirmed by an asynchronous callback) or an offline bank deposit/transfer confirmed manually by staff. `orders.stripe_payment_intent_id` was replaced with `payment_method`, `payment_reference`, `mpesa_checkout_request_id`, and `proof_of_payment_path` as described above; FR-019's "payment-processor reference" language already read generically and needed no wording change. The migration, `Order` model, `OrderFactory`, and the schema tests that exercised the old column were updated to match.

**Output**: This data model, combined with `research.md`, fully specifies the schema and Eloquent model relationships. No `contracts/` artifact applies (see plan.md). Proceed to `quickstart.md` for the validation guide.
