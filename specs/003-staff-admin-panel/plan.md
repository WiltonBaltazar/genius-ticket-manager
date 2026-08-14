# Implementation Plan: Staff Admin Panel (Events, Ticket Types & Orders)

**Branch**: `003-staff-admin-panel` (working on `main`; no feature-specific git branch was created — no branch-creation hook configured) | **Date**: 2026-08-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-staff-admin-panel/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Build the Filament 5 `/admin` panel — the constitution's designated staff tool — under a new, dedicated `staff` guard kept fully independent from the attendee-facing `web` guard (feature 002). Ship three resources (`EventResource`, `TicketTypeResource`, read-mostly `OrderResource`), a role-gated dashboard stats widget, four `StaffRole`-driven policies (`super_admin`, `event_manager`, `support`, `gate_operator`), the brand color/font panel theme, two seeded staff accounts, and the Pest feature tests spec.md calls out explicitly. Two real schema/enum mismatches between the original request and the already-shipped feature 001 schema were discovered and resolved during research (see `research.md` §2–§4): `EventStatus`'s cases didn't match the requested four statuses, and `events.start_date` was date-only where the request needs time-of-day precision.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13). No new frontend framework — Filament 5 is server-rendered Livewire 3 (already part of this project's stack per the constitution), distinct from the public site's React 19/TanStack Router stack (feature 002).

**Primary Dependencies**: `filament/filament:"^5.0"` (new Composer dependency — brings Livewire 3 transitively); Pest + `pestphp/pest-plugin-laravel` (existing, used for every feature test in this plan).

**Storage**: MySQL 8.0+, InnoDB (existing). Adds columns to `events` (`slug`, `hero_image_path`, `internal_notes`, and a `DATE`→`DATETIME` type change on `start_date`) and to `ticket_types` (`sales_start_date`, `sales_end_date`) via additive/altering migrations — not rewrites of feature 001's original migrations (see `data-model.md`). No new tables.

**Testing**: Pest feature tests per constitution Principle III's general Testing Gates (this feature isn't itself in the principle's "booking-critical path" enumeration, but its own spec.md explicitly names four required tests, and the constitution separately requires "policy tests for every Filament authorization rule"). One test class per resource/concern (staff auth/redirect, Event CRUD + policy matrix, TicketType total_quantity lock + policy matrix, Order read-only + policy matrix, dashboard widget visibility/accuracy), run against the same MySQL test database (`genius_ticket_manager_test`) feature 001/002 already established, inside `DatabaseTransactions`.

**Target Platform**: Shared hosting (PHP-FPM). Filament serves `/admin` as server-rendered Livewire over standard HTTP requests — no separate JS SPA bundle, no new build target beyond Filament's own (small) compiled CSS/JS assets, consistent with constitution Principle VI.

**Project Type**: Web application — single Laravel monolith (this feature adds `app/Filament/*`, `app/Policies/*`, one new enum, two migrations, a seeder addition; no changes to the existing `resources/js` React app).

**Performance Goals**: No new numeric latency targets stated in spec.md; standard Filament list/table/dashboard responsiveness (server-rendered Livewire partial updates) is the baseline, matching how the constitution already describes the admin panel's expected feel (Principle V: "fast, predictable admin tool, not marketing polish").

**Constraints**: No custom-built Filament theme/CSS pipeline — brand colors and Barlow are applied via Filament's panel-level `->colors()`/`->font()` configuration (research.md §9). No GSAP/Lenis in the admin panel (constitution Principle V — public-site only; admin uses native Livewire transitions only). `audit_logs` writes stay scoped to payment/order state changes, per spec.md's Clarifications — this feature does not write new audit rows for Event/TicketType CRUD. No delete action is exposed for Ticket Types, for any role (research.md §6).

**Scale/Scope**: Same low-thousands scale envelope as features 001/002 — 3 Filament resources, 1 dashboard widget, 3 policies, 1 new enum (`StaffRole`), 2 additive/altering migrations, 1 seeder addition, 1 factory correction (research.md §7).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Applies to this feature? | Status |
|---|---|---|
| I. SOLID Architecture & Clean Code | Yes | ✅ PASS: Filament Resources/Policies/Widgets are each single-responsibility by construction (one resource per model, one policy per model). The only two pieces of real "logic" this feature adds — the `total_quantity` read-only gate and `Order::event()`'s derived-accessor lookup — are single-expression rules, not multi-step processes, so keeping them directly on the Resource/Model (rather than extracting an Action/Service class) does not violate Principle I's intent; there is no mockable external dependency or multi-branch business process here to justify an interface/DI seam. `Staff` role checks are centralized in three Policy classes rather than scattered `if` checks across controllers/views (Filament resources have no controllers to bloat). |
| II. Security by Design | Yes, fully | ✅ PASS (by design): every resource/action is gated by a Policy class evaluated on the dedicated `staff` guard (spec.md FR-004/FR-005); unauthenticated requests are redirected by Filament's own auth middleware (FR-002); the panel sits behind authentication per the constitution's explicit "Filament admin panel MUST sit behind authentication and policy-based authorization for every resource and action." No new mutating HTTP surface is exposed beyond Filament's own CSRF-protected Livewire request cycle (already covered by the `web` middleware group's `VerifyCsrfToken`). |
| III. Test-First for Booking-Critical Paths | Partially — this feature isn't itself in the principle's explicit booking-critical enumeration (ticket selection/cart/checkout/payment/webhook/refunds/inventory-locking/check-in), but the constitution's general Testing Gates section and spec.md's own explicit test list still apply in full | ✅ PASS: Pest feature tests written for every acceptance scenario in spec.md, plus the full role × resource policy matrix in `data-model.md`, before/alongside the corresponding Filament classes per `tasks.md`'s ordering |
| IV. Data Integrity & Immutable Audit Trail | Yes | ✅ PASS: additive/altering migrations only (no drop/recreate of `events`/`ticket_types`); existing `available_quantity <= total_quantity` CHECK and FK `ON DELETE RESTRICT` constraints untouched; `audit_logs` stays append-only and payment-scoped exactly as feature 001 built it — this feature deliberately does not add new write paths to it (spec.md Clarifications, research.md) |
| V. Accessible, On-Brand Experience | Yes | ✅ PASS (by design): brand palette + Barlow applied via Filament's panel theme (research.md §9); native Livewire transitions only, no GSAP/Lenis (constitution's explicit admin-panel carve-out); WCAG 2.1 AA is inherited from Filament's own accessible-by-default component library (ARIA roles, keyboard navigation, focus management) rather than hand-rolled markup — spot-checked manually per `quickstart.md`, since no automated a11y tool is part of this project's toolchain yet |
| VI. Shared-Hosting-Compatible Simplicity | Yes | ✅ PASS: Filament 5 requires no Redis, no queue workers, no Docker — it's a Composer package rendering through the same PHP-FPM process as the rest of the app, exactly the technology the constitution already names for this purpose |

No unjustified violations. Complexity Tracking table below is intentionally empty.

**Post-Phase-1 re-check**: `data-model.md` and `research.md` were reviewed against this table after Phase 1 design. Two design choices are worth surfacing explicitly rather than treating as automatic:
- Not exposing a Ticket Type delete action for any role, including `super_admin` (research.md §6), is a deliberate narrower-than-literal reading of FR-004's "full access, including delete" — justified by the absence of any spec'd behavior for already-sold-ticket-type deletion, not a missed requirement.
- Correcting `StaffFactory`'s role pool (research.md §7) touches a file feature 001 already shipped. This is treated as a pre-existing bug fix this feature's own tests would otherwise silently mask, not as scope creep — it has zero effect on any already-passing test, since no prior feature asserted on specific role string values.

Gate remains PASS; no Complexity Tracking entries needed.

## Project Structure

### Documentation (this feature)

```text
specs/003-staff-admin-panel/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md         # Phase 1 output (/speckit-plan command)
├── quickstart.md         # Phase 1 output (/speckit-plan command)
├── checklists/
│   └── requirements.md
└── tasks.md              # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

No `contracts/` directory: this feature exposes no external HTTP/JSON API surface (Filament resources are server-rendered Livewire UI, not endpoints consumed by another system or the React frontend) — matching feature 001's precedent, not feature 002's (which genuinely added `POST /register` etc. for the React SPA to call).

### Source Code (repository root)

Same single Laravel 13 monolith as features 001/002; this feature adds the project's first Filament panel/resources/policies alongside the existing `app/Actions/Auth`, `app/Http/Controllers/Auth` (feature 002) and `resources/js` React app (untouched by this feature).

```text
app/
├── Enums/
│   └── StaffRole.php                          # NEW: super_admin | event_manager | support | gate_operator
├── Models/
│   ├── Staff.php                              # MODIFIED: cast `role` to StaffRole::class
│   ├── Event.php                               # MODIFIED: fillable gains slug/hero_image_path/internal_notes;
│   │                                              start_date cast stays 'date' → 'datetime'; end_date
│   │                                              auto-dehydration on save (booted() hook, mirrors TicketType's
│   │                                              existing version-increment pattern)
│   ├── TicketType.php                          # MODIFIED: fillable gains sales_start_date/sales_end_date
│   └── Order.php                               # MODIFIED: adds event(): ?Event accessor (research.md §5)
├── Policies/
│   ├── EventPolicy.php                         # NEW
│   ├── TicketTypePolicy.php                    # NEW
│   └── OrderPolicy.php                         # NEW
├── Filament/
│   ├── Resources/
│   │   ├── Events/
│   │   │   ├── EventResource.php               # form: name, slug, location(venue), date/time(start_date),
│   │   │   │                                      hero image, rich-text description, status select,
│   │   │   │                                      internal notes | table: name, location, date/time,
│   │   │   │                                      status badge, created date | status filter
│   │   │   └── Pages/
│   │   │       ├── ListEvents.php
│   │   │       ├── CreateEvent.php
│   │   │       ├── EditEvent.php
│   │   │       └── ViewEvent.php
│   │   ├── TicketTypes/
│   │   │   ├── TicketTypeResource.php          # navigationGroup('Events'); form: event select, name,
│   │   │   │                                      description, price (MZN), total_quantity (locked once
│   │   │   │                                      selling), available_quantity (always read-only),
│   │   │   │                                      sales_start_date, sales_end_date | no delete action
│   │   │   └── Pages/
│   │   │       ├── ListTicketTypes.php
│   │   │       ├── CreateTicketType.php
│   │   │       └── EditTicketType.php          # no DeleteAction registered (research.md §6)
│   │   └── Orders/
│   │       ├── OrderResource.php               # infolist only: id, attendee, email, event(), status badge,
│   │       │                                      total (MZN), payment method, created date, + RepeatableEntry
│   │       │                                      of order items (ticket type, quantity, unit price)
│   │       └── Pages/
│   │           ├── ListOrders.php
│   │           └── ViewOrder.php               # no Create/Edit pages registered (spec.md FR-016)
│   └── Widgets/
│       └── OrderStatsOverview.php              # StatsOverviewWidget; canView() gated to
│                                                  super_admin|event_manager|support (research.md §8)
└── Providers/
    └── Filament/
        └── AdminPanelProvider.php              # NEW: panel id 'admin', path 'admin', ->authGuard('staff'),
                                                    ->colors([...]), ->font('Barlow'), resource/widget discovery

bootstrap/
└── providers.php                               # MODIFIED: registers AdminPanelProvider

config/
└── auth.php                                    # MODIFIED: new 'staff' guard + 'staff' provider
                                                    (config/auth.php's existing 'users' provider comment,
                                                    which anticipated this feature, is superseded — see
                                                    research.md §1 for why a dedicated provider was used instead)

database/
├── migrations/
│   ├── xxxx_add_admin_fields_to_events_table.php        # slug (unique), hero_image_path, internal_notes,
│   │                                                        start_date DATE→DATETIME, CHECK constraint update,
│   │                                                        defensive status-value backfill (research.md §2)
│   └── xxxx_add_sales_window_to_ticket_types_table.php  # sales_start_date, sales_end_date (nullable)
├── factories/
│   ├── StaffFactory.php                        # MODIFIED: role pool corrected to the 4 canonical StaffRole
│   │                                              values; adds superAdmin()/eventManager()/support()/
│   │                                              gateOperator() states (research.md §7)
│   ├── EventFactory.php                        # MODIFIED: adds slug, start_date now datetime format,
│   │                                              status uses realigned EventStatus cases
│   └── TicketTypeFactory.php                    # MODIFIED: adds sales_start_date/sales_end_date
└── seeders/
    └── DatabaseSeeder.php                       # MODIFIED: seeds the two staff accounts (spec.md FR-020)

app/Enums/
└── EventStatus.php                             # MODIFIED: cases realigned to Draft/Published/Closed/Archived
                                                    (research.md §2) — breaking change to feature 001's enum,
                                                    justified there

tests/
└── Feature/Filament/
    ├── StaffAuthenticationTest.php             # unauthenticated redirect (/admin → /admin/login), login flow
    ├── EventResourcePolicyTest.php             # event_manager creates; support/gate_operator refused; super
    │                                              admin-only delete
    ├── TicketTypeResourcePolicyTest.php         # total_quantity lock; role matrix; no delete action anywhere
    ├── OrderResourcePolicyTest.php              # read-only for all permitted roles; gate_operator refused
    └── DashboardStatsWidgetTest.php             # figures match underlying Order aggregates; hidden from
                                                     gate_operator
```

**Structure Decision**: Same single Laravel application as features 001/002, now gaining its first Filament panel. `TicketTypeResource` is a standalone resource (own navigation entry) grouped under the same "Events" navigation group as `EventResource` — satisfying spec.md's "nested under events" via navigation grouping and the explicit event-select form field, without introducing Filament's separate nested-resource-routing mechanism, which nothing in spec.md's acceptance scenarios requires. `OrderResource` registers no Create/Edit pages at all, rather than registering them and disabling every field — the smaller, more honest way to express "this resource is not writable through this UI" (spec.md FR-016). Business logic stays minimal and colocated (Constitution Check row I) since nothing here rises to the complexity that would justify a dedicated Action/Service class.

## Complexity Tracking

> No Constitution Check violations were identified — this table is intentionally left empty.
