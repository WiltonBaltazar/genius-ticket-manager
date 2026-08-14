---

description: "Task list for Core Ticketing Data Model"
---

# Tasks: Core Ticketing Data Model

**Input**: Design documents from `/specs/001-core-database-schema/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, quickstart.md

**Tests**: Included and REQUIRED, not optional — constitution Principle III ("Test-First for Booking-Critical Paths") is non-negotiable for the inventory-locking guarantee this feature implements, and plan.md's Constitution Check explicitly commits to these tests.

**Organization**: Tasks are grouped by user story (from spec.md) to enable independent implementation and testing of each story's guarantee.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no ordering dependency on an incomplete task)
- **[Story]**: Which user story this task belongs to (US1–US5); Setup/Foundational/Polish tasks carry no story label
- File paths are exact and relative to the repository root

## Path Conventions

Single Laravel 13 monolith (per plan.md Structure Decision): `database/migrations/`, `database/factories/`, `app/Models/`, `app/Enums/`, `tests/Feature/Schema/`. This feature adds nothing under `app/Http`, `routes/`, or `app/Filament`.

---

## Phase 1: Setup

**Purpose**: Get a Laravel 13 application and its test tooling ready to receive migrations/models

- [X] T001 Verify or scaffold the Laravel 13 application skeleton (`composer.json`, `artisan`, `config/database.php`) at the repository root; configure `.env` for a local MySQL 8 connection using the `mysql` driver with utf8mb4/utf8mb4_unicode_ci defaults
- [X] T002 Install and configure Pest as the test runner (`composer require pestphp/pest --dev --with-all-dependencies`, `php artisan pest:install`)
- [X] T003 [P] Configure `.env.testing` to point at a real MySQL 8 test database (not SQLite) — required because generated columns, JSON columns, and InnoDB row-locking semantics used by this schema are not equivalently supported in SQLite
- [X] T004 [P] Configure Pest to wrap every feature test in a database transaction with rollback (`uses(Illuminate\Foundation\Testing\DatabaseTransactions::class)->in('Feature')` in `tests/Pest.php`) per constitution Principle III — **except** `TicketTypeOversellTest` (T037), which needs two genuinely independent, uncommitted connections and must opt out of this trait

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The full relational schema and models — every user story's acceptance test depends on the complete Attendee→Order→OrderItem→Ticket→TicketType→Event graph plus Staff/AuditLog/PaymentEvent existing simultaneously, so the base tables/models are shared foundation, not story-specific slices

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

### Migrations (dependency-ordered — FK targets must exist before the migration that references them)

- [X] T005 [P] Create migration for `events` table in `database/migrations/xxxx_xx_xx_xxxxxx_create_events_table.php` — id CHAR(36) PK, name, description NULL, venue NULL, start_date DATE, end_date DATE, status VARCHAR(20), timestamps, deleted_at; `CHECK (end_date >= start_date AND end_date <= DATE_ADD(start_date, INTERVAL 1 DAY))`; `INDEX(status, start_date)`; `INDEX(end_date)` — per data-model.md §events
- [X] T006 [P] Create migration for `attendees` table in `database/migrations/xxxx_xx_xx_xxxxxx_create_attendees_table.php` — id, name, email, `email_active` VARCHAR(255) GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN email END) STORED, phone NULL, timestamps, deleted_at; `UNIQUE(email_active)` — per data-model.md §attendees, research.md §3
- [X] T007 [P] Create migration for `staff` table in `database/migrations/xxxx_xx_xx_xxxxxx_create_staff_table.php` — id, name, email, `email_active` generated column (same pattern as T006), password, role NULL, remember_token NULL, email_verified_at NULL, timestamps, deleted_at; `UNIQUE(email_active)` — per data-model.md §staff
- [X] T008 Create migration for `ticket_types` table in `database/migrations/xxxx_xx_xx_xxxxxx_create_ticket_types_table.php` — id, event_id FK→events.id `ON DELETE RESTRICT`, name, description NULL, price DECIMAL(10,2), total_quantity INT UNSIGNED, available_quantity INT UNSIGNED, version INT UNSIGNED DEFAULT 0, timestamps, deleted_at; `CHECK (available_quantity >= 0 AND available_quantity <= total_quantity)`; `INDEX(event_id)` — depends on T005 (events must exist)
- [X] T009 Create migration for `orders` table in `database/migrations/xxxx_xx_xx_xxxxxx_create_orders_table.php` — id, attendee_id FK→attendees.id `ON DELETE RESTRICT`, status VARCHAR(20), transaction_hash VARCHAR(255), payment_method VARCHAR(20), payment_reference VARCHAR(255) NULL, mpesa_checkout_request_id VARCHAR(255) NULL, proof_of_payment_path VARCHAR(255) NULL, total_amount DECIMAL(10,2), ip_address VARCHAR(45), user_agent VARCHAR(512) NULL, created_by/confirmed_by/refunded_by CHAR(36) FK→staff.id NULL `ON DELETE RESTRICT`, confirmed_at NULL, refunded_at NULL, timestamps, deleted_at; `UNIQUE(transaction_hash)`; `UNIQUE(payment_reference)`; `UNIQUE(mpesa_checkout_request_id)`; `INDEX(attendee_id, status)`; `INDEX(attendee_id, created_at DESC)` (constitution Principle IV) — depends on T006, T007 (updated 2026-08-04: payment fields corrected from Stripe to M-Pesa/offline, see tasks.md note at end of file)
- [X] T010 Create migration for `order_items` table in `database/migrations/xxxx_xx_xx_xxxxxx_create_order_items_table.php` — id, order_id FK→orders.id **`ON DELETE CASCADE`**, ticket_type_id FK→ticket_types.id `ON DELETE RESTRICT`, quantity INT UNSIGNED, unit_price DECIMAL(10,2), subtotal DECIMAL(10,2), timestamps; `INDEX(order_id)`; `INDEX(ticket_type_id)` — depends on T008, T009
- [X] T011 Create migration for `tickets` table in `database/migrations/xxxx_xx_xx_xxxxxx_create_tickets_table.php` — id, order_item_id FK→order_items.id `ON DELETE RESTRICT`, ticket_type_id FK→ticket_types.id `ON DELETE RESTRICT`, qr_code VARCHAR(64), status VARCHAR(20) DEFAULT 'unused', checked_in_at NULL, checked_in_by CHAR(36) FK→staff.id NULL `ON DELETE RESTRICT`, timestamps; `UNIQUE(qr_code)`; `INDEX(ticket_type_id, status)` — depends on T007, T008, T010
- [X] T012 [P] Create migration for `audit_logs` table in `database/migrations/xxxx_xx_xx_xxxxxx_create_audit_logs_table.php` — id BIGINT UNSIGNED AUTO_INCREMENT PK, staff_id CHAR(36) FK→staff.id NULL `ON DELETE RESTRICT`, action VARCHAR(255), auditable_type VARCHAR(255), auditable_id CHAR(36), changes JSON NULL, ip_address VARCHAR(45) NULL, created_at only (no updated_at); `INDEX(auditable_type, auditable_id)` — depends on T007
- [X] T013 [P] Create migration for `payment_events` table in `database/migrations/xxxx_xx_xx_xxxxxx_create_payment_events_table.php` — id, order_id CHAR(36) FK→orders.id NULL **`ON DELETE SET NULL`**, event_type VARCHAR(100), payload JSON, occurred_at, created_at; `INDEX(order_id)` — depends on T009

### Shared model concern

- [X] T014 [P] ~~Create `HasOrderedUuid` trait~~ — superseded during implementation: Laravel 13's built-in `Illuminate\Database\Eloquent\Concerns\HasUuids` already generates UUID v7 (ordered) keys by default (confirmed by reading its source), so every model except `AuditLog` uses that directly instead of a redundant custom trait, per research.md §1's intent

### Enums

- [X] T015 [P] Create `EventStatus` backed enum in `app/Enums/EventStatus.php` (`Draft`, `Published`, `SoldOut`, `Completed`, `Cancelled`)
- [X] T016 [P] Create `OrderStatus` backed enum in `app/Enums/OrderStatus.php` (`Pending`, `Paid`, `Failed`, `Refunded`, `Cancelled`)
- [X] T017 [P] Create `TicketStatus` backed enum in `app/Enums/TicketStatus.php` (`Unused`, `CheckedIn`, `Voided`)

### Models

- [X] T018 [P] Create `Event` model in `app/Models/Event.php` — uses `HasOrderedUuid`, soft deletes, `status` cast to `EventStatus`, `hasMany(TicketType::class)` — depends on T005, T014, T015
- [X] T019 [P] Create `Attendee` model in `app/Models/Attendee.php` — uses `HasOrderedUuid`, soft deletes, `hasMany(Order::class)` — depends on T006, T014
- [X] T020 [P] Create `Staff` model in `app/Models/Staff.php` — extends `Authenticatable`, uses `HasOrderedUuid`, soft deletes, `hasMany(Ticket::class, 'checked_in_by')`, `hasMany(AuditLog::class)` — depends on T007, T014
- [X] T021 [P] Create `TicketType` model in `app/Models/TicketType.php` — uses `HasOrderedUuid`, soft deletes, `belongsTo(Event::class)`, `hasMany(OrderItem::class)` — depends on T008, T014
- [X] T022 [P] Create `Order` model in `app/Models/Order.php` — uses `HasOrderedUuid`, soft deletes, `status` cast to `OrderStatus`, `belongsTo(Attendee::class)`, `hasMany(OrderItem::class)`, `hasMany(PaymentEvent::class)`, `belongsTo(Staff::class, 'created_by')`, `belongsTo(Staff::class, 'confirmed_by')`, `belongsTo(Staff::class, 'refunded_by')` — depends on T009, T014, T016
- [X] T023 [P] Create `OrderItem` model in `app/Models/OrderItem.php` — uses `HasOrderedUuid`, `belongsTo(Order::class)`, `belongsTo(TicketType::class)`, `hasMany(Ticket::class)` — depends on T010, T014
- [X] T024 [P] Create `Ticket` model in `app/Models/Ticket.php` — uses `HasOrderedUuid`, `status` cast to `TicketStatus`, `belongsTo(OrderItem::class)`, `belongsTo(TicketType::class)`, `belongsTo(Staff::class, 'checked_in_by')` — depends on T011, T014, T017
- [X] T025 [P] Create `AuditLog` model in `app/Models/AuditLog.php` — `BIGINT` key (no `HasOrderedUuid`), `const UPDATED_AT = null`, `morphTo('auditable')`, `belongsTo(Staff::class)` — depends on T012
- [X] T026 [P] Create `PaymentEvent` model in `app/Models/PaymentEvent.php` — uses `HasOrderedUuid`, `belongsTo(Order::class)` (nullable) — depends on T013, T014

### Factories

- [X] T027 [P] Create `EventFactory` in `database/factories/EventFactory.php`
- [X] T028 [P] Create `AttendeeFactory` in `database/factories/AttendeeFactory.php`
- [X] T029 [P] Create `StaffFactory` in `database/factories/StaffFactory.php`
- [X] T030 [P] Create `TicketTypeFactory` in `database/factories/TicketTypeFactory.php`
- [X] T031 [P] Create `OrderFactory` in `database/factories/OrderFactory.php`
- [X] T032 [P] Create `OrderItemFactory` in `database/factories/OrderItemFactory.php`
- [X] T033 [P] Create `TicketFactory` in `database/factories/TicketFactory.php`
- [X] T034 [P] Create `AuditLogFactory` in `database/factories/AuditLogFactory.php`
- [X] T035 [P] Create `PaymentEventFactory` in `database/factories/PaymentEventFactory.php`

### Foundational verification

- [X] T036 [P] Pest feature test asserting all 9 tables exist with `ENGINE = InnoDB` and `TABLE_COLLATION = utf8mb4_unicode_ci` in `tests/Feature/Schema/TableEngineTest.php` (quickstart.md step 1)

**Checkpoint**: Foundation ready — schema, models, factories, and a baseline engine/charset test all exist. User story work can now begin.

---

## Phase 3: User Story 1 - Reliable Ticket Inventory, No Overselling (Priority: P1) 🎯 MVP

**Goal**: Guarantee `ticket_types.available_quantity` can never go negative or be oversold, even under concurrent purchase attempts (spec SC-001)

**Independent Test**: Set a ticket type's `available_quantity` to 1; issue two concurrent decrement attempts using version-matched conditional updates; assert exactly one succeeds and the row ends at 0

### Tests for User Story 1

- [X] T037 [P] [US1] Pest test (excluded from the global `DatabaseTransactions` wrapper configured in T004 — uses a second raw connection and manual cleanup instead, since it needs two genuinely independent, uncommitted connections racing the same row): two concurrent version-matched decrements against the same `ticket_types` row (available_quantity=1) — only one succeeds, final `available_quantity` is 0 and `version` incremented once, in `tests/Feature/Schema/TicketTypeOversellTest.php`
- [X] T038 [P] [US1] Pest test: the `available_quantity` CHECK constraint rejects a value below 0 or above `total_quantity` in `tests/Feature/Schema/TicketTypeQuantityBoundsTest.php`
- [X] T039 [P] [US1] Pest test: `version` increments on every successful `available_quantity` update in `tests/Feature/Schema/TicketTypeVersionTest.php`

### Implementation for User Story 1

- [X] T040 [US1] Add `isSoldOut()` accessor and `available()` local query scope to `TicketType` model in `app/Models/TicketType.php` (depends on T021)

**Checkpoint**: User Story 1 is fully functional and independently testable — this is the MVP.

---

## Phase 4: User Story 2 - Auditable Order & Payment History (Priority: P1)

**Goal**: Every order and every payment notification against it is permanently and immutably recorded (spec SC-002)

**Independent Test**: Create an order, attach several `payment_events` and `audit_logs` entries, confirm the full ordered history is retrievable and none of it can be altered

### Tests for User Story 2

- [X] T041 [P] [US2] Pest test: inserting multiple `payment_events` rows for the same `order_id` preserves every row (no overwrite) in `tests/Feature/Schema/PaymentEventHistoryTest.php`
- [X] T042 [P] [US2] Pest test: force-deleting an `Order` sets `payment_events.order_id` to `null` on its rows rather than deleting them in `tests/Feature/Schema/PaymentEventOrderDeletionTest.php`
- [X] T043 [P] [US2] Pest test: `audit_logs` has no `updated_at`/`deleted_at` columns and the `AuditLog` model exposes no update/delete path in `tests/Feature/Schema/AuditLogImmutabilityTest.php`
- [X] T044 [P] [US2] Pest test: `audit_logs.auditable_type`/`auditable_id` correctly resolves to an `Order`, a `Ticket`, and a `Staff` record (three assertions) in `tests/Feature/Schema/AuditLogPolymorphicTest.php`
- [X] T045 [P] [US2] Pest test: `orders.created_by`/`confirmed_by`/`refunded_by`/`confirmed_at`/`refunded_at` persist and are retrievable in `tests/Feature/Schema/OrderAuditFieldsTest.php`

### Implementation for User Story 2

- [X] T046 [US2] Add `auditLogs()` morphMany relationship to `Order` and `Ticket` models in `app/Models/Order.php`, `app/Models/Ticket.php` (depends on T022, T024, T025)

**Checkpoint**: User Stories 1 AND 2 both work independently.

---

## Phase 5: User Story 3 - Fraud Signal Capture on Orders (Priority: P2)

**Goal**: Every order retains its originating network/device context and a payment idempotency token (spec SC-003)

**Independent Test**: Create an order with an IP/user-agent and confirm both are retrievable; attempt to insert a second order with a duplicate `transaction_hash` and confirm it's rejected

### Tests for User Story 3

- [X] T047 [P] [US3] Pest test: `orders.ip_address` and `orders.user_agent` persist and are retrievable in `tests/Feature/Schema/OrderFraudSignalTest.php`
- [X] T048 [P] [US3] Pest test: inserting a second order with a duplicate `transaction_hash` throws a unique-constraint violation in `tests/Feature/Schema/OrderTransactionHashUniquenessTest.php`
- [X] T049 [P] [US3] Pest test: inserting a second order with a duplicate `payment_reference` throws a unique-constraint violation in `tests/Feature/Schema/OrderPaymentReferenceUniquenessTest.php` (renamed 2026-08-04, was `OrderPaymentIntentUniquenessTest.php`)

No implementation tasks beyond Foundational — the fraud-signal columns and uniqueness constraints were already established in T009; this phase only adds the tests proving them.

**Checkpoint**: User Stories 1, 2, AND 3 all work independently.

---

## Phase 6: User Story 4 - GDPR/POPIA-Compliant Data Removal (Priority: P2)

**Goal**: Soft-deleting an attendee/event/ticket type/order/staff record never breaks historical references, and erasure frees an attendee's email for legitimate reuse (spec SC-005)

**Independent Test**: Soft-delete an attendee who has past orders; confirm their orders/tickets remain fully queryable; confirm a new attendee can register with the same email afterward

### Tests for User Story 4

- [X] T050 [P] [US4] Pest test: soft-deleting an `Attendee` sets `deleted_at`, hides it from default queries, while its past `Order`/`Ticket` records remain fully queryable in `tests/Feature/Schema/AttendeeSoftDeleteTest.php`
- [X] T051 [P] [US4] Pest test: after soft-deleting an `Attendee`, a new `Attendee` can be created with the same `email` (the `email_active` generated column frees it) in `tests/Feature/Schema/AttendeeEmailReuseTest.php`
- [X] T052 [P] [US4] Pest test: soft-deleting an `Event`, `TicketType`, or `Staff` record hides it from default queries while existing `Order`/`Ticket` references remain intact in `tests/Feature/Schema/SoftDeleteReferentialIntegrityTest.php`
- [X] T053 [P] [US4] Pest test: hard-deleting an `Event`, `TicketType`, `Attendee`, or `Staff` record that still has a non-soft-deleted referencing row is rejected (FK RESTRICT) in `tests/Feature/Schema/HardDeleteRestrictTest.php`
- [X] T054 [P] [US4] Pest test: force-deleting an `Order` cascades to delete its `OrderItem` rows in `tests/Feature/Schema/OrderItemCascadeTest.php`

No implementation tasks beyond Foundational — soft-delete behavior and FK rules were already established in T005–T011; this phase only adds the tests proving them.

**Checkpoint**: User Stories 1–4 all work independently.

---

## Phase 7: User Story 5 - Fast, Unambiguous Ticket Check-In (Priority: P3)

**Goal**: A ticket's check-in state (`unused` → `checked_in` / `voided`) is unambiguous, repeat check-ins are rejected, and refunded orders void their tickets (spec SC-004, FR-012, FR-013)

**Independent Test**: Check in an unused ticket; re-scan the same ticket (already used); refund its order and confirm the ticket becomes `voided` and a further check-in attempt is rejected

### Tests for User Story 5

- [X] T055 [P] [US5] Pest test: updating a `Ticket` from `unused` to `checked_in` records `checked_in_at`/`checked_in_by` in `tests/Feature/Schema/TicketCheckInTest.php`
- [X] T056 [P] [US5] Pest test: `qr_code` uniqueness is enforced — a duplicate value is rejected in `tests/Feature/Schema/TicketQrCodeUniquenessTest.php`
- [X] T057 [P] [US5] Pest test: looking up a ticket by `qr_code` returns the correct record with its `ticketType`/`orderItem` relations eager-loadable in `tests/Feature/Schema/TicketLookupByQrCodeTest.php`
- [X] T058 [P] [US5] Pest test: a `Ticket` can reach `voided` from either `unused` or `checked_in` when its order transitions to `refunded` (not `cancelled` — a cancelled order per FR-007 is always pre-payment, so it never has issued tickets to void), and a `voided` ticket is distinguishable from `checked_in` for a subsequent check-in attempt in `tests/Feature/Schema/TicketVoidingTest.php`

### Implementation for User Story 5

- [X] T059 [US5] Add `isCheckedIn()` and `isVoided()` accessors to `Ticket` model in `app/Models/Ticket.php` (depends on T024)

**Checkpoint**: All 5 user stories are independently functional.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Validate the whole feature end-to-end against its own design artifacts

- [X] T060 [P] Run the `quickstart.md` validation guide end-to-end against a fresh `php artisan migrate:fresh` and record results in `specs/001-core-database-schema/quickstart-results.md`
- [X] T061 [P] Pest test verifying the index groups from data-model.md exist — `events(status, start_date)`, `ticket_types(event_id)`, `orders(attendee_id, status)`, `orders(attendee_id, created_at DESC)` (constitution Principle IV, added per plan.md's Complexity Tracking), `orders(payment_reference)` unique, `tickets(qr_code)` unique — via `information_schema.statistics` in `tests/Feature/Schema/IndexPresenceTest.php` (updated 2026-08-04, was `orders(stripe_payment_intent_id)`)
- [X] T062 Record the required `audit_logs` INSERT/SELECT-only database grant (research.md §6) as a deployment runbook step in `docs/deployment-runbook.md` — this is a DB-level `GRANT` applied per environment, not something a migration can express
- [X] T063 Cross-check every column, index, and constraint in the implemented migrations against `data-model.md` and reconcile any drift found

### Coverage gaps closed by `/speckit-analyze`

The tasks below were added after cross-artifact analysis found several requirements had no dedicated verifying test: FR-002/FR-021 (event duration/multi-day matching), FR-019/SC-006 (order lookup performance), FR-012 (repeat check-in distinct from voiding), FR-023's baseline uniqueness case, FR-003/FR-009/FR-022 (multiple ticket types per event, ticket-count-matches-quantity, staff/attendee distinction), and SC-004 (ticket lookup performance, added in a follow-up analysis pass once SC-006 was addressed but its ticket-side counterpart was initially missed). Appended here (rather than inserted earlier and renumbering T001–T063) since none of them block or are blocked by any task in between.

- [X] T064 [P] Pest test: the `events` table's CHECK constraint rejects an `end_date` more than 1 day after `start_date` (FR-002) in `tests/Feature/Schema/EventDurationBoundsTest.php`
- [X] T065 [P] Pest test: querying events active on a given date matches a 2-day event on both its `start_date` and `end_date` (FR-021) in `tests/Feature/Schema/EventMultiDayLookupTest.php`
- [X] T066 [P] Pest test: order lookup by `attendee_id`, by `status`, and by `payment_reference` each return the correct row(s) (FR-019); combined with an `EXPLAIN`/timing check against a seeded representative row count to validate SC-006 in `tests/Feature/Schema/OrderLookupPerformanceTest.php` (updated 2026-08-04, was `stripe_payment_intent_id`)
- [X] T067 [P] [US5] Pest test: scanning a ticket already in `checked_in` status (not `voided`) is detected as already-used, distinct from the voiding path tested in T058 (FR-012, US5 Acceptance Scenario 2) in `tests/Feature/Schema/TicketRepeatCheckInTest.php`
- [X] T068 [P] Pest test: two non-deleted `Attendee` records cannot share the same `email` — the baseline uniqueness rule underlying FR-023, distinct from the post-erasure reuse case already tested in T051 — in `tests/Feature/Schema/AttendeeEmailUniquenessTest.php`
- [X] T069 [P] [US1] Pest test: an `Event` can have more than one `TicketType` simultaneously, each with independent pricing/availability (FR-003) in `tests/Feature/Schema/EventMultipleTicketTypesTest.php`
- [X] T070 [P] [US1] Pest test: an `OrderItem` with `quantity = 3` has exactly 3 associated `Ticket` rows via its `tickets()` relationship (FR-009) in `tests/Feature/Schema/OrderItemTicketCardinalityTest.php`
- [X] T071 [P] Pest test: `Staff` and `Attendee` are distinct models/tables with no shared identity, and a `Ticket.checked_in_by` / `Order.confirmed_by` always resolves to a `Staff` instance, never an `Attendee` (FR-022) in `tests/Feature/Schema/StaffAttendeeDistinctionTest.php`
- [X] T072 [P] Pest test: ticket lookup by `qr_code` (FR-020) combined with an `EXPLAIN`/timing check against a seeded representative row count to validate SC-004's under-1-second target — the ticket/QR-code counterpart to T066's order-lookup timing check for SC-006 — in `tests/Feature/Schema/TicketLookupPerformanceTest.php`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories; migrations T005–T013 must run in the listed order due to FK targets
- **User Stories (Phase 3–7)**: All depend on Foundational completion; once it's done, all 5 stories can proceed in parallel or in priority order (P1 → P1 → P2 → P2 → P3)
- **Polish (Phase 8)**: Depends on all desired user stories being complete (T064–T067 specifically depend on the Foundational events/orders/tickets schema — T005, T009, T011 — and on T058 for T067's contrast case, not on the full user-story set)

### User Story Dependencies

- **US1 (P1)**: No dependency on other stories — the MVP
- **US2 (P1)**: No dependency on other stories
- **US3 (P2)**: No dependency on other stories
- **US4 (P2)**: No dependency on other stories
- **US5 (P3)**: No dependency on other stories (its voiding-on-refund test reuses the Order/Ticket rows from Foundational, not from US1/US2's test runs)

### Within Each User Story

- Tests are written first and must fail before any story-specific implementation task
- Story complete before moving to the next priority, if working sequentially

### Parallel Opportunities

- T001–T004 (Setup): T003, T004 in parallel after T001, T002
- T005–T007, T012–T013 (Foundational migrations with no interdependency): parallel
- T008–T011 (Foundational migrations with FK dependencies): sequential, in the listed order
- T014–T035 (trait, enums, models, factories): parallel once their respective migrations exist
- All test tasks within a single user story phase: parallel (different files)
- Different user story phases: parallel across developers once Foundational is done

---

## Parallel Example: User Story 1

```bash
# Launch all three tests for User Story 1 together:
Task: "Pest test: concurrent version-matched decrements in tests/Feature/Schema/TicketTypeOversellTest.php"
Task: "Pest test: available_quantity CHECK bounds in tests/Feature/Schema/TicketTypeQuantityBoundsTest.php"
Task: "Pest test: version increments on update in tests/Feature/Schema/TicketTypeVersionTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational — note this phase is unusually large for this feature because the nine tables form one tightly-coupled relational graph (an order can't exist without an attendee, a ticket can't exist without an order item and a ticket type); there is no meaningful smaller schema slice to defer
3. Complete Phase 3: User Story 1 (oversell prevention)
4. **STOP and VALIDATE**: run T037–T039 independently; confirm the MVP guarantee holds
5. This proves the schema's highest-risk guarantee before adding the remaining stories

### Incremental Delivery

1. Setup + Foundational → full schema, models, and factories exist
2. Add US1 → validate no-oversell → this is the MVP
3. Add US2 → validate audit/payment history
4. Add US3 → validate fraud-signal columns and idempotency
5. Add US4 → validate soft-delete/erasure behavior
6. Add US5 → validate check-in and voiding
7. Polish → run quickstart.md, verify indexes, record the audit_logs grant

### Parallel Team Strategy

Once Foundational (Phase 2) is done, up to 5 developers can take one user story phase each (US1–US5) — none of the story phases modify Foundational files or depend on another story's implementation tasks (T040 and T046/T059 each touch a different model).

---

## Notes

- [P] tasks = different files, no ordering dependency
- [Story] label maps every story-phase task to its user story for traceability back to spec.md
- Every test task must fail before its (if any) implementation task, per constitution Principle III
- Commit after each task or logical group
- Avoid: vague tasks, same-file conflicts, cross-story dependencies that would break independence
