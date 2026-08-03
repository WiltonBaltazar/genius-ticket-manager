# Quickstart: Validating the Core Ticketing Data Model

This is a validation guide for the schema and models described in `data-model.md`, not an implementation walkthrough. Run these once the migrations, factories, and models from `tasks.md` exist.

## Prerequisites

- PHP 8.3+, Composer, and a MySQL 8.0+ instance reachable from `.env` (InnoDB default engine)
- Laravel application installed with this feature's migrations in `database/migrations/` and models in `app/Models/`
- `composer install` run at least once

## 1. Apply the schema

```bash
php artisan migrate:fresh
```

**Expected outcome**: All nine tables (`attendees`, `events`, `ticket_types`, `staff`, `orders`, `order_items`, `tickets`, `audit_logs`, `payment_events`) are created with no errors. Confirm engine/charset:

```bash
php artisan tinker --execute="dd(DB::select(\"SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE()\"))"
```

**Expected outcome**: every row shows `ENGINE = InnoDB` and `TABLE_COLLATION = utf8mb4_unicode_ci`.

## 2. Seed a minimal graph and verify relationships

```bash
php artisan tinker
```

```php
$event = \App\Models\Event::factory()->create(['start_date' => '2026-09-12', 'end_date' => '2026-09-13']);
$type  = \App\Models\TicketType::factory()->for($event)->create(['total_quantity' => 1, 'available_quantity' => 1]);
$attendee = \App\Models\Attendee::factory()->create(['email' => 'test@example.com']);
$order = \App\Models\Order::factory()->for($attendee)->create(['transaction_hash' => 'txn_test_1']);
$item  = \App\Models\OrderItem::factory()->for($order)->for($type, 'ticketType')->create(['quantity' => 1]);
$ticket = \App\Models\Ticket::factory()->for($item, 'orderItem')->for($type, 'ticketType')->create();

$event->ticketTypes; // expect the TicketType above
$order->orderItems;  // expect the OrderItem above
$item->tickets;      // expect the Ticket above
```

**Expected outcome**: each relationship call returns the associated record(s) with no errors.

## 3. Verify no-oversell (optimistic locking)

```php
$a = \App\Models\TicketType::find($type->id);
$b = \App\Models\TicketType::find($type->id);
// Simulate two concurrent readers of the same row (available_quantity = 1, version = 0)
```

**Expected outcome**: attempting to decrement `available_quantity` from both `$a` and `$b` using their captured `version` value results in exactly one successful write; the second write, checked against the now-stale `version`, affects zero rows. (The check-and-increment logic itself belongs to a later feature — this step only confirms the `version` column exists and is usable for that purpose.)

## 4. Verify uniqueness and idempotency constraints

```php
\App\Models\Order::factory()->for($attendee)->create(['transaction_hash' => 'txn_test_1']);
```

**Expected outcome**: throws a unique-constraint violation on `transaction_hash` (FR-006 payment idempotency).

```php
\App\Models\Attendee::factory()->create(['email' => 'test@example.com']);
```

**Expected outcome**: throws a unique-constraint violation on the active-email generated column (FR-023), while the following succeeds after soft-deleting the original:

```php
$attendee->delete(); // soft delete
\App\Models\Attendee::factory()->create(['email' => 'test@example.com']); // now succeeds
```

## 5. Verify FK delete rules

```php
$order->delete();      // soft delete on Order — order_items/tickets remain untouched
$order->forceDelete(); // hard delete — order_items rows for this order are gone (CASCADE)
```

**Expected outcome**: soft-deleting an order leaves its order items and tickets fully intact and queryable; force-deleting it removes its `order_items` rows (cascade) while any `payment_events` rows referencing it have `order_id` set to `null` (not deleted).

```php
$type->delete(); // ticket type still referenced by an order item
```

**Expected outcome**: with a non-soft-deleted referencing `order_items` row still present, hard-deleting the `ticket_types` row is rejected (RESTRICT); soft-deleting it succeeds and the row disappears from active listings only (FR-018).

## 6. Verify ticket check-in and voiding

```php
$ticket->update(['status' => \App\Enums\TicketStatus::CheckedIn, 'checked_in_at' => now(), 'checked_in_by' => $staff->id]);
$order->update(['status' => \App\Enums\OrderStatus::Refunded]);
// Applying the FR-013 voiding rule (enforced by a later feature's logic) should now mark $ticket as voided.
```

**Expected outcome**: the ticket's `status` column can represent `voided` independent of whether it was previously `checked_in`, and a re-check-in attempt against a `voided` ticket has a distinct, queryable state to reject against.

## 7. Verify audit_logs is insert-only at the DB layer

```bash
mysql -u <app_user> -p -e "UPDATE audit_logs SET action='x' WHERE id=1;"
```

**Expected outcome**: fails with an access-denied error once the application database user's `GRANT` has been restricted to `INSERT, SELECT` on `audit_logs` (a deployment task — see `research.md` §6). Until that grant is applied in a given environment, this check only confirms the table has no `updated_at`/`deleted_at` columns for the application layer to (mis)use.

## Success criteria mapping

| Spec success criterion | Validated by step |
|---|---|
| SC-001 (no overselling) | Step 3 |
| SC-002 (unbroken audit/payment history) | Steps 2, 7 |
| SC-003 (no duplicate charges) | Step 4 |
| SC-005 (privacy erasure without data loss) | Step 5 |
| SC-006 (fast lookup by attendee/status/payment ref/QR code) | Indexes confirmed in `data-model.md`; query-plan (`EXPLAIN`) verification belongs to the performance-testing task in `tasks.md` |
