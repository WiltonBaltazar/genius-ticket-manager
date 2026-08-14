# Phase 0 Research: Attendee Ticket Checkout

## 1. Idempotency key: reuse `orders.transaction_hash`, don't add a new column

**Decision**: The client generates a UUID (or similar nonce) once per checkout attempt and sends it as `transaction_hash` on order submission. The existing `orders.transaction_hash` column (feature 001) already carries a `UNIQUE` constraint and an existing test (`OrderTransactionHashUniquenessTest`) already proves a duplicate value is rejected at the DB layer. A retried/duplicated submission with the same `transaction_hash` fails the unique constraint; the controller catches that specific case and returns the already-created order instead of erroring.

**Rationale**: This is exactly what `transaction_hash` was built for — no new schema, no new concept. Regenerating the value only when the attendee starts a genuinely new checkout (not on retry) is a client-side concern (a value held in the checkout form's state, not derived per-request).

**Alternatives considered**: A dedicated `idempotency_key` header/column (rejected — `transaction_hash` already fills this exact role; adding a second column with the same purpose would be redundant schema).

## 2. Oversell-safe, all-or-nothing order creation

**Decision**: Order submission runs inside a single DB transaction. For each cart line item, it issues the same conditional `UPDATE ... WHERE version = ? AND available_quantity >= ?` pattern already proven in `tests/Feature/Schema/TicketTypeOversellTest.php` (feature 001), decrementing by the requested quantity rather than by 1. If any line item's conditional update affects 0 rows (insufficient stock or a lost race), the entire transaction rolls back — no order, no order items, no partial decrement — and the request fails with a 422 identifying which ticket type(s) came up short. `TicketType`'s existing `booted()` hook still auto-increments `version` on every successful update, so no new locking mechanism is introduced.

**Rationale**: Spec.md FR-002 requires re-validation at submission time, and the schema's oversell guarantee is already proven at the single-row level — this just applies the same pattern per line item inside one transaction, and treats the cart as a single all-or-nothing unit rather than allowing a partial order (silently dropping a sold-out item would mean charging for less than what the attendee reviewed and confirmed, which spec.md's review step is designed to prevent).

**Alternatives considered**: Partial-success orders that drop unavailable line items (rejected — contradicts the review step's promise: what the attendee approved is what they get, or nothing). A pessimistic row lock (`SELECT ... FOR UPDATE`) instead of optimistic versioning (rejected — inconsistent with the locking strategy feature 001 already established and tested for this exact table).

## 3. Pending-order expiry: a scheduled console command, not a queued job

**Decision**: Add `OrderStatus::Expired` as a new backed-enum case. A new Artisan command (`orders:expire-pending`) finds pending orders older than 24 hours, and for each one, inside a transaction: releases each line item's reserved quantity back to `available_quantity` (same conditional-update pattern as §2, run in reverse), then sets the order's status to `Expired`. Registered on Laravel's scheduler (`$schedule->command('orders:expire-pending')->everyFiveMinutes()`), which requires the standard single cron entry (`* * * * * php artisan schedule:run`) — not yet present in `docs/deployment-runbook.md`.

**Rationale**: This project's `QUEUE_CONNECTION=database` has no documented persistent worker or `queue:work` cron entry in the deployment runbook — relying on a queued job for something as consequential as releasing inventory risks it silently never running. Laravel's scheduler needs only the one, already-standard cron line, matching constitution Principle VI's shared-hosting-simplicity constraint better than a worker process would. A new `Expired` status (rather than reusing `Cancelled`) keeps "abandoned automatically" distinguishable from any future staff-initiated cancellation, per spec.md FR-018's explicit requirement for a distinct visible state.

**Alternatives considered**: A queued `ExpireOrderJob` dispatched with a 24h delay at submission time (rejected — depends on unconfirmed worker infrastructure; also awkward to cancel/no-op if the order is paid before the delay elapses). Lazy expiry (only checked when someone happens to view the order) (rejected — spec.md's own Assumptions explicitly rules this out: "expiry must actually happen, not just be computed on demand").

## 4. Ticket issuance happens at payment confirmation, not at order submission

**Decision**: `Ticket` rows (one per purchased unit, each with a freshly generated unique `qr_code`) are created by the same staff action that confirms payment (§7), not when the order is first submitted as pending.

**Rationale**: If tickets were created at submission time, expiring a pending order (§3) would also need to delete or void those tickets — extra cleanup for records that, in the common abandoned-checkout case, should never have existed in a durable form at all. Deferring ticket creation to the paid transition means an expired order simply never had tickets, no cleanup path needed.

**Alternatives considered**: Creating tickets at submission with `status = unused` and voiding them on expiry (rejected — more moving parts for the same end state, and risks a real ticket briefly existing for an order nobody has paid for).

## 5. PDF ticket generation: `barryvdh/laravel-dompdf` + `endroid/qr-code`, generated on demand

**Decision**: Add `barryvdh/laravel-dompdf` (pure-PHP HTML-to-PDF, no headless browser or system binary) and `endroid/qr-code` (actively maintained, PHP-native QR generation) as new Composer dependencies. A ticket's PDF is rendered on demand when downloaded (a Blade view → PDF), embedding a QR image encoding the ticket's `qr_code` value — not pre-generated and stored at confirmation time.

**Rationale**: Both packages are pure PHP with no external process/binary dependency, consistent with constitution Principle VI (no Docker, no headless Chrome, runs in the same PHP-FPM process as everything else). On-demand generation avoids managing a generated-file store (and its cleanup) on shared hosting's disk; a PDF is cheap enough to regenerate per download that pre-generation buys nothing here.

**Alternatives considered**: `spatie/browsershot` (rejected — requires a headless Chrome/Node runtime, violates Principle VI). Pre-generating and storing PDFs at confirmation time (rejected — extra storage/cleanup for no measurable benefit at this scale).

## 6. WhatsApp checkout: a `wa.me` deep link, no API integration

**Decision**: The organization's WhatsApp number is a new config value (`config('services.whatsapp.number')`, sourced from `.env`). The payment step renders a plain `https://wa.me/<number>?text=<url-encoded message>` link — the message includes the order reference, total, and the order-status page URL (so the attendee can send proof-of-payment as a WhatsApp attachment and staff can open the linked order from the conversation, per the Clarifications session). No WhatsApp Business API integration, no webhook, no new package — this is a static link construction, matching spec.md's Assumption that WhatsApp is purely a communication channel here, not a payment API.

**Rationale**: `wa.me` links are a public, documented WhatsApp feature that works identically whether the visitor has the app, WhatsApp Web, or neither (in which case WhatsApp's own site prompts them to install/open it) — no project-side fallback logic is needed for the "WhatsApp isn't available on this device" edge case; that's WhatsApp's own onboarding flow to handle.

**Alternatives considered**: WhatsApp Business Cloud API (rejected — real API integration with its own credentialing/approval process, out of proportion for "send a pre-filled message," and not what "WhatsApp checkout" was scoped to mean per spec.md's Assumptions).

## 7. Staff payment confirmation: one new `Action` on the existing `OrderResource`, not a new resource

**Decision**: Add a `ConfirmPayment` Filament `Action` to `OrderResource`'s `ViewOrder` page (feature 003), visible/enabled only when `order.status === Pending` and gated by a new `OrderPolicy::confirmPayment()` ability (allowed for the same roles already permitted to view orders: `super_admin`, `event_manager`, `support` — matching spec.md FR-013's explicit reuse of the existing role matrix). The action calls a new `app/Actions/Orders/ConfirmOrderPaymentAction` that, in one transaction: sets `status = Paid`, `confirmed_by`, `confirmed_at`, and creates the order's `Ticket` rows (§4). The existing `OrderInfolist` gains a proof-of-payment file preview (FR-020) so staff can see it before confirming.

**Rationale**: This is the minimal touch to feature 003's already-shipped, deliberately read-mostly `OrderResource` — one action, one policy method, one infolist addition — rather than reopening it into a general-purpose editable resource, which spec.md's Assumptions explicitly says stays out of scope (refunds, un-confirming, and general order editing remain future work).

**Alternatives considered**: A full edit form for orders (rejected — spec.md is explicit that only the pending → paid confirmation action is in scope, nothing else). A separate "Pending Payments" resource/page (rejected — the existing `OrderResource`'s list/filter already surfaces pending orders; a parallel resource would duplicate that without adding capability).

## 8a. Every payment state change writes to `audit_logs`

**Decision**: `SubmitOrderAction` (pending), `ConfirmOrderPaymentAction` (paid), and the expiry command's handler (expired) each write one `AuditLog` row against the `Order` via its existing `auditLogs()` polymorphic relation. `staff_id` is null for the two attendee/system-initiated transitions (created, expired) and set to the confirming staff member for the paid transition.

**Rationale**: The constitution states this plainly and without qualification: "every payment state change MUST be written to the immutable `audit_logs` table." This feature's three order-status transitions are exactly that. `staff_id`'s existing nullability (feature 001) already anticipates a non-staff-attributed entry.

**Alternatives considered**: Skipping this for the attendee-initiated transitions on the theory that `audit_logs` is "staff-action-scoped" (rejected — that framing was feature 003's own Clarification about Event/TicketType *CRUD* specifically, not payment state changes, which the constitution addresses in the very same sentence as the audit requirement; conflating the two would misread the constitution's actual scope).

## 8. Order-status page and proof-of-payment upload: public routes keyed by the order's UUID, no new auth

**Decision**: New `web` routes (no `/api` prefix, matching the project's existing session-based routing per `bootstrap/app.php`'s own comment) added to `routes/web.php`: `GET /orders/{order}` (status page data), `POST /orders/{order}/proof-of-payment` (upload). Neither requires authentication — knowledge of the order's UUID (a UUIDv7 primary key, effectively unguessable) is the access control, per the Clarifications session's resolution. The upload endpoint only accepts a file while the order is still pending (FR-019), stored on a private (non-publicly-listable) disk.

**Rationale**: This mirrors how most checkout systems hand back an unguessable order/session link as the access mechanism for a guest with no account — it needs no new authentication concept, and matches spec.md's explicit resolution that the order's own UUID is sufficient without a separate signed token.

**Alternatives considered**: Requiring attendee login to view order status (rejected by the Clarifications session — would force guests into account creation, contradicting FR-003). A separate signed/expiring token (rejected — same session, deemed unnecessary given the UUID is already unguessable).

## 9. Cart: client-side only, no server persistence

**Decision**: The cart (ticket type/quantity selections prior to submission) lives entirely in the React app's client-side state (e.g., a context/store persisted to `localStorage` so it survives a page reload), matching spec.md's Key Entities description of Cart as "not persisted on its own." It becomes real `Order`/`OrderItem` rows only at submission (§2).

**Rationale**: Nothing in spec.md requires a cart to survive across devices or outlive the browser session it was built in — a client-only cart is the simplest thing that satisfies every acceptance scenario, and avoids inventing a server-side "reservation" concept distinct from the Order itself.

**Alternatives considered**: A server-persisted cart/reservation entity created before checkout (rejected — no requirement needs it, and it would duplicate the inventory-holding role the pending `Order` already plays after submission).

---

**Output**: These decisions, combined with the already-shipped schema (001) and attendee session handling (002), fully specify this feature's data and control flow. Proceed to `data-model.md` for the concrete schema additions and `contracts/` for the new endpoints.
