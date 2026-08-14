# Phase 1 Data Model: Attendee Ticket Checkout

No new tables. This feature is the first to actually write to `orders`/`order_items`/`tickets` through normal use (feature 001 only created the schema; feature 003 only reads it). One enum case is added; everything else is exercising columns that already exist.

## `orders` (existing, feature 001 — no schema change)

| Column | Used how by this feature |
|---|---|
| `id` | The unguessable order-status/proof-of-payment access key (research.md §8) |
| `attendee_id` | Set to the logged-in attendee, or a found-or-created guest `Attendee` matched by email (FR-004) |
| `status` | Written for the first time: `pending` at submission, `paid` at staff confirmation, **`expired`** (new case, below) by the scheduled expiry command |
| `transaction_hash` | The client-supplied idempotency key (research.md §1) |
| `payment_method` | `mpesa` \| `offline` (existing `PaymentMethod` enum) — WhatsApp checkout is stored as `offline`, since it is a manual/human-mediated payment, not a distinct processor; the payment step's WhatsApp-vs-bank-transfer choice is a UI/instructions distinction, not a new backend payment method (see Assumptions) |
| `payment_reference` | Left null at submission; unused by this feature (reserved for a future M-Pesa integration) |
| `proof_of_payment_path` | Written by the new upload endpoint (FR-019); nullable, only settable while `status = pending` |
| `total_amount` | Sum of the submitted order items' subtotals |
| `ip_address`, `user_agent` | Captured from the checkout request, matching the existing fraud-signal columns' intent (feature 001) |
| `created_by` | Left null — this feature's orders are attendee-initiated, not staff-created |
| `confirmed_by`, `confirmed_at` | Written by `ConfirmOrderPaymentAction` (research.md §7) |

**New `OrderStatus` case**: `Expired = 'expired'` (research.md §3). Existing cases (`Pending`, `Paid`, `Failed`, `Refunded`, `Cancelled`) are unchanged; this feature only ever writes `Pending`, `Paid`, and `Expired` — `Failed`/`Refunded`/`Cancelled` remain out of this feature's write path (Assumptions).

**Validation rules**:
- `transaction_hash`: required, unique (existing DB constraint) — a duplicate submission with the same value returns the already-created order rather than erroring (research.md §1).
- An order transitions `pending → paid` only via `ConfirmOrderPaymentAction`, and only from `pending` (FR-012).
- An order transitions `pending → expired` only via the scheduled expiry command, and only from `pending`, only once `created_at` is more than 24 hours in the past (FR-017).
- `proof_of_payment_path` is settable only while `status = pending` (FR-019); the upload endpoint rejects the request otherwise.

**State transitions**:

```
pending ──(ConfirmOrderPaymentAction, staff)──> paid
pending ──(orders:expire-pending, >24h old)──> expired
```

No transition out of `paid` or `expired` exists in this feature (un-confirm/refund/cancel are out of scope, per spec.md Assumptions).

Each of these three transitions (created/pending, confirmed/paid, expired) writes one `AuditLog` row against the order via its existing `auditLogs()` relation (research.md §8a) — the constitution's "every payment state change MUST be written to `audit_logs`" requirement, unconditional and separate from feature 003's narrower Event/TicketType-CRUD audit-logging carve-out.

## `order_items` (existing, feature 001 — no schema change)

Created at order submission from the cart's contents: one row per distinct ticket type in the cart, `unit_price` copied from the ticket type's current price, `subtotal = unit_price * quantity`.

## `tickets` (existing, feature 001 — no schema change)

Created by `ConfirmOrderPaymentAction` when an order transitions to `paid` (research.md §4) — one row per unit across all of the order's order items, each with a freshly generated, unique `qr_code`, `status = unused`. Never created for a `pending` or `expired` order.

**Validation rules**:
- `qr_code`: generated server-side (not client input), unique (existing DB constraint), encoded into the downloadable PDF's QR image (research.md §5) — the value scanned at check-in, not the ticket's `id`.

## `ticket_types` (existing, feature 001 — no schema change)

`available_quantity` is decremented at order submission (research.md §2) and incremented back at order expiry (research.md §3), both via the existing optimistic-locking (`version`) conditional-update pattern — no new columns, no new locking mechanism.

## `attendees` (existing, feature 001/002 — no schema change)

Looked up by `email` at checkout submission (FR-004): if a matching record exists (soft-delete-aware, via the existing `email_active` generated column), the order attaches to it; otherwise a new `Attendee` is created with no password and `email_verified_at = null` (a guest record — indistinguishable in shape from an unverified self-registered account, per feature 002's existing schema).

## New configuration values (no schema — `.env`/`config/services.php`)

| Key | Purpose |
|---|---|
| `services.whatsapp.number` | The organization's WhatsApp number for the `wa.me` checkout link (research.md §6) |
| `services.bank_transfer.*` (account name, number, bank, branch — exact keys are an implementation detail) | Displayed on the payment step for the bank-transfer method (spec.md FR-009) |

## Entity-Relationship Summary (delta from features 001–003)

```
Order (existing) ──gains (write path, not schema)──> status now reaches paid/expired in practice
                  ──new OrderStatus case──> Expired

OrderItem (existing) ──gains (write path)──> created at checkout submission

Ticket (existing) ──gains (write path)──> created at payment confirmation, not before

TicketType (existing) ──gains (write path)──> available_quantity actually decremented/incremented
                                                 through normal use for the first time

Attendee (existing) ──gains (write path)──> guest records created via checkout email lookup
```

**Output**: This data model, combined with `research.md`, fully specifies every write path this feature introduces. Proceed to `contracts/` for the concrete request/response shapes, then `quickstart.md` for the validation guide.
