# Quickstart Validation Results

**Run date**: 2026-08-03
**Environment**: Laravel 13.23.0, PHP 8.3.22, MySQL 8.0.44 (Docker container `genius-ticket-mysql`), Pest 4.7.7

This records the outcome of running `quickstart.md`'s validation guide end-to-end against `php artisan migrate:fresh`, per task T060. Steps 2–7 of `quickstart.md` are exercised more rigorously by the automated Pest suite than by manual Tinker commands, so this run validates via `php artisan migrate:fresh` (both `genius_ticket_manager` and `genius_ticket_manager_test`) followed by the full test suite, and cross-references each quickstart step to the test(s) that cover it.

## Step 1 — Apply the schema

`php artisan migrate:fresh --force` (both databases): all 9 feature tables plus Laravel's default 3 created with no errors. Engine/collation confirmed `InnoDB` / `utf8mb4_unicode_ci` for all 9 — see `tests/Feature/Schema/TableEngineTest.php` (T036).

## Step 2 — Seed a minimal graph and verify relationships

Covered by every factory-based test in the suite (e.g. `EventMultipleTicketTypesTest`, `OrderItemTicketCardinalityTest`) — relationships resolve with no errors across all 9 models.

## Step 3 — Verify no-oversell (optimistic locking)

Covered by `tests/Feature/Schema/TicketTypeOversellTest.php` (T037): two independent connections racing a version-matched conditional update — exactly one succeeds.

## Step 4 — Verify uniqueness and idempotency constraints

Covered by `OrderTransactionHashUniquenessTest`, `AttendeeEmailUniquenessTest` (baseline case), and `AttendeeEmailReuseTest` (post-erasure reuse via the `email_active` generated column).

## Step 5 — Verify FK delete rules

Covered by `SoftDeleteReferentialIntegrityTest`, `HardDeleteRestrictTest`, `OrderItemCascadeTest`, and `PaymentEventOrderDeletionTest` (RESTRICT, CASCADE, and SET NULL all exercised).

## Step 6 — Verify ticket check-in and voiding

Covered by `TicketCheckInTest`, `TicketRepeatCheckInTest`, and `TicketVoidingTest`.

## Step 7 — Verify audit_logs is insert-only at the DB layer

Schema-level check (no `updated_at`/`deleted_at` columns) covered by `AuditLogImmutabilityTest`. The DB-grant enforcement itself is an environment-specific deployment step, recorded in `docs/deployment-runbook.md` (T062) rather than testable in this local environment.

## Overall result

**57/57 tests passed, 166 assertions**, run twice consecutively against a freshly migrated schema with no flakiness observed. Full suite: `php artisan test`.

## Success criteria mapping (confirmed)

| Spec success criterion | Result |
|---|---|
| SC-001 (no overselling) | ✅ `TicketTypeOversellTest` |
| SC-002 (unbroken audit/payment history) | ✅ `PaymentEventHistoryTest`, `AuditLogImmutabilityTest`, `AuditLogPolymorphicTest` |
| SC-003 (no duplicate charges) | ✅ `OrderTransactionHashUniquenessTest`, `OrderPaymentIntentUniquenessTest` |
| SC-004 (<1s ticket check-in lookup) | ✅ `TicketLookupPerformanceTest` — measured under representative load |
| SC-005 (privacy erasure without data loss) | ✅ `AttendeeSoftDeleteTest`, `AttendeeEmailReuseTest` |
| SC-006 (<2s order/ticket lookup) | ✅ `OrderLookupPerformanceTest` — measured under representative load |
