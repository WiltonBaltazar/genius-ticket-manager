---

description: "Task list for Staff Admin Panel (Events, Ticket Types & Orders)"
---

# Tasks: Staff Admin Panel (Events, Ticket Types & Orders)

**Input**: Design documents from `/specs/003-staff-admin-panel/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, quickstart.md

**Tests**: Included and REQUIRED — spec.md explicitly enumerates four required tests (staff login redirect when unauthenticated, event_manager can create an event, support role cannot create an event, unauthenticated request to `/admin` redirects to `/admin/login`), and the constitution separately mandates "policy tests for every Filament authorization rule." Every test task is written and failing before its corresponding implementation task.

**Organization**: Tasks are grouped by user story (from spec.md) so each story is an independently testable increment.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no ordering dependency on an incomplete task)
- **[Story]**: Which user story this task belongs to (US1–US5); Setup/Foundational/Polish tasks carry no story label
- File paths are exact and relative to the repository root

## Path Conventions

Single Laravel 13 monolith (per plan.md): Filament panel/resources/policies under `app/Filament/`, `app/Policies/`, `app/Providers/Filament/`; schema under `database/migrations/`, `database/factories/`, `database/seeders/`; tests under `tests/Feature/Filament/`. No changes to `resources/js` (the React public site, features 001/002) — this feature is entirely server-rendered Filament/Livewire.

---

## Phase 1: Setup

**Purpose**: Install Filament 5 and wire the dedicated `staff` guard/panel shell this whole feature runs on

- [X] T001 Run `composer require filament/filament:"^5.0"` and `php artisan filament:install --panels` (creates `app/Providers/Filament/AdminPanelProvider.php`, registers it in `bootstrap/providers.php`, publishes Filament's config/assets); run `npm run build` so the panel's compiled assets exist (research.md §1)
- [X] T002 [P] Configure `config/auth.php` — add a `staff` guard (`driver: session`, `provider: staff`) and a `staff` provider (`driver: eloquent`, `model: Staff::class`), leaving the existing `web`/`attendees`/`users` guards/providers untouched (research.md §1)
- [X] T003 Configure `app/Providers/Filament/AdminPanelProvider.php` — panel id `admin`, path `admin`, `->authGuard('staff')`, `->login()`, `->colors(['primary' => Color::hex('#3C0D5F'), 'info' => Color::hex('#F2A801'), 'danger' => Color::hex('#FF3502')])`, `->font('Barlow')`, resource/page/widget auto-discovery pointed at `app/Filament/{Resources,Pages,Widgets}` (research.md §1, §9; depends on T001, T002) — discovered during implementation: `Filament\Http\Middleware\Authenticate` requires the guard's user model to implement `FilamentUser::canAccessPanel()` or panel access 403s outside `local` env (Pest runs under `testing`); folded into T008 (Staff model)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema changes, enums, model changes, policies, and seed data every user story depends on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T004 [P] Create `app/Enums/StaffRole.php` — backed string enum: `SuperAdmin = 'super_admin'`, `EventManager = 'event_manager'`, `Support = 'support'`, `GateOperator = 'gate_operator'` (research.md §7, data-model.md §staff)
- [X] T005 [P] Modify `app/Enums/EventStatus.php` — realign cases from `Draft, Published, SoldOut, Completed, Cancelled` to `Draft, Published, Closed, Archived` (research.md §2)
- [X] T006 [P] Create migration `database/migrations/xxxx_add_admin_fields_to_events_table.php` — add `slug` VARCHAR(255) NOT NULL with a unique index, nullable `hero_image_path`, nullable `internal_notes` TEXT; change `start_date` from `DATE` to `DATETIME`; replace the `events_duration_check` CHECK constraint to compare `end_date` against `DATE(start_date)`; include the defensive backfill statements mapping any pre-existing `sold_out`/`completed`/`cancelled` status values to `archived`/`closed`/`closed` respectively before the app starts reading `status` through the realigned enum (data-model.md §events, research.md §2 pre-existing-row safety check, §3)
- [X] T007 [P] Create migration `database/migrations/xxxx_add_sales_window_to_ticket_types_table.php` — add nullable `sales_start_date` DATETIME and nullable `sales_end_date` DATETIME to `ticket_types` (data-model.md §ticket_types)
- [X] T008 Modify `app/Models/Staff.php` — cast `role` to `StaffRole::class` (depends on T004). Implemented via a custom `App\Casts\StaffRoleCast` (`tryFrom()`), not Laravel's built-in enum cast: the built-in cast throws `ValueError` for any non-null column value that doesn't match a case, which would crash rather than degrade to "no access" for FR-004's "unrecognized role" requirement. Also implements `Filament\Models\Contracts\FilamentUser::canAccessPanel()` (returns true unconditionally — resource-level gating is via Policies) — required per T003's note, since Filament 403s any user model that doesn't implement it outside `local` env.
- [X] T009 Modify `app/Models/Event.php` — add `slug`, `hero_image_path`, `internal_notes` to `$fillable`; cast `start_date` as `datetime` (data-model.md §events; depends on T006). Deliberately NOT adding a model-level `saving` hook to derive `end_date` from `start_date`: discovered during implementation that doing so at the model layer breaks feature 001's already-shipped multi-day event support (`tests/Feature/Schema/EventDurationBoundsTest.php`, `EventMultiDayLookupTest.php` directly set a 2-day `end_date` via `Event::factory()->create()` and assert the CHECK constraint's >1-day boundary) — the `end_date = DATE(start_date)` derivation belongs to the Filament form layer only (T023), not to every caller of `Event::save()`
- [X] T010 [P] Modify `app/Models/TicketType.php` — add `sales_start_date`, `sales_end_date` to `$fillable`, cast both as `datetime` (data-model.md §ticket_types; depends on T007)
- [X] T011 [P] Modify `app/Models/Order.php` — add `event(): ?Event` method returning `$this->orderItems->first()?->ticketType?->event` (research.md §5, data-model.md §orders)
- [X] T012 Run `php artisan migrate` against dev and `--env=testing` databases; verify `Schema::getColumnType('events', 'start_date')` reports `datetime` and `events`/`ticket_types` carry the new columns (depends on T006, T007)
- [X] T013 [P] Correct `database/factories/StaffFactory.php` — replace the `['event_manager', 'support_agent', 'gate_staff']` role pool (two values match no real role) with the four canonical `StaffRole` values, and add `superAdmin()`, `eventManager()`, `support()`, `gateOperator()` states (research.md §7; depends on T004)
- [X] T014 [P] Update `database/factories/EventFactory.php` — add a unique `slug` (`Str::slug(name)` plus a uniqueness suffix), change `start_date`/`end_date` generation to the new datetime format, and use the realigned `EventStatus` cases (depends on T005, T006)
- [X] T015 [P] Update `database/factories/TicketTypeFactory.php` — add `sales_start_date`/`sales_end_date` defaults (depends on T007)
- [X] T016 [P] Create `app/Policies/EventPolicy.php` — `viewAny`/`view`/`create`/`update` true for `super_admin` and `event_manager`; `delete` true only for `super_admin`; everything false for `support`, `gate_operator`, and any unrecognized role (data-model.md Role→Resource matrix, spec.md FR-004/FR-010; depends on T004, T008)
- [X] T017 [P] Create `app/Policies/TicketTypePolicy.php` — `viewAny`/`view`/`create`/`update` true for `super_admin` and `event_manager`; `delete` always false for every role, including `super_admin` (research.md §6, spec.md FR-011); everything false for `support`, `gate_operator` (depends on T004, T008)
- [X] T018 [P] Create `app/Policies/OrderPolicy.php` — `viewAny`/`view` true for `super_admin`, `event_manager`, `support`; `create`/`update`/`delete` always false for every role (spec.md FR-016); everything false for `gate_operator` (depends on T004, T008)
- [X] T019 Modify `database/seeders/DatabaseSeeder.php` (or add a `database/seeders/StaffSeeder.php` called from it) — seed exactly one `super_admin` and one `event_manager` `Staff` row with placeholder, non-production credentials; guard the seeder with `app()->environment(['local', 'testing'])` (or an explicit `--force`-style override) so it refuses to run in production (spec.md FR-020; depends on T008, T013)

**Checkpoint**: Schema, enums, models, policies, and seed data ready — user story work can begin.

---

## Phase 3: User Story 1 - Staff Sign In and Are Gated by Role (Priority: P1) 🎯 MVP

**Goal**: Unauthenticated visitors are redirected to staff login; authenticated staff see only the nav/actions their role permits (spec.md FR-001–FR-005, SC-001, SC-002, SC-006)

**Independent Test**: `GET /admin` while signed out → redirect to `/admin/login`; sign in as `super_admin` → full nav; sign in as `gate_operator` → no Events/Ticket Types/Orders nav entries and direct URL access to each refused

### Tests for User Story 1 ⚠️ write first, must fail

- [X] T020 [P] [US1] Pest feature test `tests/Feature/Filament/StaffAuthenticationTest.php` — unauthenticated `GET /admin` redirects to `/admin/login`, not the attendee-facing `/auth/login` (FR-002, SC-001, the spec's explicit "unauthenticated request to /admin redirects to /admin/login" test); a seeded staff member can log in via `/admin/login` and lands on the dashboard, session established under the `staff` guard (FR-001, FR-003, the spec's explicit "staff login redirect when unauthenticated" test read as its positive case); `super_admin` sees Events, Ticket Types, and Orders nav entries; `gate_operator` sees none of the three and a direct `GET` to each resource's index route is refused (FR-005, SC-006); a signed-in staff member whose role is changed to `gate_operator` (or account deactivated) mid-session loses access on their very next request, with no separate logout/session-invalidation step (FR-022)

### Implementation for User Story 1

- [X] T021 [US1] Verify `app/Providers/Filament/AdminPanelProvider.php` exclusively authenticates against the `staff` guard (no fallback to `web`) and that Filament's default post-login redirect lands on the dashboard; adjust if the installed default differs (depends on T003, T016, T017, T018) — confirmed via T020's passing login test (`assertAuthenticatedAs($staff, 'staff')`); the 3 nav/role-gating assertions in T020 remain red until Phases 4-6 (Event/TicketType/Order resources) exist, per TDD — this is expected, not a regression

**Checkpoint**: At this point, staff authentication and role-based nav/action gating are fully functional and independently testable.

---

## Phase 4: User Story 2 - Event Manager Creates and Maintains Events (Priority: P1)

**Goal**: `event_manager`/`super_admin` can create, list, filter, and edit events; only `super_admin` can delete; `support`/`gate_operator` are refused (spec.md FR-006–FR-010, SC-002, SC-003)

**Independent Test**: Create an event end-to-end as `event_manager` and see it in the filtered list; confirm `support` gets refused on the same create action

### Tests for User Story 2 ⚠️ write first, must fail

- [X] T022 [P] [US2] Pest feature test `tests/Feature/Filament/EventResourcePolicyTest.php` — `event_manager` creates an event with name, unique slug, location, date/time, hero image, description, status, and internal notes, and it appears in the events list (FR-006, FR-008, User Story 2 AS1, the spec's explicit "event_manager can create an event" test); an event saves successfully with no hero image and no description (FR-006 optionality); duplicate slug rejected (FR-007, AS5); an oversized/unsupported hero image upload is rejected with an inline validation error and the event is not saved (FR-025); filtering the list by status returns only matching events (FR-009, AS2); `support` attempting to create or edit an event is refused (FR-004, AS3, the spec's explicit "support role cannot create an event" test); `event_manager` attempting to delete an event is refused, only `super_admin` can (FR-010, AS4); saving an edit to an event another staff member deleted moments earlier fails with a not-found error and the edit is discarded (FR-024)

### Implementation for User Story 2

- [X] T023 [US2] Create `app/Filament/Resources/Events/EventResource.php` — form: name, slug, location (`venue`), date/time (`start_date`), hero image upload (`nullable()`, restricted to standard web image types/size via `acceptedFileTypes()`/`maxSize()`, FR-025), rich-text description (`nullable()`), status select (draft/published/closed/archived, unrestricted transitions), internal notes; a `mutateFormDataBeforeCreate()`/`mutateFormDataBeforeSave()` hook on the Create/Edit pages sets `end_date` to `start_date`'s calendar date (Filament-form-scoped, not a model-level hook — see T009's note on why); table: name, location, date/time, status badge, created date, default-sorted by created date descending, sortable columns, standard pagination (FR-008), with a status filter; policy-driven (FR-006–FR-010, FR-025; depends on T009, T016). Scaffolded via `php artisan make:filament-resource Event --view --soft-deletes`, then stripped restore/force-delete/bulk actions and the `TrashedFilter` — not in spec.md's scope ("standard CRUD actions" means create/view/edit/delete only).
- [X] T024 [P] [US2] Create `app/Filament/Resources/Events/Pages/ListEvents.php`, `CreateEvent.php`, `EditEvent.php`, `ViewEvent.php` (depends on T023). FR-024 (stale-record edit) needed no explicit code: Livewire rehydrates the page's public `$record` model property fresh from the DB on every request and throws `ModelNotFoundException` on its own if the row is gone, before any page method runs — confirmed by test, not assumed.

**Checkpoint**: At this point, User Stories 1 AND 2 both work independently.

---

## Phase 5: User Story 3 - Event Manager Configures Ticket Types for an Event (Priority: P2)

**Goal**: `event_manager`/`super_admin` can create/edit ticket types scoped to an event; `total_quantity` locks once selling starts; `available_quantity` is always read-only; no role can delete a ticket type (spec.md FR-011–FR-013, SC-004)

**Independent Test**: Add a ticket type to an event with price/quantity/sales window; simulate a sale and confirm `total_quantity` becomes read-only

### Tests for User Story 3 ⚠️ write first, must fail

- [X] T025 [P] [US3] Pest feature test `tests/Feature/Filament/TicketTypeResourcePolicyTest.php` — `event_manager` creates a ticket type with event select, name, description, MZN price, total quantity, and a sales start/end window (FR-011, AS1); a sales end date before the sales start date is rejected with a validation error at save time (FR-026); `total_quantity` stays editable while `available_quantity === total_quantity` (AS2); once `available_quantity < total_quantity`, `total_quantity` renders read-only, re-checked against current DB state at save time so a sale recorded after the form loaded still blocks the save (FR-013, AS3, SC-004); `available_quantity` is always read-only regardless of sales state (FR-012, AS4); `support`/`gate_operator` refused view/create/edit (AS5); no delete action is registered or reachable for this resource, for any role including `super_admin` (research.md §6); saving an edit to a ticket type another staff member deleted moments earlier fails with a not-found error (FR-024)

### Implementation for User Story 3

- [X] T026 [US3] Create `app/Filament/Resources/TicketTypes/TicketTypeResource.php` — `navigationGroup('Events')`; form: event select, name, description, price (MZN, no unit conversion per research.md §4), total_quantity (`disabled()` when `available_quantity < total_quantity`), available_quantity (always `disabled()`, defaults to submitted total_quantity on create), sales_start_date, sales_end_date (validated end ≥ start via a form-level rule, FR-026); table listing; no delete action registered anywhere on the resource (FR-011–FR-013, FR-026; depends on T010, T017). No separate `mutateFormDataBeforeSave()` guard needed for the FR-013 race: confirmed by test (`TicketTypeResourcePolicyTest`'s race-condition case) that Filament re-evaluates a `disabled(Closure)` callback against the record's current, Livewire-rehydrated state on the save request itself, not just at initial page load — a sale recorded after the form opens already flips the field to non-dehydrated before `save()` runs.
- [X] T027 [P] [US3] Create `app/Filament/Resources/TicketTypes/Pages/ListTicketTypes.php`, `CreateTicketType.php`, `EditTicketType.php` (no delete page) (depends on T026)

**Checkpoint**: At this point, User Stories 1, 2, AND 3 all work independently.

---

## Phase 6: User Story 4 - Staff View Orders and Their Line Items Read-Only (Priority: P2)

**Goal**: `super_admin`/`event_manager`/`support` can view orders and their line items; nothing is editable; `gate_operator` is refused entirely (spec.md FR-015–FR-018)

**Independent Test**: Open an order as `support` and confirm every field and line item is visible but none editable; confirm `gate_operator` cannot reach the orders area

### Tests for User Story 4 ⚠️ write first, must fail

- [X] T028 [P] [US4] Pest feature test `tests/Feature/Filament/OrderResourcePolicyTest.php` — `super_admin`, `event_manager`, and `support` can each view an order's id, attendee, email, event (via the new `Order::event()` accessor), status badge, total (MZN), payment method, and created date, with none editable (FR-015, FR-016, AS1); the order's line items (ticket type, quantity, unit price) display read-only (FR-017, AS2); an order with zero order items renders its line-items list as empty rather than erroring (FR-027); `gate_operator` is refused entirely, no order data shown (FR-018, AS3); the resource exposes no create or edit route

### Implementation for User Story 4

- [X] T029 [US4] Create `app/Filament/Resources/Orders/OrderResource.php` — infolist-only display (id, attendee, `attendee.email`, `event()`, status badge, total labeled "Total (MZN)", payment method, created date) plus a `RepeatableEntry` for order items (ticket type name, quantity, unit price); no form/Create/Edit pages registered (FR-015–FR-017; depends on T011, T018)
- [X] T030 [P] [US4] Create `app/Filament/Resources/Orders/Pages/ListOrders.php`, `ViewOrder.php` (depends on T029)

**Checkpoint**: At this point, User Stories 1–4 all work independently.

---

## Phase 7: User Story 5 - Staff See At-a-Glance Sales Stats on the Dashboard (Priority: P3)

**Goal**: Staff with order-view access see total orders, paid orders, revenue (MZN, paid only), and pending orders on the dashboard; hidden from `gate_operator` (spec.md FR-019, SC-005)

**Independent Test**: Land on the dashboard as `support` and confirm the four figures match the underlying `Order` data; confirm `gate_operator` sees no stats

### Tests for User Story 5 ⚠️ write first, must fail

- [X] T031 [P] [US5] Pest feature test `tests/Feature/Filament/DashboardStatsWidgetTest.php` — the widget's total orders, paid orders, revenue (MZN, summed from paid orders only), and pending orders figures match direct `Order` aggregate queries (FR-019, AS1, AS2, SC-005); the widget does not render at all for `gate_operator` (research.md §8, SC-006)

### Implementation for User Story 5

- [X] T032 [US5] Create `app/Filament/Widgets/OrderStatsOverview.php` extending `StatsOverviewWidget` — four stat cards (total/paid/revenue/pending) computed from `Order` aggregates; `canView()` true only for `super_admin`, `event_manager`, `support` (FR-019, research.md §8; depends on T018)

**Checkpoint**: All five user stories independently functional.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Whole-feature validation and consistency with the rest of the codebase

- [ ] T033 Run the full Pest suite (`php artisan test`) — all new Filament tests pass AND all feature 001/002 tests still pass (the `events`/`ticket_types` migrations and `EventStatus`/`StaffFactory` changes must not regress them)
- [ ] T034 [P] Execute `specs/003-staff-admin-panel/quickstart.md` steps 1–9 end-to-end and record results in `specs/003-staff-admin-panel/quickstart-results.md`
- [ ] T035 [P] Accessibility spot-check across all three resources and the dashboard against WCAG 2.1 AA (Filament's component library is accessible by default per plan.md's Constitution Check row V — this step confirms nothing in this feature's custom form/table/widget configuration broke that), recording findings in `specs/003-staff-admin-panel/quickstart-results.md`
- [ ] T036 Run Pint (`vendor/bin/pint`) on all new/modified PHP files, per constitution Principle I's formatting gate
- [ ] T037 Re-validate `specs/003-staff-admin-panel/checklists/readiness.md` (already 33/33 as of the 2026-08-14 `/speckit-clarify` pass) — confirm the implementation matches each resolved item's stated requirement, especially FR-013/FR-022/FR-024/FR-025/FR-026/FR-027, and flag any drift for follow-up

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately; T003 depends on T001, T002
- **Foundational (Phase 2)**: Depends on Setup; T008–T011 depend on T004–T007 as noted per-task; T012 depends on T006, T007; T016–T018 depend on T004, T008; T019 depends on T008, T013; blocks all user stories
- **User Stories (Phases 3–7)**: All depend on Foundational; each story is otherwise independent of the others (different resource files)
- **Polish (Phase 8)**: Depends on all user stories

### User Story Dependencies

- **US1 (P1)**: Independent — the MVP (mostly validates Foundational's guard/policy wiring)
- **US2 (P1)**: Independent — own resource file
- **US3 (P2)**: Independent — own resource file (references the `events` table via a select field, not via US2's resource code)
- **US4 (P2)**: Independent — own resource file, read-only
- **US5 (P3)**: Independent — own widget file

### Within Each User Story

- Test task first, confirmed failing, before any implementation task (constitution Principle III's general testing gate + spec.md's explicit test list)
- Resource class before its Pages

### Parallel Opportunities

- T002 in parallel with T001 is not safe (T003 needs both done first) — T002 can start immediately alongside T001, they just both must finish before T003
- T004–T007, and T011 can run in parallel in Phase 2; T008–T010 each depend on one of T004/T006/T007 respectively but can run in parallel with each other once their own dependency lands; T013–T015, T016–T018 are all `[P]`
- Once Foundational completes, US1–US5 phases can proceed in parallel across developers (different files)
- All `[P]` test tasks across stories can be written in parallel once Foundational is done

---

## Parallel Example: Phase 2 (Foundational)

```bash
# Launch independent Foundational tasks together once Phase 1 is done:
Task: "Create StaffRole enum in app/Enums/StaffRole.php"
Task: "Realign EventStatus cases in app/Enums/EventStatus.php"
Task: "Create events admin-fields migration in database/migrations/xxxx_add_admin_fields_to_events_table.php"
Task: "Create ticket_types sales-window migration in database/migrations/xxxx_add_sales_window_to_ticket_types_table.php"
Task: "Add Order::event() accessor in app/Models/Order.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1 (Setup) → Phase 2 (Foundational) — this is most of the real work; US1 mainly validates it
2. Complete Phase 3 (US1) — staff login + role-gated nav
3. **STOP and VALIDATE**: unauthenticated redirect, seeded-account login, and nav gating all green
4. Demo the login/gating behavior as the MVP increment

### Incremental Delivery

1. Setup + Foundational → guard, panel shell, schema, enums, policies, seed data ready
2. US1 → staff auth + role gating (MVP)
3. US2 → event management
4. US3 → ticket type management (with the sales-lock rule)
5. US4 → read-only order visibility
6. US5 → dashboard stats
7. Polish → full-suite regression check (including features 001/002), quickstart run, a11y pass, formatting, checklist reconciliation

### Parallel Team Strategy

After Foundational: Developer A takes US2 (Events), Developer B takes US3 (Ticket Types) + US4 (Orders, both small/read-mostly), Developer C takes US1 (auth validation) + US5 (dashboard). No shared files between these stories.

---

## Notes

- [P] tasks = different files, no ordering dependency
- Every test task must fail before its implementation tasks, per constitution Principle III's general testing gate
- The two real schema/enum conflicts discovered during planning (research.md §2, §3) are resolved in Phase 2 (T005, T006), not left for a story phase to discover
- T017/T026's "no delete action for Ticket Types" and T016/T023's "delete restricted to super_admin for Events" together resolve the FR-004/FR-011 ambiguity the readiness checklist flagged (checklists/readiness.md CHK032) — already fixed at the spec level, implemented here per that fix
- FR-023 (last-write-wins on concurrent Event/Ticket Type edits) requires no implementation task — it's Filament's unmodified default save behavior; no optimistic-locking code should be added for this feature
- Commit after each task or logical group
