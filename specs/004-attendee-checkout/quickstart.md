# Quickstart: Validating Attendee Checkout

This is a validation guide for the flow in `spec.md` and the design in `research.md`/`data-model.md`, not an implementation walkthrough. Run these once the migrations, `OrderStatus::Expired` case, checkout controllers/actions, `orders:expire-pending` command, React cart/checkout pages, and the staff `ConfirmPayment` action from `tasks.md` exist.

## Prerequisites

- Features 001–003's quickstart guides already passing (schema migrated, staff admin panel working, at least one seeded staff account with order-view access)
- `composer require barryvdh/laravel-dompdf endroid/qr-code` installed
- `services.whatsapp.number` and `services.bank_transfer.*` config values set in `.env`
- At least one `published` `Event` with a `TicketType` that has `available_quantity > 0` (create one via the admin panel, per feature 003's quickstart)

## 1. Public event page shows live availability

```bash
curl -s http://localhost/events/<event-slug> | jq '.ticket_types'
```

**Expected outcome**: each ticket type shows `price` and `available_quantity` matching what the admin panel's Ticket Types list shows for the same event (contracts/checkout-api.md `GET /events/{event:slug}`).

## 2. Cart won't exceed availability

In the browser: open the event page, try adding more of a ticket type than its `available_quantity`.

**Expected outcome**: the UI blocks it and shows the real remaining count (spec.md FR-002, User Story 1).

## 3. Guest checkout creates a pending order and decrements inventory

```bash
curl -s -X POST http://localhost/checkout -H 'Content-Type: application/json' -d '{
  "transaction_hash": "quickstart-test-1",
  "event_id": "<event-id>",
  "items": [{ "ticket_type_id": "<ticket-type-id>", "quantity": 1 }],
  "name": "Quickstart Guest",
  "email": "quickstart-guest@example.test"
}'
```

**Expected outcome**: `201` with the new order's `id` and `status: pending`; re-querying the ticket type's `available_quantity` shows it decreased by 1; a new `Attendee` row exists for `quickstart-guest@example.test`.

## 4. Duplicate submission is idempotent

Re-run the exact same `curl` command from step 3 (same `transaction_hash`).

**Expected outcome**: `200` (not `201`) with the *same* order `id` as before; `available_quantity` does not decrease a second time (contracts/checkout-api.md, research.md §1).

## 5. WhatsApp and bank-transfer payment instructions

```bash
curl -s http://localhost/orders/<order-id> | jq '.order'
```

Then in the browser, revisit the payment step for that order.

**Expected outcome**: choosing WhatsApp shows a `wa.me` link whose pre-filled text includes the order reference, total, and this same order-status URL; choosing bank transfer shows the configured bank details plus the order reference/total (spec.md User Story 3).

## 6. Staff confirms payment; tickets appear

In the admin panel (`/admin/orders/<order-id>`), as a staff member with order-view access, use the new "Confirm Payment" action.

```bash
curl -s http://localhost/orders/<order-id> | jq '.order.status, .order.tickets'
```

**Expected outcome**: order status is now `paid`; `tickets` is a non-empty array, one entry per unit purchased, each with a working `pdf_url`.

## 7. Ticket PDF downloads and is inspectable

```bash
curl -s http://localhost/orders/<order-id>/tickets/<ticket-id>/pdf -o ticket.pdf
```

**Expected outcome**: a valid PDF containing the event name, ticket type, attendee name, and a QR code (spec.md FR-014).

## 8. Pending order expires after 24 hours and releases inventory

```bash
php artisan tinker --execute="App\Models\Order::latest()->first()->update(['created_at' => now()->subHours(25)])"
php artisan orders:expire-pending
```

```bash
curl -s http://localhost/orders/<order-id> | jq '.order.status'
```

**Expected outcome**: order status is now `expired`; the ticket type's `available_quantity` increased back by the order's quantity; re-running the same "Confirm Payment" action in the admin panel is refused (FR-012, FR-017).

## 9. Proof-of-payment upload

```bash
curl -s -X POST http://localhost/orders/<pending-order-id>/proof-of-payment -F 'file=@receipt.jpg'
```

Then view that order in the admin panel.

**Expected outcome**: `200 OK`; the uploaded file is visible to staff on the order's detail view before they confirm payment (FR-019, FR-020).

## Success criteria mapping

| Spec success criterion | Validated by step |
|---|---|
| SC-001 (checkout under 3 minutes) | Steps 1–3, timed manually |
| SC-002 (zero oversold tickets) | Step 2 (UI) + the existing `TicketTypeOversellTest` concurrency coverage this feature's order-submission path reuses |
| SC-003 (idempotent duplicate submissions) | Step 4 |
| SC-004 (every order reaches a clear state) | Steps 3, 5, 6, 8 (pending → paid or expired, never stuck) |
| SC-005 (ticket available immediately on confirmation) | Step 6 |
| SC-006 (mobile/desktop parity) | Manual pass in both a mobile viewport and desktop, steps 1–7 |
