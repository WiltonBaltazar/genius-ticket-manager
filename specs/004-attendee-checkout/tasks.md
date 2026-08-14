---

description: "Task list for Attendee Ticket Checkout"
---

# Tasks: Attendee Ticket Checkout

**Input**: Design documents from `/specs/004-attendee-checkout/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included and REQUIRED — constitution Principle III names this feature's exact domain ("ticket selection, cart, checkout, payment... inventory locking") in its booking-critical enumeration, so every acceptance scenario needs a failing test before its implementation task, plus the mandated concurrency test for the oversell path.

**Organization**: Tasks are grouped by user story (from spec.md) so each story is an independently testable increment.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no ordering dependency on an incomplete task)
- **[Story]**: Which user story this task belongs to (US1–US5); Setup/Foundational/Polish tasks carry no story label
- File paths are exact and relative to the repository root

## Path Conventions

Single Laravel 13 monolith (per plan.md): backend under `app/{Actions,Http,Notifications,Console,Policies}/`, frontend under `resources/js/{lib,components,routes}/`, tests under `tests/Feature/Checkout/` (new) and `tests/Feature/Filament/` (existing, feature 003). Two new Composer dependencies (`barryvdh/laravel-dompdf`, `endroid/qr-code`). No new migrations — see data-model.md for why.

---

## Phase 1: Setup

**Purpose**: Install the two new Composer dependencies this feature needs

- [X] T001 Run `composer require barryvdh/laravel-dompdf endroid/qr-code` (research.md §5)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema/config/test-harness groundwork every user story depends on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 [P] Add `Expired = 'expired'` case to `app/Enums/OrderStatus.php` (data-model.md, research.md §3)
- [X] T003 [P] Add `whatsapp.number` and `bank_transfer.*` (account_name, account_number, bank_name, branch) to `config/services.php`, sourced from new `.env` keys (data-model.md)
- [X] T004 Wire `tests/Feature/Checkout/*.php` into `tests/Pest.php`'s `DatabaseTransactions` group, excluding `OrderSubmissionOversellTest.php` — that test needs two genuinely independent, uncommitted connections racing the same row, exactly like the existing `TicketTypeOversellTest.php` exclusion it mirrors (see `tests/Pest.php`'s current comment for why)
- [X] T005 [P] Add an `expired()` state to `database/factories/OrderFactory.php` (`status: Expired`, `created_at` set in the past) — needed by US3's expiry tests

**Checkpoint**: Enum, config, test harness, and factory support ready — user story work can begin.

---

## Phase 3: User Story 1 - Attendee Builds a Cart from Live Availability (Priority: P1) 🎯 MVP

**Goal**: A visitor can view a published event's ticket types with live availability and build a cart that never exceeds what's actually available (spec.md FR-001, FR-002 partial — full re-validation lands in US2)

**Independent Test**: Open a published event with limited-availability ticket types; add up to the available quantity; confirm the UI blocks a 6th add when only 5 remain and shows a sold-out type as unaddable.

### Tests for User Story 1 ⚠️ write first, must fail

- [X] T006 [P] [US1] Pest feature test `tests/Feature/Checkout/EventAvailabilityTest.php` — `GET /events/{slug}` returns the event plus each ticket type's `price`/`available_quantity` (FR-001, AS1); a ticket type with 0 remaining is still included, not omitted (contracts/checkout-api.md); a `draft`/`closed`/`archived` event returns 404, not the event's data

### Implementation for User Story 1

- [X] T007 [US1] Create `app/Http/Controllers/Checkout/EventCheckoutController.php` — `show(Event $event)`, 404 unless `status = Published`, eager-loads non-soft-deleted `ticketTypes` with currently-open sales windows (contracts/checkout-api.md). Discovered during implementation: this route is dual-purpose (SPA shell for a browser navigation vs JSON for the app's own `fetch()`, branched on `$request->expectsJson()`) — the same URL can't be page-only or API-only the way the plan first assumed, since there's no `/api` prefix in this project's routing convention. `GET /orders/{order}` (T025) needs the same treatment.
- [X] T008 [US1] Register `GET /events/{event:slug}` in `routes/web.php`, route-model-bound by `slug` (depends on T007)
- [X] T009 [P] [US1] Create `resources/js/lib/cart.ts` — client-side cart state (context + `localStorage` persistence, research.md §9), add/remove/update-quantity operations that refuse to exceed a ticket type's known `available_quantity`
- [X] T010 [P] [US1] Create `resources/js/components/checkout/TicketTypeSelector.tsx` — renders price/availability per ticket type, disables adding beyond `available_quantity`, shows a sold-out state for `available_quantity = 0` (AS2, AS3)
- [X] T011 [US1] Create `resources/js/routes/events/$slug.tsx` — event page fetching `GET /events/{slug}`, wiring `TicketTypeSelector` to the cart from T009 (depends on T008, T009, T010)

**Checkpoint**: User Story 1 is fully functional and independently testable — a visitor can browse an event and build a valid cart.

---

## Phase 4: User Story 2 - Attendee Submits Order with Their Details (Priority: P1)

**Goal**: A visitor (logged-in or guest) reviews their cart and submits it, creating a real, inventory-decrementing pending order (spec.md FR-002 full re-validation, FR-003–FR-006)

**Independent Test**: As both a logged-in attendee and a guest, submit a valid cart; confirm a pending order and its order items exist with correct totals, the relevant `available_quantity`s decreased, and a duplicate submission (same idempotency key) never creates a second order.

### Tests for User Story 2 ⚠️ write first, must fail

- [X] T012 [P] [US2] Pest feature test `tests/Feature/Checkout/OrderSubmissionTest.php` — logged-in attendee's order attaches to their account (AS1); guest submission with no password/verification creates/reuses an `Attendee` by email (AS2, AS3, FR-004); review totals match submitted line items (AS4); a line item exceeding current `available_quantity` at submission time is rejected with a 422 identifying which item, and creates nothing (FR-002's re-validation, distinct from US1's client-side check)
- [X] T013 [P] [US2] Pest feature test `tests/Feature/Checkout/OrderSubmissionIdempotencyTest.php` — resubmitting the same `transaction_hash` returns the original order (200, not 201) and never creates a second order or double-decrements inventory (AS6, research.md §1)
- [X] T014 [P] [US2] Pest feature test `tests/Feature/Checkout/OrderSubmissionOversellTest.php` (excluded from `DatabaseTransactions` per T004) — two simultaneous submissions racing the same ticket type with 1 remaining: exactly one order is created, the other's request fails cleanly, mirroring `TicketTypeOversellTest`'s two-connection pattern (research.md §2, constitution Principle III's concurrency-test mandate)

### Implementation for User Story 2

- [X] T015 [US2] Create `app/Http/Requests/Checkout/SubmitOrderRequest.php` — validates `transaction_hash`, `event_id`, `items[].{ticket_type_id,quantity}`, `name`/`email` required unless an attendee session exists (contracts/checkout-api.md)
- [X] T016 [US2] Create `app/Actions/Checkout/SubmitOrderAction.php` — attendee find-or-create by email (FR-004); within one DB transaction, per line item runs the conditional `UPDATE ... WHERE version = ? AND available_quantity >= ?` decrement (research.md §2); rolls back and returns which item(s) fell short on any shortfall; on a duplicate `transaction_hash`, returns the existing order instead of inserting; writes one `AuditLog` row against the order (`staff_id` null, research.md §8a) (depends on T002, T015). Implementation note: rolling back the transaction on a shortfall requires *throwing* inside the `DB::transaction()` closure, not calling `DB::rollBack()` directly — the latter double-manages the transaction and breaks the wrapper's own commit/rollback bookkeeping. Added a small `InsufficientAvailabilityException` for this. Also fixed an initial draft that used `lockForUpdate()` — that's pessimistic locking, which research.md §2 explicitly chose *not* to use in favor of the optimistic version-column check alone.
- [X] T017 [US2] Create `app/Notifications/Orders/OrderStatusLink.php` — plain (not `ShouldQueue`) notification emailing the order-status page link, sent synchronously per plan.md's Constraints (FR-010a)
- [X] T018 [US2] Create `app/Http/Controllers/Checkout/OrderController.php` with `store(SubmitOrderRequest, SubmitOrderAction)` — `POST /checkout`, delegates to the action, sends `OrderStatusLink`, returns the order per contracts/checkout-api.md (depends on T016, T017)
- [X] T019 [US2] Register `POST /checkout` in `routes/web.php` (depends on T018)
- [X] T020 [P] [US2] Create `resources/js/components/checkout/CheckoutDetailsForm.tsx` — pre-fills name/email from an active attendee session, otherwise a plain guest name/email form; shows the cart review with line items and total before submit (AS1–AS4). Discovered during implementation: feature 002 never shipped a client-accessible "am I logged in" endpoint (login only ever returned attendee info inline in its own response) — added a small `GET /session` on the existing `AuthenticatedSessionController` to unblock this, tested in `tests/Feature/Checkout/SessionCheckTest.php`.
- [X] T021 [US2] Create `resources/js/routes/checkout.tsx` — wires `CheckoutDetailsForm`, submits to `POST /checkout` with a client-generated `transaction_hash` held in form state (not regenerated on retry), redirects to the payment step on success (depends on T019, T020)

**Checkpoint**: User Stories 1 AND 2 both work independently — a visitor can build a cart and submit a real, correctly-decremented pending order.

---

## Phase 5: User Story 3 - Attendee Pays via WhatsApp or Offline Bank Transfer (Priority: P1)

**Goal**: A pending order gets a working payment path today — WhatsApp or bank transfer — plus proof-of-payment upload, and abandoned pending orders expire and release their held inventory (spec.md FR-007–FR-010a, FR-017–FR-021)

**Independent Test**: Submit an order, choose WhatsApp, confirm a working `wa.me` link with the order reference/total/status-page URL pre-filled; choose bank transfer, confirm the configured details render; upload a proof-of-payment file and confirm it's stored; fast-forward a pending order's `created_at` by 25 hours, run the expiry command, and confirm its inventory is released and it can no longer be confirmed.

### Tests for User Story 3 ⚠️ write first, must fail

- [X] T022 [P] [US3] Pest feature test `tests/Feature/Checkout/OrderStatusPageTest.php` — `GET /orders/{order}` returns the shape in contracts/checkout-api.md; `expires_at` present only while pending; `tickets` is empty until paid (FR-015 preview); no endpoint anywhere lists/enumerates orders by attendee or email for an unauthenticated caller (research.md §8)
- [X] T023 [P] [US3] Pest feature test `tests/Feature/Checkout/ProofOfPaymentUploadTest.php` — upload succeeds while pending and is visible via the order (FR-019); rejected with 409 once the order is paid or expired; rejected with 422 for a missing/invalid file
- [X] T024 [P] [US3] Pest feature test `tests/Feature/Checkout/OrderExpiryTest.php` — a pending order older than 24h is moved to `expired` by `orders:expire-pending`, its ticket types' `available_quantity` is released back, and a subsequent payment-confirmation attempt on it is refused (FR-017, FR-012); an order younger than 24h is untouched; writes an `AuditLog` row per expired order (research.md §8a)

### Implementation for User Story 3

- [X] T025 [US3] Add `show(Order $order)` to `app/Http/Controllers/Checkout/OrderController.php` — `GET /orders/{order}`, returns the shape in contracts/checkout-api.md; builds each ticket's `pdf_url` as a literal path string (`/orders/{order}/tickets/{ticket}/pdf`), not via a named `route()` call, so this doesn't depend on US5's route existing yet (depends on T018)
- [X] T026 [P] [US3] Create `app/Http/Requests/Checkout/UploadProofOfPaymentRequest.php` — file required, image/PDF type, reasonable size cap (mirrors feature 003's hero-image validation pattern)
- [X] T027 [US3] Create `app/Http/Controllers/Checkout/ProofOfPaymentController.php` — `POST /orders/{order}/proof-of-payment`, 409 unless `status = pending`, stores to a private (non-publicly-listable) disk, updates `proof_of_payment_path` (depends on T026)
- [X] T028 [US3] Register `GET /orders/{order}` and `POST /orders/{order}/proof-of-payment` in `routes/web.php` (depends on T025, T027)
- [X] T029 [US3] Create `app/Actions/Checkout/ExpirePendingOrdersAction.php` — finds pending orders with `created_at` older than 24h; per order, in one transaction, releases each line item's reserved quantity (conditional update, research.md §3) and sets `status = Expired`; writes one `AuditLog` row per order (`staff_id` null, research.md §8a) (depends on T002, T005). Implementation note: unlike the checkout decrement (T016), a version-mismatch here must be *retried*, not accepted as a final outcome — a release that silently affects 0 rows would permanently lose that inventory. Added a small bounded retry loop (5 attempts) around the conditional increment.
- [X] T030 [US3] Create `app/Console/Commands/ExpirePendingOrders.php` (`orders:expire-pending`) calling the action from T029 (depends on T029)
- [X] T031 [US3] Register the scheduler entry in `bootstrap/app.php` — `$schedule->command('orders:expire-pending')->everyFiveMinutes()` (research.md §3; depends on T030)
- [X] T032 [P] [US3] Create `resources/js/components/checkout/PaymentMethodStep.tsx` — WhatsApp `wa.me` link built from `config('services.whatsapp.number')` with order reference/total/status-page URL pre-filled in the message (FR-008); bank-transfer details from `config('services.bank_transfer.*')` plus the order reference/total (FR-009); a proof-of-payment upload control wired to `POST /orders/{order}/proof-of-payment`
- [X] T033 [P] [US3] Create `resources/js/components/checkout/OrderStatus.tsx` — renders the pending (with payment instructions), paid, and expired states distinctly (FR-018)
- [X] T034 [US3] Create `resources/js/routes/orders/$orderId.tsx` — order-status page fetching `GET /orders/{order}`, wiring `OrderStatus` and `PaymentMethodStep` (depends on T028, T032, T033). The WhatsApp number/bank-transfer details needed client-side are embedded in `resources/views/app.blade.php` via `Illuminate\Support\Js::from()` (Laravel's safe pattern for this — guards against `</script>` breakout) as `window.__CHECKOUT_CONFIG__`, not fetched from an endpoint, since they're static config rather than order-specific data.

**Checkpoint**: User Stories 1–3 all work independently — the full attendee-facing flow (cart → submit → pay) is usable today without M-Pesa.

---

## Phase 6: User Story 4 - Staff Confirms Payment and the Attendee Gets Their Ticket (Priority: P2)

**Goal**: A permitted staff member can confirm a pending order's payment, which issues its tickets (spec.md FR-011–FR-013, FR-019–FR-021 staff-facing half)

**Independent Test**: As a staff member with order-view access, open a pending order (with an uploaded proof-of-payment file), confirm it, and verify status becomes paid, `confirmed_by`/`confirmed_at` are set, and `Ticket` rows now exist; confirm a non-pending order's confirmation attempt is refused, and that a role without order-view access sees no confirm action at all.

### Tests for User Story 4 ⚠️ write first, must fail

- [X] T035 [P] [US4] Pest feature test `tests/Feature/Checkout/PaymentConfirmationTest.php` — confirming a pending order sets `status = paid`, `confirmed_by`, `confirmed_at`, and creates one `Ticket` per purchased unit across all order items, each with a unique `qr_code` (AS1, AS4); confirming a non-pending order is refused (AS2); writes an `AuditLog` row with `staff_id` set (research.md §8a)
- [X] T036 [P] [US4] Pest feature test `tests/Feature/Filament/OrderConfirmPaymentPolicyTest.php` — `confirmPayment()` matches the existing order-view role matrix (`super_admin`/`event_manager`/`support` allowed, `gate_operator` refused, AS3); staff can see an uploaded proof-of-payment file on a pending order before confirming (AS5, FR-020)

### Implementation for User Story 4

- [X] T037 [US4] Modify `app/Policies/OrderPolicy.php` — add `confirmPayment()` granting the same roles as the existing `viewAny()` (research.md §7)
- [X] T038 [US4] Create `app/Actions/Orders/ConfirmOrderPaymentAction.php` — guards `status = pending` (else refuses); sets `status = Paid`, `confirmed_by`, `confirmed_at`; creates `Ticket` rows per purchased unit with freshly generated unique `qr_code`s (research.md §4); writes one `AuditLog` row with `staff_id` set (research.md §8a) (depends on T037)
- [X] T039 [US4] Modify `app/Filament/Resources/Orders/Pages/ViewOrder.php` — add a `ConfirmPayment` header `Action`, visible/enabled only when the order is pending and `confirmPayment()` allows it, calling `ConfirmOrderPaymentAction` (depends on T038)
- [X] T040 [US4] Modify `app/Filament/Resources/Orders/Schemas/OrderInfolist.php` — add a proof-of-payment file preview entry (FR-020). The `proof-of-payment` disk is private (storage/app/private, not publicly listable), so this needed a small auth-gated streaming route (`GET /admin/orders/{order}/proof-of-payment-file`, new `OrderProofOfPaymentController`, `middleware('auth:staff')` + an inline `OrderPolicy::view` check) rather than a direct disk URL — not anticipated in the original plan. Also caught and fixed a latent bug while touching this file: both `OrderInfolist` and `OrdersTable`'s status-badge `match ($state)` expressions were non-exhaustive over `OrderStatus` — missing the `Expired` case added in Phase 2 — which would have thrown `UnhandledMatchError` the first time any expired order was viewed in the admin panel; this had gone untriggered until now since no test had viewed an infolist/table row for an expired order before.

**Checkpoint**: User Stories 1–4 all work independently — staff can turn a pending order into a paid one with issued tickets.

---

## Phase 7: User Story 5 - Attendee Receives Their Ticket (Priority: P2)

**Goal**: A paid order's tickets are downloadable as individual, scannable PDFs (spec.md FR-014–FR-015)

**Independent Test**: Confirm a pending order's payment, then download a PDF for each of its tickets and confirm each shows the event name, ticket type, attendee name, and a distinct QR code; confirm no PDF is reachable for a still-pending order.

### Tests for User Story 5 ⚠️ write first, must fail

- [X] T041 [P] [US5] Pest feature test `tests/Feature/Checkout/TicketPdfDownloadTest.php` — `GET /orders/{order}/tickets/{ticket}/pdf` returns a PDF only when the order is paid and the ticket belongs to it (AS1, FR-015); the response contains the event name, ticket type, and attendee name; 404 for a pending order's ticket, a mismatched order/ticket pair, or a nonexistent ticket

### Implementation for User Story 5

- [X] T042 [US5] Create `resources/views/tickets/pdf.blade.php` — event name, ticket type, attendee name, and an embedded QR image encoding the ticket's `qr_code` (not its `id`, research.md §5)
- [X] T043 [US5] Create `app/Http/Controllers/Checkout/TicketPdfController.php` — `GET /orders/{order}/tickets/{ticket}/pdf`, 404 unless paid and the ticket belongs to the order, renders T042 via `barryvdh/laravel-dompdf` with a QR image generated via `endroid/qr-code` (depends on T042)
- [X] T044 [US5] Register `GET /orders/{order}/tickets/{ticket}/pdf` in `routes/web.php` (depends on T043)

**Checkpoint**: All five user stories independently functional — the full checkout-to-ticket flow works end to end.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Whole-feature validation and consistency with the rest of the codebase

- [ ] T045 Run the full Pest suite (`php artisan test`) — all new checkout tests pass AND all features 001–003 tests still pass
- [ ] T046 [P] Execute `specs/004-attendee-checkout/quickstart.md` steps 1–9 end-to-end and record results in `specs/004-attendee-checkout/quickstart-results.md`
- [ ] T047 [P] Accessibility spot-check on the new public pages (event/cart, checkout, order-status) against WCAG 2.1 AA, and a manual mobile-viewport pass confirming parity with desktop (SC-006), recording findings in `quickstart-results.md`
- [ ] T048 Run Pint (`vendor/bin/pint`) on all new/modified PHP files, per constitution Principle I's formatting gate
- [ ] T049 Add the `* * * * * php artisan schedule:run` cron entry to `docs/deployment-runbook.md` (research.md §3 — this feature is the first to need Laravel's scheduler; not yet documented there)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup; blocks all user stories
- **User Stories (Phases 3–7)**: All depend on Foundational. US1 and US2 are otherwise independent of each other in principle, but in practice US2's checkout flow is reached from US1's event page, so build in priority order (US1 → US2 → US3 → US4 → US5) even though each phase's own tests don't require the others to exist first. US3's payment step depends on US2's order existing; US4's confirmation depends on US3's pending order existing; US5's PDF download depends on US4's tickets existing.
- **Polish (Phase 8)**: Depends on all user stories

### User Story Dependencies

- **US1 (P1)**: Independent — the MVP's browsing/cart half
- **US2 (P1)**: Functionally depends on US1's cart existing to have something to submit, but its own backend (SubmitOrderAction, POST /checkout) is independently testable via direct API calls without the US1 UI
- **US3 (P1)**: Depends on US2's pending orders existing to pay against or expire
- **US4 (P2)**: Depends on US3's pending orders existing to confirm
- **US5 (P2)**: Depends on US4's tickets existing to download

### Within Each User Story

- Test task(s) first, confirmed failing, before any implementation task (constitution Principle III)
- Requests/Actions before Controllers before routes before frontend components before route pages

### Parallel Opportunities

- T002–T003, T005 are `[P]` in Foundational; T004 touches the shared `tests/Pest.php` so runs alone
- Within each story, all test tasks marked `[P]` can be written in parallel; within implementation, tasks touching different files (e.g., a Request class and a frontend component) can run in parallel — see each phase's `[P]` markers
- Once Foundational completes, US1's frontend tasks (T009–T011) can proceed in parallel with backend work on later stories by a second developer, since they touch entirely different files

---

## Parallel Example: Phase 4 (User Story 2) tests

```bash
# Launch independent US2 test tasks together once T002/T015 exist:
Task: "Pest test for order submission happy path + validation in tests/Feature/Checkout/OrderSubmissionTest.php"
Task: "Pest test for idempotent duplicate submission in tests/Feature/Checkout/OrderSubmissionIdempotencyTest.php"
Task: "Pest test for concurrent oversell prevention in tests/Feature/Checkout/OrderSubmissionOversellTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1 (Setup) → Phase 2 (Foundational)
2. Complete Phase 3 (US1) — browsable event + live-availability cart
3. **STOP and VALIDATE**: a visitor can build a valid cart; over-adding and sold-out states behave correctly
4. Demo the cart as the first increment — no real order exists yet, but the read side is provably correct

### Incremental Delivery

1. Setup + Foundational → enum, config, test harness, factory support ready
2. US1 → live-availability cart (read-only proof)
3. US2 → real, oversell-safe, idempotent pending orders (the actual transaction)
4. US3 → a working payment path today (WhatsApp/bank transfer) + expiry safety net — this is what makes the flow usable in production before M-Pesa exists
5. US4 → staff can turn payment into issued tickets
6. US5 → attendees can actually download what they bought
7. Polish → full-suite regression check (including features 001–003), quickstart run, a11y pass, formatting, deployment-runbook update

### Parallel Team Strategy

After Foundational: Developer A takes US1 (frontend-heavy), Developer B takes US2 (the core transaction, highest-risk), Developer C takes US4+US5 (staff confirmation + PDF, can be stubbed against US2/US3's contracts before US3's UI lands). US3 is the natural next pickup for whoever finishes US1 or US2 first, since it depends on US2's backend but not on US1's UI.

---

## Notes

- [P] tasks = different files, no ordering dependency
- Every test task must fail before its implementation tasks, per constitution Principle III's general testing gate and this feature's explicit place in the booking-critical enumeration
- `payment_method` is stored as `offline` for both the WhatsApp and bank-transfer paths (data-model.md) — there is no separate `PaymentMethod::Whatsapp` case; the distinction is a UI/instructions concern, not a schema one
- Every order-status transition (pending, paid, expired) writes an `AuditLog` row (research.md §8a) — don't skip this by analogy to feature 003's narrower Event/TicketType-CRUD audit carve-out, which does not apply to payment state changes
- Commit after each task or logical group
