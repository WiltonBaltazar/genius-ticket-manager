# Implementation Plan: Core Ticketing Data Model

**Branch**: `001-core-database-schema` | **Date**: 2026-08-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-core-database-schema/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Design the normalized MySQL 8/InnoDB schema and matching Eloquent models for the ticketing system's core data layer — attendees, events, ticket_types, orders, order_items, tickets, staff, audit_logs, and payment_events — enforcing no-oversell inventory via optimistic locking, payment idempotency, an immutable append-only audit trail, GDPR/POPIA-compliant soft deletes, and the index/lookup patterns required for real-time check-in and support/finance queries. No controllers, requests, policies, or other application/business logic are introduced by this feature.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13)

**Primary Dependencies**: Laravel 13 (Eloquent ORM, schema migrations), Pest (test runner); Filament 5 and the React/Vite public site consume these models in later features but add nothing to this one

**Storage**: MySQL 8.0+, InnoDB engine only, utf8mb4 charset, utf8mb4_unicode_ci collation (per constitution Principle IV)

**Testing**: Pest feature tests for migrations/constraints (FK behavior, uniqueness, cascade/restrict/set-null rules) and Pest unit tests for model relationships and casts; concurrency tests simulating simultaneous purchases against the same ticket type (constitution Principle III, non-negotiable for the no-oversell guarantee)

**Target Platform**: Shared hosting (PHP-FPM, SSH, cPanel-style MySQL) — no Docker, no persistent worker processes (constitution Principle VI)

**Project Type**: Web application — single Laravel monolith (this feature touches only the data layer: migrations and models)

**Performance Goals**: Ticket check-in lookup by QR code under 1s (spec SC-004); order/ticket lookup by attendee, status, payment reference, or QR code under 2s (spec SC-006)

**Constraints**: No Redis or queued workers (database queue driver only, per constitution); InnoDB row-level locking is the only mechanism relied on to prevent overselling under concurrent writes; the application's database user must be restricted to INSERT/SELECT-only on `audit_logs` (DB-level grant, not enforceable purely in a migration)

**Scale/Scope**: One annual ticketed event per cycle, with potentially two-day duration; low thousands of attendees/orders/tickets and a handful of ticket types per event — not a high-throughput, multi-tenant, or hyperscale system

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Applies to this feature? | Status |
|---|---|---|
| I. SOLID Architecture & Clean Code | Yes — models restricted to relationships/accessors/casts | ✅ PASS — spec is explicitly schema/models only, no actions/services/controllers exist yet to violate this |
| II. Security by Design | Partially — only the data-layer subset (soft deletes, order audit fields, payment idempotency, immutable audit_logs) | ✅ PASS for the data-layer subset; CSRF, rate limiting, CSP, Filament auth/policies are N/A here — deferred to the features that introduce controllers/routes/Filament resources |
| III. Test-First for Booking-Critical Paths | Yes — inventory-locking concurrency test is non-negotiable | ✅ PASS (gate honored): concurrency test for ticket-type oversell prevention is committed to in `tasks.md`/implementation, not skipped |
| IV. Data Integrity & Immutable Audit Trail | Yes — this principle *is* this feature | ✅ PASS — directly implemented: InnoDB, CHAR(36) UUID PKs (BIGINT for `audit_logs`), FK `ON DELETE RESTRICT` by default with the two documented exceptions, the four constitution-named index patterns (one satisfied by two indexes — see Complexity Tracking), JSON columns for processor payloads, append-only `audit_logs` |
| V. Accessible, On-Brand Experience | No — no UI in this feature | N/A — deferred to future booking-flow/admin-UI features |
| VI. Shared-Hosting-Compatible Simplicity | Yes | ✅ PASS — standard Laravel migrations/Eloquent on MySQL, no new infrastructure or persistent processes introduced |

No unjustified violations. Complexity Tracking table below is intentionally empty.

**Post-Phase-1 re-check**: `data-model.md` and `research.md` were reviewed against this table after Phase 1 design. No new violations were introduced — the generated-column uniqueness trick (research #3), polymorphic audit references (research #5), and denormalized `tickets.ticket_type_id` (data-model.md) are schema-design choices within Principle IV, not exceptions to it. Gate remains PASS.

## Project Structure

### Documentation (this feature)

```text
specs/001-core-database-schema/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

No `contracts/` directory is generated for this feature: it introduces no controllers, routes, API endpoints, or CLI surface — only migrations and Eloquent models consumed internally by later features. Contracts belong to the features that add those interfaces (booking API, Filament admin resources).

### Source Code (repository root)

This is a single Laravel 13 monolith (Filament 5 admin + React/Vite public site share one app); this feature only adds the paths below.

```text
database/
├── migrations/
│   ├── xxxx_xx_xx_xxxxxx_create_attendees_table.php
│   ├── xxxx_xx_xx_xxxxxx_create_events_table.php
│   ├── xxxx_xx_xx_xxxxxx_create_ticket_types_table.php
│   ├── xxxx_xx_xx_xxxxxx_create_staff_table.php
│   ├── xxxx_xx_xx_xxxxxx_create_orders_table.php
│   ├── xxxx_xx_xx_xxxxxx_create_order_items_table.php
│   ├── xxxx_xx_xx_xxxxxx_create_tickets_table.php
│   ├── xxxx_xx_xx_xxxxxx_create_audit_logs_table.php
│   └── xxxx_xx_xx_xxxxxx_create_payment_events_table.php
└── factories/
    ├── AttendeeFactory.php
    ├── EventFactory.php
    ├── TicketTypeFactory.php
    ├── StaffFactory.php
    ├── OrderFactory.php
    ├── OrderItemFactory.php
    └── TicketFactory.php

app/
├── Models/
│   ├── Attendee.php
│   ├── Event.php
│   ├── TicketType.php
│   ├── Staff.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Ticket.php
│   ├── AuditLog.php
│   └── PaymentEvent.php
└── Enums/
    ├── EventStatus.php
    ├── OrderStatus.php
    └── TicketStatus.php

tests/
├── Feature/
│   └── Schema/           # migration/constraint-level: FK restrict/cascade/set-null, uniqueness, optimistic-lock version bump, concurrency/oversell
└── Unit/
    └── Models/            # relationship and cast tests per model

docs/
└── deployment-runbook.md  # ops-only: records the audit_logs INSERT/SELECT-only DB grant (research.md §6); not application source, not covered by tests
```

**Structure Decision**: Single Laravel application, data-layer only. This feature adds nothing under `app/Http`, `app/Actions`, `app/Services`, `routes/`, or `app/Filament` — those belong to later features (booking flow, admin panel) that build on top of this schema. If no Laravel skeleton exists yet in this repository, provisioning one (`composer create-project laravel/laravel`) is a one-time prerequisite task tracked in `tasks.md`, not part of this plan's design.

## Complexity Tracking

> No unjustified violations remain. The one deviation `/speckit-analyze` flagged has been resolved by addition, not by dropping the constitution's requirement — recorded below per Governance's rule that every deviation must be logged here.

| Constitution requirement | What was built instead/in addition | Why |
|---|---|---|
| Principle IV: index `orders` on `(attendee_id, created_at DESC)` | `orders` now carries **both** `(attendee_id, status)` (for FR-019's status-filtered lookup) **and** `(attendee_id, created_at DESC)` (the constitution's literal index) | FR-019 needs status-filtered lookup, which the constitution's suggested index doesn't serve well; rather than choose one over the other, both are kept since the table's expected row count (low thousands, per plan's Scale/Scope) makes the extra index cheap |
| Principle IV: index `(event_id, status)` | `events(status, start_date)` + `ticket_types(event_id)` | No single table has both an `event_id` FK and a `status` column to index directly (see data-model.md's reconciliation note) — the two together satisfy the same query intent |
