# Phase 0 Research: Staff Admin Panel (Events, Ticket Types & Orders)

All Technical Context fields were resolvable from the constitution, spec.md's Clarifications, and the already-implemented feature 001/002 codebase — no `NEEDS CLARIFICATION` markers remain. Several items below are genuine conflicts discovered by reading the actual installed schema/code, not just the spec, in the same spirit as research.md §2 and §6 in feature 002.

## 1. Filament 5 install, panel, and the dedicated `staff` guard

**Decision**: Add `filament/filament:"^5.0"` to `composer.json`. Create a single panel (`App\Providers\Filament\AdminPanelProvider`, panel id `admin`, path `admin`) registered in `bootstrap/providers.php`. Add a new `staff` guard/provider pair to `config/auth.php`, and set the panel's `->authGuard('staff')` explicitly so Filament never falls back to the app's `default` guard (`web`, which is attendee-scoped per feature 002).

```php
// config/auth.php — new entries only, existing 'web'/'attendees'/'users' left untouched
'guards' => [
    'web' => [...],            // unchanged (feature 002, attendee-facing)
    'staff' => [
        'driver' => 'session',
        'provider' => 'staff',
    ],
],
'providers' => [
    ...
    'staff' => [
        'driver' => 'eloquent',
        'model' => Staff::class,
    ],
],
```

**Rationale**: `config/auth.php` already carries a comment on the `users` provider anticipating this: "a future Filament/staff-auth feature will repurpose or replace it for staff auth" — but `users`/`User::class` is Laravel's default scaffold table, unrelated to this project's actual `staff` table (feature 001). Reusing it would conflate a vestigial scaffold model with the real `Staff` model. A dedicated `staff` guard/provider pointed at the existing `Staff` model (already `Authenticatable`, already has hashed `password`, already soft-deletable) is the smallest change that satisfies spec.md FR-001's "authenticated separately from public attendee accounts."

**Alternatives considered**: Repurposing the `web` guard for staff too, differentiated only by route middleware (rejected — spec.md FR-001 explicitly requires staff identity never be confused with attendee identity; sharing a guard risks exactly that if any future code reads `Auth::user()` without specifying a guard). Repurposing `users`/`User::class` as the staff model (rejected — `User` is an unused Laravel scaffold artifact with no `role` column or relation to this domain; migrating its data/shape is unjustified churn for a table nothing currently depends on).

## 2. `EventStatus` enum must be realigned to the spec's four statuses

**Decision**: Change `App\Enums\EventStatus`'s cases from `Draft, Published, SoldOut, Completed, Cancelled` (feature 001) to `Draft, Published, Closed, Archived` (spec.md FR-006, matching the original request's literal `draft/published/closed/archived`).

**Rationale**: Reading the installed enum (feature 001) shows it does not match this feature's explicit, user-specified status list at all — `SoldOut`/`Completed`/`Cancelled` have no equivalent in spec.md, and `Closed`/`Archived` don't exist yet. The `events.status` column is a plain `VARCHAR(20)` with no DB-level CHECK constraint (unlike `ticket_types.available_quantity`), so this is a pure PHP-enum change, not a migration. Blast radius is minimal: only `EventFactory::definition()` references a case (`EventStatus::Published`, which survives unchanged); no other application code, test, or migration references the removed cases.

**Pre-existing-row safety check**: this is pre-launch development — features 001/002 shipped no seeder or code path that ever persists an `events` row with `status = sold_out|completed|cancelled` (the only place any status is written is `EventFactory`, corrected above, and this feature's own seeder, which writes none of the removed values). There is therefore no production or seed data an enum-case rename could orphan. As a defensive measure anyway (cheap insurance against a manually-inserted row from ad-hoc local testing), this feature's `events` migration includes a guard statement executed before the application starts reading `status` through the new enum: `UPDATE events SET status = 'closed' WHERE status IN ('completed', 'cancelled'); UPDATE events SET status = 'archived' WHERE status = 'sold_out';` — chosen because "closed" (sales/visibility ended) is the closer semantic match for a completed or cancelled event than "archived" (long-term inactive), and "sold_out" (no longer transactable) maps closer to "archived". This mapping only ever fires against rows that shouldn't exist; it costs one migration statement to remove the risk entirely rather than assume it away.

**Alternatives considered**: Keeping both sets of cases (7 total) so old and new meanings coexist (rejected — spec.md's clarified FR-006 is explicit and authoritative about exactly four statuses with unrestricted transitions; a 7-value enum would let staff pick a status this feature's UI was never designed to explain, and nothing in the codebase depends on the old three values). Mapping `closed`→`completed` and `archived`→`cancelled` as aliases (rejected — different semantics: "cancelled" implies the event never happened, "closed" per this spec just means sales/visibility ended; conflating them would misrepresent event history).

## 3. Event date/time: add time-of-day precision to `start_date`, keep `end_date` derived

**Decision** (resolved by spec.md's Clarification #1): Change `events.start_date` from `DATE` to `DATETIME`. The EventResource form exposes **one** field ("date/time") bound to `start_date`. `end_date` remains a `DATE` column, is **not** exposed on the form, and is dehydrated automatically to `start_date`'s calendar date on every save. The existing CHECK constraint (`end_date >= start_date AND end_date <= DATE_ADD(start_date, INTERVAL 1 DAY)`, feature 001) is updated to compare against `DATE(start_date)` so it continues to hold trivially once `end_date` always equals that same date.

**Rationale**: spec.md's own Key Entities/FR-006 describe a single "date and start time" field, not a staff-facing date range — the existing `end_date` column exists for feature 001's own (out-of-scope-here) duration bookkeeping, and this feature must not break its CHECK constraint. Auto-deriving `end_date = DATE(start_date)` is the minimal change that satisfies both: the constraint keeps working unmodified in spirit, and staff never have to think about a field the request never asked for.

**Alternatives considered**: Also exposing `end_date`/end-time as a second form field (rejected by the clarification — the request and spec.md both describe a single date/time field). Leaving `start_date` as `DATE` and treating "date/time" as date-only (rejected by the clarification in favor of true time-of-day precision).

## 4. Ticket type price: keep `DECIMAL(10,2)`, no integer-cents migration

**Decision**: `ticket_types.price` stays `DECIMAL(10,2)` (feature 001, unchanged). The Filament price form field accepts and displays a plain MZN decimal amount; no `dehydrateStateUsing` cents conversion is applied, because none is needed.

**Rationale**: The original request's literal instruction ("stored/dehydrated as integer cents") describes a common pattern for avoiding floating-point rounding — but MySQL's `DECIMAL` is already exact fixed-point storage, not floating-point, so spec.md FR-014's actual requirement ("no floating-point rounding or precision loss") is already satisfied by the schema feature 001 shipped. Introducing a parallel `price_cents INT` column (or converting the existing column) would be schema churn with no correctness benefit, and would create an inconsistency with `order_items.unit_price`/`subtotal` (also `DECIMAL(10,2)`, feature 001) that this feature has no reason to touch. spec.md's own Assumptions section left this storage-representation choice to planning for exactly this reason.

**Alternatives considered**: Migrating `price` to an integer-cents column (rejected — see above: no correctness gain, and it would desynchronize `TicketType.price`'s unit from `OrderItem.unit_price`/`subtotal`'s unit, which must stay comparable since `OrderItem.unit_price` is a point-in-time copy of `TicketType.price`).

## 5. Deriving an order's "event" — `orders` has no `event_id` column

**Decision**: Add a plain (non-Eloquent-relation) accessor method, `Order::event(): ?Event`, computed as `$this->orderItems->first()?->ticketType?->event`. The OrderResource table/infolist "event" column uses this accessor via a computed column, not a direct relationship join.

**Rationale**: Reading feature 001's `orders` migration/data-model confirms `orders` has no `event_id` — an order's association to an event only exists transitively through `order_items.ticket_type_id → ticket_types.event_id`. Nothing in feature 001 or the constitution's 3-step checkout flow suggests an order can legitimately span multiple events (checkout is inherently scoped to one event's ticket types at a time), so "the event of an order's first item" is a safe, accurate stand-in for "the order's event" without requiring a schema change this feature has no mandate to make.

**Alternatives considered**: Adding an `event_id` column to `orders` (rejected — a real schema change to a table feature 001 already shipped and feature 002 already builds on, for a feature whose own spec.md describes orders as strictly read-only; out of proportion). Joining/aggregating across all of an order's items and showing every distinct event (rejected — over-engineering for a case the existing checkout flow doesn't produce).

## 6. Ticket type deletion is not exposed by this feature, to any role

**Decision**: `TicketTypeResource` registers only List/Create/Edit/View pages — no delete action, for any role, including super admin.

**Rationale**: spec.md's FR-011 defines only create/edit for ticket types, and the spec's own Assumptions section states ticket types "follow the narrower create/edit... rules stated explicitly above" (deliberately narrower than events' full CRUD). FR-004's "super admin: full access, including delete" reads, in context, as clarifying the one explicit delete carve-out the spec does define — event deletion (FR-010) — not as silently granting a second, never-described delete capability over ticket types. Since a ticket type may already have sold tickets (`order_items` reference it via `ON DELETE RESTRICT`), granting delete without a spec'd behavior for that case would be a real data-integrity risk the spec never asked this feature to solve.

**Alternatives considered**: Granting super admin ticket-type delete since FR-004 says "full access" (rejected — no acceptance scenario, edge case, or FR anywhere in spec.md describes what should happen to a ticket type's already-sold tickets/order_items on delete; inventing that behavior now would be scope creep past what was specified). Soft-deleting only unsold ticket types (rejected — same reasoning: not requested, and `TicketType` already has `SoftDeletes` available for a future feature to use deliberately).

## 7. `StaffRole` backed enum, and a real bug found in `StaffFactory`

**Decision**: Add `App\Enums\StaffRole: string` with cases `SuperAdmin = 'super_admin'`, `EventManager = 'event_manager'`, `Support = 'support'`, `GateOperator = 'gate_operator'` — the four values named explicitly in spec.md FR-004. Cast `Staff::role` to `StaffRole::class`. Correct `StaffFactory::definition()`, which currently generates `fake()->randomElement(['event_manager', 'support_agent', 'gate_staff'])` — two of those three strings (`support_agent`, `gate_staff`) don't match any role this or any other feature defines, and `super_admin` is missing entirely from the pool. Add explicit factory states (`superAdmin()`, `eventManager()`, `support()`, `gateOperator()`) mirroring the existing `soldOut()`/`offline()`/`refunded()` pattern in sibling factories, for use in this feature's policy tests.

**Rationale**: A factory-generated `Staff` with `role = 'support_agent'` would match none of this feature's policy checks (which must test against the spec's literal `'support'`) — silently producing a staff member with *zero* access anywhere, the same as an unrecognized role, rather than the intended support-role access. This is exactly the kind of pre-existing, not-yet-exercised gap research.md exists to surface (cf. feature 002 research.md §2's `sessions.user_id` discovery) — until this feature adds real policy checks against `role`, nothing had ever read that column meaningfully.

**Alternatives considered**: Leaving `StaffFactory` as-is and only fixing it inside this feature's own tests via `->state(['role' => 'support'])` overrides (rejected — leaves a factory default that silently produces an inaccessible staff account for every *other* future feature/test that calls `Staff::factory()->create()` without a role override, a landmine worth fixing at the source now that it's been found). A staff member with a `null`/unrecognized role is treated as having the same (zero) access as `gate_operator` to Events/TicketTypes/Orders — not a special error state — consistent with a default-deny security posture and requiring no new spec behavior.

## 8. Dashboard stats: a single `StatsOverviewWidget`, visible only to order-viewing roles

**Decision**: `App\Filament\Widgets\OrderStatsOverview extends Filament\Widgets\StatsOverviewWidget`, registered on the panel's default Dashboard page. Its `getStats()` runs three/four aggregate queries against `Order` (`count()`, `where('status', OrderStatus::Paid)->count()`, `where('status', OrderStatus::Paid)->sum('total_amount')`, `where('status', OrderStatus::Pending)->count()`). `canView()` returns true only when the authenticated staff member's role is `super_admin`, `event_manager`, or `support` (spec.md FR-019's "any role permitted to view orders").

**Rationale**: Filament's built-in `StatsOverviewWidget` is the standard, first-party primitive for exactly this "a few aggregate numbers at the top of the dashboard" pattern — no custom Livewire component or package is needed. Per-role visibility via `canView()` keeps `gate_operator` (and any unrecognized role, per §7) from seeing order figures at all, satisfying spec.md SC-006 ("staff never see a navigation entry or action control for a resource their role cannot use") for the dashboard specifically.

**Alternatives considered**: A dedicated `/admin/stats` page instead of a dashboard widget (rejected — spec.md's User Story 5 explicitly describes staff landing on "the dashboard" and immediately seeing these figures, not navigating to a separate page).

## 9. Brand colors and Barlow font wired via Filament's panel configuration, no custom theme build

**Decision**: In the panel provider: `->colors(['primary' => Color::hex('#3C0D5F'), 'info' => Color::hex('#F2A801'), 'danger' => Color::hex('#FF3502')])` and `->font('Barlow')` (Filament's built-in Google-Fonts-backed font loader, matching the constitution's stated Barlow-for-UI-text pattern already used on the public site).

**Rationale**: Filament's panel builder has offered exactly this `->colors()`/`->font()` configuration surface across its major versions for this exact purpose (palette + typography without hand-rolling a custom CSS theme or Tailwind build), and the constitution's Design Tokens section already establishes these three hex values and Barlow as this project's canonical tokens — this feature applies them, it doesn't invent them. The precise Filament 5 API surface will be confirmed against the installed package's source during implementation (a mechanical verification step, not a design decision); no fallback plan is needed since `->colors()`/`->font()` (or their direct equivalents) are core to how every Filament panel has been themed to date.

**Alternatives considered**: A custom compiled Filament theme (`resources/css/filament/admin/theme.css` + a dedicated Vite entry) (rejected — the constitution's Principle VI (shared-hosting simplicity) and Filament's own panel-config API make this unnecessary for three token colors and one font family; reserve a custom theme build for a future need this feature doesn't have).

---

**Output**: All decisions above resolve directly into `data-model.md` (new/changed columns, the `StaffRole` enum, the `EventStatus` realignment) and the Filament resource/policy/widget structure in `plan.md`'s Project Structure. This feature exposes no external API — see `plan.md`'s Project Structure section for why `contracts/` is skipped, matching feature 001's precedent. No open questions remain for Phase 1.
