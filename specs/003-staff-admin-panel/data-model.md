# Phase 1 Data Model: Staff Admin Panel (Events, Ticket Types & Orders)

Derived from `spec.md` and `research.md`. This feature adds columns to two existing tables (`events`, `ticket_types`), adds an application-level enum cast to a third (`staff.role`), and reuses `orders`/`order_items`/`staff` otherwise unchanged. No new tables are created.

## `events` (additive/altering migration on top of feature 001)

| Column | Type | Notes |
|---|---|---|
| slug | VARCHAR(255) NOT NULL | New. Unique per spec.md FR-007. Staff-entered, not auto-generated from `name` (spec.md's form lists `slug` as its own field) |
| hero_image_path | VARCHAR(255) NULL | New. Storage path to the uploaded hero image (spec.md FR-006) |
| internal_notes | TEXT NULL | New. Staff-only; never rendered on any public/attendee-facing surface (spec.md FR-006, Key Entities) |
| start_date | **DATETIME** (was `DATE`) | **Changed** (research.md §3) — now carries time-of-day; this is the "Start Date & Time" field the EventResource form exposes |
| end_date | DATE | Unchanged type. **Staff-editable as of the 2026-08-14 post-implementation change** (research.md §3) — optional on the form, defaulting to `start_date`'s calendar date when left blank so single-day events need no extra input; events may span more than one day when staff set a later end date |
| status | VARCHAR(20) | Unchanged column; **backing enum's cases change** (research.md §2) from `draft/published/sold_out/completed/cancelled` to `draft/published/closed/archived`. All four transitions are unrestricted (spec.md Clarification #4) |

**New index**: `UNIQUE (slug)`.

**Constraint change**: the feature-001 CHECK `events_duration_check` (`end_date >= start_date AND end_date <= DATE_ADD(start_date, INTERVAL 1 DAY)`) was first replaced with an equivalent comparing against the date portion of the now-`DATETIME` `start_date` (`end_date >= DATE(start_date) AND end_date <= DATE_ADD(DATE(start_date), INTERVAL 1 DAY)`), then **further relaxed on 2026-08-14** to drop the one-day upper bound entirely: `end_date >= DATE(start_date)`. Events may now span any number of days.

No changes to `id`, `name`, `description`, `venue` (used as spec.md's "location" field — no rename, per spec.md's Assumptions), `created_at`/`updated_at`, `deleted_at`, or the existing `(status, start_date)`/`(end_date)` indexes.

**Validation rules**:
- `slug`: required, unique among non-soft-deleted events (spec.md FR-007) — reuses the same soft-delete-aware "active" uniqueness pattern already established for `attendees.email_active`/`staff.email_active` (feature 001/002) if a collision needs to ignore soft-deleted rows; otherwise a plain unique index is sufficient since there is no attendee-visible "re-registration" concept for event slugs.
- `start_date`: required, date + time.
- `end_date`: optional on the form (defaults to `start_date`'s calendar date); when provided, must be on or after `start_date`'s calendar date (enforced both by the Filament form's `afterOrEqual` rule and the DB CHECK).
- `status`: one of the four `EventStatus` cases; every staff member permitted to edit an event may set it to any of the four, in any order (spec.md Clarification #4 — no state-machine validation).
- `hero_image_path`: optional; standard web image formats within a reasonable size limit (spec.md Assumptions).

**State transitions** (`status`): unrestricted — any value to any other value, at any time, by any staff member with edit permission on the event.

## `ticket_types` (additive migration on top of feature 001)

| Column | Type | Notes |
|---|---|---|
| sales_start_date | DATETIME NULL | New. Optional — a ticket type with no configured window has no sales-timing restriction (spec.md FR-011) |
| sales_end_date | DATETIME NULL | New |

No changes to `id`, `event_id`, `name`, `description`, `price` (kept `DECIMAL(10,2)`, research.md §4), `total_quantity`, `available_quantity`, `version`, `created_at`/`updated_at`, `deleted_at`, the existing `available_quantity >= 0 AND available_quantity <= total_quantity` CHECK, or the FK to `events`.

**Validation rules**:
- `total_quantity`: editable on create and on edit **only while** `available_quantity === total_quantity` (i.e., no tickets sold yet). Once `available_quantity < total_quantity`, the field is presented read-only (spec.md FR-013). This is a Filament form-state rule, not a new DB constraint — the existing CHECK already guarantees `available_quantity` never exceeds `total_quantity` at the database layer. The check is re-evaluated live against the record's current database state at both form-render and save time (not a one-time flag captured at first sale) — see the State Transitions note below.
- `available_quantity`: always read-only in the admin UI (spec.md FR-012). On create, defaults to the submitted `total_quantity` (no sales exist yet for a brand-new ticket type) — set server-side, never staff-entered.
- `price`: entered/displayed as a plain MZN decimal amount; no unit conversion (research.md §4).
- `sales_end_date`, when set, should not precede `sales_start_date` — a form-level validation rule (mirrors the Edge Cases entry in spec.md); not DB-enforced, consistent with `sales_start_date`/`sales_end_date` both being nullable/independent.
- Delete is restricted to `super_admin` (spec.md FR-011), unrestricted by sales state — deleting a ticket type that already has sold tickets is allowed and is the staff member's responsibility, not blocked by this feature.

**State transitions**: none beyond the `total_quantity`-lock behavior above. Per spec.md FR-013 (resolved 2026-08-14), this is a live condition, not a one-way gate: if `available_quantity` is ever restored to equal `total_quantity` (e.g., by a future refund/cancellation feature — no code in this feature does so), the field becomes editable again on the next form load/save, since the check always re-reads current state rather than a flag captured at first sale. This scenario is unobservable within this feature's own scope (nothing here ever increases `available_quantity`); it matters only once a future refund workflow exists.

## `staff` (no schema change — new application-level enum cast only)

**New enum**: `App\Enums\StaffRole: string` — `SuperAdmin = 'super_admin'`, `EventManager = 'event_manager'`, `Support = 'support'`, `GateOperator = 'gate_operator'` (research.md §7).

**Model change**: `Staff::role` cast to `StaffRole::class`. A `null` role, or any value that doesn't map to one of the four cases, is treated as zero access to Events/TicketTypes/Orders — the same posture as `GateOperator` (research.md §7) — not a distinct error state.

**Factory correction**: `StaffFactory::definition()`'s role pool is corrected from `['event_manager', 'support_agent', 'gate_staff']` (two of which match no real role) to the four canonical `StaffRole` values, plus explicit per-role factory states (`superAdmin()`, `eventManager()`, `support()`, `gateOperator()`) for use in this feature's policy tests (research.md §7).

**Seed data** (spec.md FR-020): `DatabaseSeeder` (or a dedicated `StaffSeeder` called from it) creates exactly two `Staff` rows — one `super_admin`, one `event_manager` — with placeholder, non-production credentials (spec.md Assumptions).

## `orders` / `order_items` (reused as-is, no schema change)

No columns change. This feature only reads these tables (spec.md FR-016).

**New non-persisted accessor**: `Order::event(): ?Event`, computed as `$this->orderItems->first()?->ticketType?->event` (research.md §5) — used by the OrderResource's table/infolist "event" display; not a real relationship, not eager-loadable via `with()` in the usual sense (eager-load `orderItems.ticketType.event` instead and let the accessor read the already-loaded relation).

**Displayed fields** (spec.md FR-015, FR-017): `id`, `attendee` (via existing `Order::attendee()`), `attendee.email`, `event()` (new accessor above), `status` (existing `OrderStatus` enum/badge), `total_amount` (labeled "Total (MZN)"), `payment_method` (existing `PaymentMethod` enum), `created_at`. Order items: `ticketType.name`, `quantity`, `unit_price` — all via the existing `OrderItem::ticketType()` relation, read-only.

**No new validation rules or state transitions** — every field on this resource is presented disabled/read-only (spec.md FR-016); no code path in this feature writes to `orders` or `order_items`.

## Role → Resource access matrix (spec.md FR-004, FR-005, FR-010, FR-011, FR-018, FR-028; research.md §7)

| Role | Events | Ticket Types | Orders |
|---|---|---|---|
| `super_admin` | view, create, edit, **delete** | view, create, edit, **delete** | view, **delete** |
| `event_manager` | view, create, edit (no delete) | view, create, edit (no delete) | view (no delete) |
| `support` | — (no access) | — (no access) | view (no delete) |
| `gate_operator` / unrecognized | — (no access) | — (no access) | — (no access) |

No role, including `super_admin`, has create/edit on Orders beyond the narrow ConfirmPayment/Refund actions (spec.md FR-016) — delete is the one exception (FR-028), distinct from editing an order's fields.

## Entity-Relationship Summary (delta from feature 001/002)

```
Event (existing, feature 001) ──gains──> slug, hero_image_path, internal_notes
                                ──changes──> start_date (DATE → DATETIME), status enum cases
                                ──unchanged relation──> hasMany TicketType

TicketType (existing, feature 001) ──gains──> sales_start_date, sales_end_date
                                    ──unchanged──> price stays DECIMAL(10,2), belongsTo Event

Staff (existing, feature 001) ──gains (app-level only)──> StaffRole enum cast on `role`
                               ──referenced-by──> EventPolicy, TicketTypePolicy, OrderPolicy

Order (existing, feature 001) ──gains (app-level only)──> event() computed accessor
                               ──unchanged──> hasMany OrderItem, belongsTo Attendee
```

**Output**: This data model, combined with `research.md`, fully specifies every schema and application-level change this feature needs. This feature exposes no external HTTP/API interface (it is entirely Filament server-rendered Livewire UI), so `contracts/` is intentionally skipped — see `plan.md`'s Project Structure for the equivalent "UI contract" (the resource/page/policy structure itself). Proceed to `quickstart.md` for the validation guide.
