# Feature Specification: Attendee Ticket Checkout

**Feature Branch**: `004-attendee-checkout`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "Build the public attendee-facing ticket booking/checkout flow as a short, linear 3-step process on the React/TanStack Router public site: (1) select an event's ticket types and quantities into a cart with live availability shown, (2) enter attendee details (name, email — reusing the existing attendee account if logged in, or capturing guest details tied to an Attendee record) plus a review of the cart with a total and any fee breakdown, (3) choose a payment method and pay — M-Pesa STK push is the primary intended method per the constitution, but the Vodacom M-Pesa API isn't available yet, so WhatsApp checkout must be offered as a real, working payment option alongside M-Pesa (not just a placeholder) for this launch, in addition to the existing offline/bank-transfer payment method already in the schema. WhatsApp checkout means: the attendee's order is created in a pending state, and they're directed to message the organizer's WhatsApp number (with order details pre-filled where possible, e.g. a wa.me link) to arrange payment; a staff member then confirms the payment manually via the existing staff admin panel's order-confirmation workflow (which is itself out of scope for the existing read-only OrderResource and needs to be built as part of, or alongside, this feature). After a successful checkout, show a confirmation page with the order ID and a PDF ticket download link. The flow must prevent overselling (uses the existing ticket_types.available_quantity optimistic-locking guarantee from the core schema), must be mobile-first and fully responsive, and must carry an idempotency key on order creation to prevent duplicate orders from double-submission. This depends on and extends the already-shipped core ticketing schema (001-core-database-schema) and attendee authentication (002-attendee-auth); it does not touch the staff admin panel's existing Event/TicketType/Order resources (003-staff-admin-panel) except to add whatever staff-side payment-confirmation action is needed to actually mark a pending order as paid."

## Clarifications

### Session 2026-08-14

- Q: How does a guest (no account) find their order-status page again after leaving it, without exposing other attendees' orders via a guessable URL? → A: Email the order-status link after submission, built from the order's existing unguessable UUID primary key; no login required to view it, no separate signed token needed.
- Q: WhatsApp/bank-transfer are slow, manual payment methods — should a pending order that's never paid hold its reserved inventory forever, or expire? → A: Pending orders expire after a fixed 24-hour hold window; expired ones release their reserved quantity back to `available_quantity` and can no longer be paid/confirmed.
- Q: The core schema already has an `orders.proof_of_payment_path` column — should this feature actually let attendees upload proof of payment, or leave that column unused for now? → A: Build it now, via two channels: attendees can upload proof directly on their order-status page (stored via `proof_of_payment_path`, visible to staff before confirming), AND the WhatsApp message pre-filled at checkout includes the order-status link itself, so an attendee can instead (or also) send proof-of-payment as an attachment directly in that WhatsApp conversation, which staff cross-reference manually against the linked order.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Attendee Builds a Cart from Live Availability (Priority: P1)

A visitor browsing a published event picks one or more ticket types and quantities. At every point they can see how many of each ticket type remain, and they can never add more than are actually available.

**Why this priority**: Nothing downstream (checkout, payment, tickets) matters if the cart itself can misrepresent availability or let someone attempt to buy tickets that don't exist. This is the entry point of the entire flow.

**Independent Test**: Open a published event with limited-availability ticket types, add tickets to the cart up to the available quantity, and confirm the UI blocks adding more than what's available and reflects the same number the admin panel's Ticket Types list shows.

**Acceptance Scenarios**:

1. **Given** a published event with one or more ticket types that have available quantity remaining, **When** a visitor views the event, **Then** each ticket type shows its price and current available quantity.
2. **Given** a ticket type with 5 remaining, **When** a visitor tries to add a 6th to their cart, **Then** the cart refuses and shows the actual remaining count.
3. **Given** a ticket type with 0 remaining, **When** a visitor views the event, **Then** that ticket type is shown as sold out and cannot be added to the cart.
4. **Given** a non-empty cart, **When** the visitor changes a quantity or removes an item, **Then** the cart's total updates immediately to match.

---

### User Story 2 - Attendee Submits Order with Their Details (Priority: P1)

With a cart built, the visitor enters their name and email (or these are pre-filled if they're logged in), reviews the cart and total, and submits the order. The order is created immediately in a pending state — no payment has happened yet.

**Why this priority**: This is the transaction itself — the step that actually reserves tickets against inventory and creates a real, trackable order. Without it, User Story 1's cart is just a preview.

**Independent Test**: As both a logged-in attendee and as a guest, complete the details step with a valid name/email and submit; confirm an order and its order items are created in the database with a `pending` status and correct totals, and that the ticket type's available quantity decreases accordingly.

**Acceptance Scenarios**:

1. **Given** a logged-in attendee with a non-empty cart, **When** they reach the details step, **Then** their name and email are pre-filled from their account and they can proceed directly to review.
2. **Given** a visitor who is not logged in, **When** they reach the details step, **Then** they can enter a name and email to continue without creating a password or verifying their email first.
3. **Given** a guest email that already belongs to an existing attendee account, **When** the order is submitted, **Then** the order is attached to that existing attendee record rather than creating a duplicate.
4. **Given** a completed details step, **When** the visitor reviews their cart, **Then** they see each line item, its quantity and subtotal, and the order total before confirming.
5. **Given** a valid, reviewed order, **When** the visitor submits it, **Then** an order is created in a pending state, the chosen ticket types' available quantities decrease by the ordered amounts, and the visitor is taken to the payment step for their chosen method.
6. **Given** a submit request that is sent twice in a row (e.g., a double-tap or a network retry), **When** both requests reach the server, **Then** only one order is created.

---

### User Story 3 - Attendee Pays via WhatsApp or Offline Bank Transfer (Priority: P1)

Having submitted a pending order, the attendee picks how they'll pay — message the organizer on WhatsApp, or transfer via bank details shown on screen — and is given everything they need to actually complete that payment outside the app.

**Why this priority**: Without a working payment path today (M-Pesa's API isn't available yet), the order stays pending forever and no ticket ever gets issued. This is the piece that makes the whole flow usable right now, not just in some future state.

**Independent Test**: Submit an order, choose WhatsApp as the payment method, and confirm a working `wa.me` link opens WhatsApp with the organizer's number and the order's key details pre-filled in the message; separately, choose the offline/bank-transfer method and confirm the on-screen instructions include the order's reference and total.

**Acceptance Scenarios**:

1. **Given** a pending order, **When** the attendee reaches the payment step, **Then** they can choose between "Pay via WhatsApp" and "Pay via bank transfer" (M-Pesa is not offered as a selectable option yet, since the integration doesn't exist).
2. **Given** the attendee chooses WhatsApp, **When** the payment step renders, **Then** a link is shown that opens WhatsApp to the organizer's number with a pre-filled message including the order reference and total.
3. **Given** the attendee chooses bank transfer, **When** the payment step renders, **Then** the organization's bank details and the order's reference/total are shown on screen for the attendee to complete manually.
4. **Given** either payment path, **When** the attendee finishes on this step, **Then** they land on an order-status page showing the order as pending, which they can safely revisit later (e.g., from a link emailed to them) to check whether it's been confirmed.
5. **Given** a pending order's status page, **When** the attendee uploads a proof-of-payment file, **Then** it's saved against the order and the order remains pending until a staff member separately confirms it.
6. **Given** the WhatsApp payment path, **When** the attendee sends the pre-filled message, **Then** it includes a link to the order's status page, which the attendee can also use to send a proof-of-payment attachment directly in that WhatsApp conversation instead of (or in addition to) uploading it on the order-status page.

---

### User Story 4 - Staff Confirms Payment and the Attendee Gets Their Ticket (Priority: P2)

A staff member sees a pending order (created via User Stories 2-3), verifies the WhatsApp conversation or bank transfer happened outside the system, and marks the order paid. The attendee is then able to get their ticket.

**Why this priority**: This is the other half of the manual payment methods — without it, a paying attendee's order sits pending forever even after they've actually paid. It depends on orders existing (US2/US3) but is otherwise a separate, staff-side action.

**Independent Test**: As a staff member with order-view access, open a pending order and confirm it; verify the order's status becomes paid, `confirmed_by`/`confirmed_at` are recorded, and the attendee's order-status page now offers their ticket.

**Acceptance Scenarios**:

1. **Given** a pending order, **When** a permitted staff member confirms its payment, **Then** the order's status becomes paid and the confirming staff member and time are recorded.
2. **Given** an order that is not pending (e.g., already paid, or cancelled), **When** a staff member attempts to confirm it, **Then** the action is refused.
3. **Given** a staff member without order-confirmation permission, **When** they view a pending order, **Then** no confirm-payment action is available to them.
4. **Given** an order just confirmed as paid, **When** the attendee revisits their order-status page, **Then** it now shows the order as paid with their ticket(s) available.
5. **Given** a pending order with an uploaded proof-of-payment file, **When** a staff member opens that order, **Then** they can see the uploaded file before deciding whether to confirm payment.

---

### User Story 5 - Attendee Receives Their Ticket (Priority: P2)

Once an order is paid, the attendee can get a ticket for each item they bought, each one individually identifiable for check-in.

**Why this priority**: This is the actual deliverable of the whole flow — a ticket the attendee can present at the event. It depends on an order reaching paid status (User Story 4) but is otherwise independent of exactly how that happened.

**Independent Test**: Confirm a pending order's payment as staff, then as the attendee (or via the order-status page's link) download a PDF ticket for each ticket purchased and confirm each one carries a distinct, scannable identifier.

**Acceptance Scenarios**:

1. **Given** a paid order, **When** the attendee opens their order-status/confirmation page, **Then** they can download a PDF ticket for each individual ticket in the order (not one PDF per line item's quantity — one per ticket).
2. **Given** a downloaded PDF ticket, **When** it's inspected, **Then** it shows the event name, ticket type, attendee name, and a scannable code unique to that ticket.
3. **Given** an order that is still pending, **When** the attendee visits their order-status page, **Then** no ticket download is offered yet.

---

### Edge Cases

- What happens if two attendees both try to buy the last remaining ticket of a type at nearly the same moment? (Relies on the existing oversell-prevention guarantee at the schema level — one succeeds, one is told it's no longer available before their order is created.)
- What happens if an attendee abandons checkout after an order is created but before choosing a payment method, or never completes payment at all? Resolved by FR-017: the order expires 24 hours after submission and its reserved quantity is released.
- What happens if a staff member confirms payment on an order, and then a refund/dispute happens later — is un-confirming a paid order supported, or is that a separate future workflow?
- What happens if an attendee's chosen WhatsApp number/app isn't available on their device (e.g., desktop browser with no WhatsApp Web session)?
- What happens if an attendee tries to re-download their PDF ticket days later, or from a different device? Resolved: the order-status page (reachable via the emailed link, FR-010a) remains accessible from any device and re-offers the same downloadable tickets for any paid order, with no expiry on ticket access itself (only pending orders expire, per FR-017).
- What happens if the same guest email is used for two different orders — do they end up as two separate attendee-facing "accounts," or is there any continuity? Resolved by FR-004: both orders attach to the same Attendee record, matched by email, so there's continuity without a separate "account" concept.
- A guest finds their order-status page again via the emailed link (see FR-010a) rather than logging in — what happens if that email is lost, mistyped, or never arrives?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST show, for every ticket type on a published event, its price and current available quantity, kept in sync with the same underlying data the staff admin panel uses.
- **FR-002**: The system MUST prevent a visitor's cart from holding more of a ticket type than is currently available, and MUST re-validate availability at order-submission time (not just when the item was added to the cart) — a ticket type that sells out between cart-building and submission MUST cause that line item to be rejected, not silently succeed.
- **FR-003**: The system MUST allow a visitor to complete checkout either as a logged-in attendee (details pre-filled from their account) or as a guest (entering name and email directly), with no password creation or email verification required to place an order.
- **FR-004**: When an order is submitted with an email that matches an existing attendee record, the system MUST attach the order to that existing record rather than creating a duplicate attendee.
- **FR-005**: The system MUST create an order and its line items in a pending state as soon as the attendee submits their reviewed cart, decrementing the relevant ticket types' available quantities at that moment (not deferred until payment is confirmed).
- **FR-006**: The system MUST carry a client-generated idempotency key on order submission and MUST guarantee that retried or duplicate submissions of the same checkout attempt create at most one order.
- **FR-007**: The system MUST offer "pay via WhatsApp" and "pay via bank transfer" as the available payment methods at checkout. M-Pesa MUST NOT be offered as a selectable payment method until its API integration exists.
- **FR-008**: When an attendee chooses WhatsApp, the system MUST provide a working link that opens WhatsApp to the organization's configured number with a pre-filled message containing the order's reference, total, and a link to the order's status page — so the attendee can send proof of payment as an attachment directly in that WhatsApp conversation if they prefer, and staff can open the linked order from the conversation to cross-reference it.
- **FR-009**: When an attendee chooses bank transfer, the system MUST display the organization's bank details alongside the order's reference and total.
- **FR-010**: After choosing a payment method, the system MUST provide the attendee a persistent, revisitable order-status page reflecting the order's current status (pending or paid).
- **FR-010a**: The system MUST email the attendee a link to their order-status page as soon as the order is submitted, so a guest (no account/login) can find it again without needing to stay on the original browser session; this link requires no separate token beyond the order's own identifier.
- **FR-011**: The system MUST allow a staff member with order-confirmation permission to mark a pending order as paid, recording who confirmed it and when.
- **FR-012**: The system MUST refuse a payment-confirmation attempt on an order that is not currently pending.
- **FR-013**: The system MUST restrict the payment-confirmation action to staff roles already permitted to view orders under the existing admin panel's role matrix; it MUST NOT be available to roles without order-view access.
- **FR-014**: Once an order is paid, the system MUST make one downloadable PDF ticket available per individual ticket purchased (not per line item), each showing the event name, ticket type, attendee name, and a scannable code unique to that ticket.
- **FR-015**: The system MUST NOT offer a ticket download for any order that is not paid.
- **FR-016**: The checkout flow MUST be a linear 3-step process — cart, details/review, payment — with no dead ends, and MUST be fully usable on both mobile and desktop.
- **FR-017**: A pending order MUST automatically expire 24 hours after submission if it has not been confirmed paid by then; expiring an order MUST release its reserved ticket-type quantities back to `available_quantity` and MUST make the order permanently unconfirmable (a staff member can no longer mark it paid).
- **FR-018**: The system MUST show an attendee visiting an expired order's status page a clear "this order expired" state, distinct from pending or paid.
- **FR-019**: While an order is pending, the system MUST let the attendee upload a proof-of-payment file (e.g., a screenshot or receipt image) from the order-status page.
- **FR-020**: The system MUST show staff any uploaded proof-of-payment file when they view a pending order, before they confirm its payment.
- **FR-021**: Uploading proof of payment MUST NOT by itself change an order's status — confirmation remains a distinct staff action (FR-011); the upload is informational input to that decision, not an automatic trigger.

### Key Entities

- **Cart**: The visitor's in-progress ticket selection prior to submitting an order — one or more (ticket type, quantity) pairs for a single event, held client-side until submission. Not persisted on its own; it becomes an Order and its Order Items only at submission.
- **Order** *(existing, feature 001)*: A purchase attempt by an attendee. This feature is the first to actually create orders through normal use; adds no new columns, but is the first feature to write `status`, `payment_method`, `payment_reference`, `proof_of_payment_path`, and the audit fields (`confirmed_by`, `confirmed_at`) in practice.
- **Order Item** *(existing, feature 001)*: A single ticket-type line within an order, created at submission time from the cart's contents.
- **Ticket** *(existing, feature 001)*: One individually-identifiable ticket, issued per unit purchased once its order is paid; carries the scannable code referenced in FR-014.
- **Attendee** *(existing, feature 001/002)*: The purchaser's identity — reused if the checkout email matches an existing record, created fresh otherwise.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A visitor can go from viewing an event to a submitted pending order in under 3 minutes on a typical mobile connection.
- **SC-002**: 100% of attempts to order more of a ticket type than is currently available are rejected before an order is created, with zero oversold tickets, verified under concurrent attempts.
- **SC-003**: 100% of duplicate/retried checkout submissions (same idempotency key) result in exactly one order, never zero or more than one.
- **SC-004**: Every order reaches a state the attendee can see a clear next step for — pending-with-payment-instructions, or paid-with-ticket — with no order left in a state the attendee can't act on or check.
- **SC-005**: A staff member confirming a pending order's payment makes that order's ticket(s) available for download immediately, with no further manual step.
- **SC-006**: Checkout completion rate and time-to-complete are equivalent on mobile and desktop (no feature or step is desktop-only).

## Assumptions

- M-Pesa STK push integration is explicitly out of scope for this feature — the Vodacom M-Pesa API isn't available yet (per project context). The payment method selection is built to accommodate adding M-Pesa later without a checkout redesign, but only WhatsApp and bank-transfer are live, selectable options now.
- "WhatsApp checkout" means directing the attendee to an external WhatsApp conversation with the organizer to arrange payment — it is not a real-time in-app payment API integration; WhatsApp itself has no payment-processing role here beyond being the communication channel.
- The organization's WhatsApp number and bank-transfer details are configuration values (not staff-editable through this feature's UI); how they're set is an implementation detail for planning.
- Pending orders expire 24 hours after submission (FR-017), releasing their reserved inventory. This requires some process to detect and expire stale pending orders (e.g., a scheduled check) rather than only checking lazily on read — the exact mechanism is a planning-level decision, but expiry must actually happen, not just be computed on demand when someone happens to look.
- Un-confirming a paid order, refunds, and disputes are out of scope for this feature, consistent with the staff admin panel's existing "refund/payment workflow is a future, separate feature" assumption (003-staff-admin-panel). This feature adds only the pending → paid confirmation action, not the reverse.
- Order confirmation/status updates are communicated to the attendee via the persistent order-status page URL, emailed to them at submission per FR-010a. The project already has working transactional email (feature 002's verification/password-reset emails), so no new email infrastructure is needed.
- Ticket PDFs are generated on demand when downloaded, not pre-generated at payment-confirmation time; either approach satisfies this spec's requirements, and the choice is a planning-level decision.
- This feature depends on 001-core-database-schema (orders/order_items/tickets/ticket_types/attendees) and 002-attendee-auth (attendee login/session) being in place, which they are. It adds a narrow payment-confirmation action to the staff admin panel (003-staff-admin-panel) but does not otherwise change that panel's existing Event/TicketType/Order resources or their policies.
