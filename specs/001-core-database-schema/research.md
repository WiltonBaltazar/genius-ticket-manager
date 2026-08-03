# Phase 0 Research: Core Ticketing Data Model

All Technical Context fields were resolvable directly from the project constitution and the feature spec — no `NEEDS CLARIFICATION` markers remain. The items below are schema/model-design decisions that the constitution and spec constrain but don't fully dictate, resolved here so Phase 1 design has no open questions.

## 1. UUID generation strategy for primary keys

**Decision**: Use ordered (time-sortable) UUIDs — UUID v7 — for every `CHAR(36)` primary key, generated via Laravel's built-in ordered-UUID model trait. `audit_logs` is unaffected (it uses `BIGINT` auto-increment per the constitution).

**Rationale**: A random UUID v4 primary key causes InnoDB to insert new rows at random points in the clustered index, fragmenting pages and degrading write throughput — exactly the failure mode most likely during a ticket on-sale burst (many concurrent `tickets`/`orders` inserts). UUID v7 keeps the monotonic-insert benefit of an auto-increment key while remaining a non-sequential, non-enumerable external identifier, preserving the constitution's reason for mandating UUIDs in the first place.

**Alternatives considered**: Random UUID v4 (simplest, but the write-amplification problem above is a known, well-documented InnoDB issue at this table's expected write pattern). ULID (same ordering benefit, but no native Laravel/Eloquent column-type support over plain UUID v7 — adds a dependency for no schema-level gain).

## 2. Status column representation (Event, Order, Ticket)

**Decision**: Store `status` as `VARCHAR`, cast to a PHP backed enum (`EventStatus`, `OrderStatus`, `TicketStatus`) at the Eloquent model layer. Do not use MySQL's native `ENUM` column type.

**Rationale**: A native MySQL `ENUM` requires an `ALTER TABLE ... MODIFY COLUMN` (full table rewrite) to add a new value later; a `VARCHAR` + PHP enum only requires a code change. Laravel's native enum casting gives the same application-level type safety without that migration risk.

**Alternatives considered**: MySQL native `ENUM` (rejected — costly schema change to extend, e.g. if a future status like `postponed` is added). Plain `VARCHAR` with no cast (rejected — no type safety, allows typos to silently create an invalid status value).

## 3. Email uniqueness across soft-deleted Attendee/Staff records

**Decision**: Do not put a plain `UNIQUE` index on `email`. Instead, add a generated (virtual) column — e.g. `email_active` = `email` when `deleted_at IS NULL`, else `NULL` — and put the `UNIQUE` index on that generated column, on both `attendees` and `staff`.

**Rationale**: FR-023 requires one canonical Attendee record per email; FR-017 requires that removing an Attendee for a privacy request not break historical data. A plain `UNIQUE(email)` would satisfy both individually but permanently lock that email address out of ever registering again after an erasure — undesirable, since the same person may legitimately attend a future event. MySQL treats `NULL` as distinct across rows in a unique index, so nulling the generated column on soft-deleted rows frees the email for reuse while still enforcing uniqueness among active (non-deleted) rows at the database level, matching the constitution's requirement that integrity constraints live in the database, not just application code.

**Alternatives considered**: Plain `UNIQUE(email)` (rejected — blocks legitimate re-registration after erasure). Application-only uniqueness check with no DB constraint (rejected — constitution Principle IV requires database-enforced integrity, not just app-layer checks).

## 4. Ticket QR code column

**Decision**: `qr_code` is a `VARCHAR(64)` unique column holding a random token, generated independently of the ticket's own UUID primary key.

**Rationale**: Decoupling the externally-scanned code from the internal primary key means neither value helps guess the other (defense in depth for a value that grants physical event entry). 64 characters comfortably fits common QR-safe encodings of a high-entropy random value without truncation risk.

**Alternatives considered**: Reusing the ticket's own UUID as the QR payload (rejected — ties an internally-referenced identifier directly to an externally-shared one). A shorter fixed-length numeric code (rejected — smaller keyspace increases guessability for an entry credential).

## 5. Audit trail reference modeling

**Decision**: `audit_logs` references the affected record via a polymorphic pair — `auditable_type` (model class) and `auditable_id` (`CHAR(36)`) — rather than one nullable foreign key column per possible referenced entity (order, ticket, event, ticket type, staff, attendee).

**Rationale**: The audit trail must be able to reference any of several entity types generically. A fixed FK-per-entity-type design means the table grows a new nullable column every time a new auditable entity is introduced, with most rows leaving most of those columns null.

**Alternatives considered**: One nullable FK column per auditable entity type (rejected — doesn't scale as entity types grow, and can't enforce `ON DELETE RESTRICT` any more meaningfully than the polymorphic approach since the referenced row may itself be soft-deleted, not hard-deleted).

## 6. `audit_logs` append-only enforcement

**Decision**: Enforce append-only behavior at two layers: (a) no code path in the application ever calls `update()` or `delete()` against this model — tracked as a convention/lint concern for the feature that first writes to this table; (b) the database application user's `GRANT` is restricted to `INSERT, SELECT` only on `audit_logs` — a deployment/ops task, not something a migration file can express.

**Rationale**: This directly matches the constitution's explicit requirement that database-level permissions, not just application discipline, prevent mutation of this table. A migration alone creates the table; it cannot set a `GRANT`, so this is flagged here rather than silently dropped.

**Alternatives considered**: Application-layer convention only (rejected — constitution Principle IV explicitly requires DB-level enforcement).

## 7. `payment_events` payload storage

**Decision**: Store the raw payment-processor notification as a `JSON` column (`payload`), per the constitution's explicit instruction that "Stripe webhook payloads and other complex audit data MUST be stored in JSON columns rather than flattened into ad-hoc fields."

**Rationale**: Matches the constitution directly; avoids needing a schema migration every time the payment processor adds or changes a field in its webhook payloads.

**Alternatives considered**: Flattened columns per payload field (rejected — explicitly disallowed by the constitution).

---

**Output**: All decisions above resolve directly into the entity definitions in `data-model.md`. No open questions remain for Phase 1.
