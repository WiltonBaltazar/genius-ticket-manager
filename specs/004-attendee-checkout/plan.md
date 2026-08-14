# Implementation Plan: Attendee Ticket Checkout

**Branch**: `004-attendee-checkout` (working on `main`; no feature-specific git branch was created — no branch-creation hook configured) | **Date**: 2026-08-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-attendee-checkout/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Build the public 3-step checkout flow (cart → details/review → payment) on the existing React/TanStack Router public site, plus the minimal staff-side payment-confirmation action needed to actually turn a pending order into issued tickets. M-Pesa's API isn't available yet, so the two live payment methods are WhatsApp (a `wa.me` deep link) and offline bank transfer — both manual, human-confirmed methods, which is why pending-order expiry (24h) and proof-of-payment upload are load-bearing parts of this feature, not nice-to-haves. No new tables: this feature is the first to actually write to `orders`/`order_items`/`tickets` through normal use, reusing the exact oversell-prevention locking pattern feature 001 already proved. One new `OrderStatus::Expired` case, two new Composer dependencies (PDF + QR generation), one new scheduled command, and a narrow addition to feature 003's existing `OrderResource` (research.md §1–§9).

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13) backend; TypeScript/React 19 via TanStack Router on the existing public-site frontend (feature 002's stack, unchanged).

**Primary Dependencies**: `barryvdh/laravel-dompdf` (new — PDF ticket rendering, research.md §5), `endroid/qr-code` (new — QR generation, research.md §5). No new frontend dependencies — cart state uses React context/`localStorage`, no state-management library beyond what feature 002 already established.

**Storage**: MySQL 8.0+, InnoDB (existing). No new tables or columns — this feature's only schema change is extending the existing `OrderStatus` PHP enum with an `Expired` case (data-model.md); every other write goes through columns feature 001 already migrated.

**Testing**: Pest feature tests per constitution Principle III, which fully applies here (this feature is explicitly named in the principle's booking-critical enumeration: ticket selection, cart, checkout, inventory locking). Concurrency tests simulate two simultaneous checkout submissions racing the same low-stock ticket type, reusing the two-connection pattern already proven in `tests/Feature/Schema/TicketTypeOversellTest.php`. Backend: feature tests for cart/checkout endpoints, order-status/proof-of-payment endpoints, the expiry command, and the staff confirmation action; policy tests for the new `OrderPolicy::confirmPayment()` ability. Frontend: component/integration tests for the cart and checkout form steps, consistent with feature 002's existing test setup for the public site.

**Target Platform**: Same shared-hosting PHP-FPM target as features 001–003. The one new operational requirement is the standard Laravel scheduler cron entry (`* * * * * php artisan schedule:run`) for the order-expiry command (research.md §3) — not yet present in `docs/deployment-runbook.md`.

**Project Type**: Web application — same single Laravel monolith, now with its first real write traffic through the public React site's checkout flow (feature 002 only ever wrote `attendees`) and its first addition to feature 003's admin panel since that feature shipped.

**Performance Goals**: No new numeric latency targets stated in spec.md; SC-001 sets a user-facing 3-minute checkout-completion target (UX/flow length, not a server latency budget).

**Constraints**: No queued jobs for anything load-bearing (research.md §3's rationale — this project's `QUEUE_CONNECTION=database` has no confirmed persistent worker) — order-expiry runs via the scheduler, and order-status emails send synchronously rather than via the existing `ShouldQueue` pattern feature 002 used for verification emails, specifically because guest access to a pending order depends on that email actually arriving. No PDF/QR generation requiring a headless browser or external binary (constitution Principle VI) — both new dependencies are pure PHP.

**Scale/Scope**: 5 new public JSON endpoints (contracts/checkout-api.md), 1 new Artisan command, 1 new enum case, 2 new Composer dependencies, ~4–5 new Action classes, 1 new Filament action + 1 policy method + 1 infolist addition on the existing `OrderResource`, new React cart/checkout/order-status pages on the existing public site.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Applies to this feature? | Status |
|---|---|---|
| I. SOLID Architecture & Clean Code | Yes | ✅ PASS: order submission, payment confirmation, and order expiry are each a single-purpose `Action` class (`SubmitOrderAction`, `ConfirmOrderPaymentAction`, the expiry command's own handler) — matching the existing `RegisterAttendeeAction` precedent (feature 002) rather than fattening controllers. Controllers stay thin (validate via FormRequest, delegate to an Action, return JSON), consistent with `RegisteredAttendeeController`'s existing shape. |
| II. Security by Design | Yes, fully — this is the project's first real payment-adjacent, money-moving flow | ✅ PASS: every mutating endpoint sits behind the existing `web` middleware group's CSRF protection (no new middleware needed). Order/proof-of-payment access is by unguessable UUID, not a guessable sequential ID or a new weaker auth scheme (research.md §8). No payment credentials of any kind are handled by this feature — WhatsApp/bank-transfer are both off-platform, human-mediated payment completion, so there is no card/API-key surface to protect yet. Every order carries an idempotency key (`transaction_hash`, research.md §1) satisfying the constitution's "every payment request MUST carry an idempotency key" requirement. Proof-of-payment uploads are stored on a private, non-publicly-listable disk. |
| III. Test-First for Booking-Critical Paths | Yes, fully — this feature *is* the booking-critical path the principle names explicitly (ticket selection, cart, checkout, inventory locking) | ✅ PASS (by construction, per `tasks.md`'s ordering): Pest tests for order creation happy path, validation failures, and the concurrency case (two simultaneous submissions racing the same ticket type) are written before the corresponding `Action`/endpoint, reusing the exact locking pattern `TicketTypeOversellTest` already proved rather than inventing a new one. |
| IV. Data Integrity & Immutable Audit Trail | Yes | ✅ PASS: no new tables; `orders`/`order_items`/`tickets` keep their existing FK `ON DELETE RESTRICT`/CASCADE semantics untouched. `confirmed_by`/`confirmed_at` are written by the new confirmation action exactly as feature 001's schema already anticipated. The constitution requires "every payment state change MUST be written to the immutable `audit_logs` table" — this feature's three state changes (order created/pending, confirmed/paid, expired) each write one `AuditLog` row against the `Order` (polymorphic `auditable`, matching `Order::auditLogs()`, already defined); `staff_id` is null for the two attendee/system-initiated transitions (created, expired) and set to the confirming staff member for the paid transition — the column is nullable specifically to allow this. `Attendee`, `Order` retain soft deletes; nothing in this feature bypasses them. |
| V. Accessible, On-Brand Experience | Yes | ✅ PASS (by design): the 3-step flow (cart → details/review → payment) matches the constitution's mandated shape exactly, substituting "pay via M-Pesa" with "pay via WhatsApp or bank transfer" per the already-amended Principle V text (from this session's earlier Stripe→M-Pesa correction, which anticipated exactly this substitution). Confirmation page + PDF ticket download link are explicit constitution requirements, both satisfied (spec.md User Story 5). New React components follow feature 002's existing mobile-first, WCAG-2.1-AA-conscious patterns; no new design system introduced. |
| VI. Shared-Hosting-Compatible Simplicity | Yes | ✅ PASS: both new Composer dependencies are pure PHP (no Docker, no headless browser, no system binary — research.md §5). The order-expiry mechanism uses Laravel's scheduler (one cron line) rather than a queue worker whose presence isn't confirmed in this project's deployment setup (research.md §3) — the more shared-hosting-appropriate choice, not the more elaborate one. |

No unjustified violations. Complexity Tracking table below is intentionally empty.

**Post-Phase-1 re-check**: `data-model.md` and `research.md` were reviewed against this table after Phase 1 design. Two choices are worth surfacing explicitly:
- Sending order-status emails synchronously rather than via the existing `ShouldQueue` pattern (research.md §3) is a deliberate, narrower deviation from feature 002's precedent — justified by this project having no confirmed queue-worker process, not a stylistic preference. If a queue worker is later confirmed running in production, this is a one-line change back to `ShouldQueue`.
- Storing `payment_method = offline` for both the WhatsApp and bank-transfer paths (data-model.md) rather than adding a third `PaymentMethod` enum case is a deliberate reading of "WhatsApp is a communication channel, not a payment processor" (spec.md Assumptions) — the two are UI/instruction variants of the same underlying manual-payment method, not distinct processors the schema needs to distinguish.

Gate remains PASS; no Complexity Tracking entries needed.

## Project Structure

### Documentation (this feature)

```text
specs/004-attendee-checkout/
├── plan.md               # This file (/speckit-plan command output)
├── research.md           # Phase 0 output (/speckit-plan command)
├── data-model.md         # Phase 1 output (/speckit-plan command)
├── contracts/
│   └── checkout-api.md   # Phase 1 output (/speckit-plan command)
├── quickstart.md         # Phase 1 output (/speckit-plan command)
├── checklists/
│   └── requirements.md
└── tasks.md              # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

Same single Laravel 13 monolith as features 001–003; this feature is the first to add real logic to both `resources/js` (the public React site) and `app/Filament` (feature 003's admin panel) in the same piece of work.

```text
app/
├── Enums/
│   └── OrderStatus.php                          # MODIFIED: adds Expired case (research.md §3)
├── Actions/
│   └── Checkout/
│       ├── SubmitOrderAction.php                # NEW: cart -> pending Order + OrderItems, oversell-safe
│       │                                           (research.md §2), attendee find-or-create (FR-004),
│       │                                           writes an AuditLog row (research.md §8a)
│       └── ExpirePendingOrdersAction.php         # NEW: called by the Artisan command below; writes an
│                                                    AuditLog row per expired order (research.md §8a)
├── Actions/
│   └── Orders/
│       └── ConfirmOrderPaymentAction.php         # NEW: pending -> paid, creates Ticket rows (research.md §4/§7),
│                                                    writes an AuditLog row with staff_id set (research.md §8a)
├── Http/
│   ├── Controllers/
│   │   └── Checkout/
│   │       ├── EventCheckoutController.php       # NEW: GET /events/{event:slug}
│   │       ├── OrderController.php                # NEW: POST /checkout, GET /orders/{order}
│   │       ├── ProofOfPaymentController.php       # NEW: POST /orders/{order}/proof-of-payment
│   │       └── TicketPdfController.php            # NEW: GET /orders/{order}/tickets/{ticket}/pdf
│   └── Requests/
│       └── Checkout/
│           ├── SubmitOrderRequest.php             # NEW
│           └── UploadProofOfPaymentRequest.php    # NEW
├── Notifications/
│   └── Orders/
│       └── OrderStatusLink.php                    # NEW: sent synchronously at submission (FR-010a,
│                                                      research.md §3's Constraints note — not ShouldQueue)
├── Console/
│   └── Commands/
│       └── ExpirePendingOrders.php                 # NEW: `orders:expire-pending`, scheduled every 5 min
├── Policies/
│   └── OrderPolicy.php                             # MODIFIED (feature 003): adds confirmPayment()
└── Filament/
    └── Resources/
        └── Orders/
            ├── Pages/
            │   └── ViewOrder.php                    # MODIFIED (feature 003): adds ConfirmPayment header action
            └── Schemas/
                └── OrderInfolist.php                 # MODIFIED (feature 003): adds proof-of-payment file preview

bootstrap/
└── app.php                                         # MODIFIED: registers the scheduler entry for
                                                        orders:expire-pending (research.md §3)

config/
└── services.php                                    # MODIFIED: adds whatsapp.number, bank_transfer.*
                                                        (data-model.md)

resources/
├── views/
│   └── tickets/
│       └── pdf.blade.php                           # NEW: the dompdf template a ticket renders through
└── js/
    ├── lib/
    │   └── cart.ts                                 # NEW: client-side cart state (research.md §9)
    ├── components/
    │   └── checkout/
    │       ├── TicketTypeSelector.tsx               # NEW: User Story 1
    │       ├── CheckoutDetailsForm.tsx               # NEW: User Story 2
    │       ├── PaymentMethodStep.tsx                 # NEW: User Story 3
    │       └── OrderStatus.tsx                       # NEW: User Stories 3-5
    └── routes/
        ├── events/
        │   └── $slug.tsx                            # NEW: event page + cart (User Story 1)
        ├── checkout.tsx                              # NEW: details/review + payment steps (User Stories 2-3)
        └── orders/
            └── $orderId.tsx                          # NEW: order-status page (User Stories 3-5)

database/
├── migrations/
│   └── (none — no schema change; OrderStatus::Expired is a PHP-enum-only addition)
└── factories/
    └── OrderFactory.php                             # MODIFIED: adds an `expired()` state for tests

tests/
├── Feature/
│   └── Checkout/
│       ├── EventAvailabilityTest.php                # GET /events/{slug} reflects live availability
│       ├── OrderSubmissionTest.php                   # happy path, validation, guest vs logged-in, FR-004
│       ├── OrderSubmissionOversellTest.php           # concurrency: two simultaneous submissions, one ticket left
│       ├── OrderSubmissionIdempotencyTest.php        # duplicate transaction_hash returns the same order
│       ├── OrderStatusPageTest.php                   # GET /orders/{order}, access-by-UUID, no enumeration
│       ├── ProofOfPaymentUploadTest.php               # upload while pending; rejected once paid/expired
│       ├── PaymentConfirmationTest.php                # ConfirmOrderPaymentAction: pending->paid, tickets created
│       ├── OrderExpiryTest.php                        # orders:expire-pending releases inventory, blocks confirm
│       └── TicketPdfDownloadTest.php                  # PDF only for paid orders, contains a QR code
└── Feature/Filament/
    └── OrderConfirmPaymentPolicyTest.php              # confirmPayment() ability matches the existing role matrix
```

**Structure Decision**: Same single Laravel application as features 001–003, now with its first meaningful write path on both ends (public React checkout, admin-panel confirmation). Checkout logic lives in `Actions` classes (not services/repositories) matching feature 002's already-established `RegisterAttendeeAction` pattern — Principle I's "no interface/DI seam without a real multi-branch process to justify it" reasoning applies here too, since each Action is a single, testable unit of business logic with one clear responsibility. The staff-side change is the smallest possible addition to feature 003's existing `OrderResource` (one action, one policy method, one infolist field) rather than a new resource, matching that feature's own established minimal-footprint precedent.

## Complexity Tracking

> No Constitution Check violations were identified — this table is intentionally left empty.
