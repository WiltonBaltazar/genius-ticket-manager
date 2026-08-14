# API Contract: Attendee Checkout

Session-based (Laravel `web` middleware group — cookies + CSRF), same-origin, JSON request/response bodies, no `/api/*` prefix — same conventions as `002-attendee-auth/contracts/auth-api.md`. Every endpoint here is reachable while unauthenticated (guest checkout, FR-003); a logged-in attendee session is used opportunistically to pre-fill details, never required.

## GET /events/{event:slug}

Maps to: FR-001, User Story 1. Public — no auth. Route-model-bound by `slug`, not `id` (nicer public URLs; the admin panel already established `slug` as the canonical public identifier for an event, feature 003).

**Dual-purpose** (discovered during implementation): this single URL is both the human-navigable page and the JSON data endpoint, mirroring the existing `/auth/{any?}` SPA-shell pattern. A request that doesn't expect JSON (a real browser navigation) gets the React shell (`view('app')`), which then calls this same URL again via `fetch()` — that second request, with an `Accept: application/json` header, gets the JSON body below. There is no separate `/api/...` path.

**Responses**:
- `200 OK` — Body:
  ```json
  {
    "event": { "id": "...", "name": "...", "slug": "...", "venue": "...", "start_date": "...", "end_date": "...", "description": "...", "hero_image_url": "..." },
    "ticket_types": [
      { "id": "...", "name": "...", "description": "...", "price": "250.00", "available_quantity": 42 }
    ]
  }
  ```
  `ticket_types` includes only non-soft-deleted types with `sales_start_date`/`sales_end_date` (if set) currently open; a type with `available_quantity = 0` is still included (so the UI can show "sold out"), never omitted.
- `404 Not Found` — no event with that slug, or the event's status is not `published` (a `draft`/`closed`/`archived` event is not publicly browsable).

## POST /checkout

Maps to: FR-002 through FR-006, User Story 2. Public — no auth required; if an attendee session exists, `attendee_id` is taken from it and `name`/`email` in the request body are ignored.

**Request body**:
```json
{
  "transaction_hash": "string, required — client-generated idempotency key, research.md §1",
  "event_id": "string, required",
  "items": [
    { "ticket_type_id": "string, required", "quantity": "integer, required, >= 1" }
  ],
  "name": "string, required unless authenticated",
  "email": "string, required unless authenticated, RFC-valid"
}
```

**Responses**:
- `201 Created` — order created in `pending` status; `available_quantity` decremented for every line item (research.md §2). Body: `{ "order": { "id": "...", "status": "pending", "total_amount": "...", "items": [...] } }`
- `200 OK` — a request replaying an already-used `transaction_hash` returns the original order unchanged, same body shape as `201` (research.md §1's idempotency guarantee — never creates a second order).
- `422 Unprocessable Entity` — validation failure, or one or more `items` entries exceeds current `available_quantity` (FR-002). Body: `{ "errors": { "items.0.quantity": ["Only 3 left."] } }` — identifies which line item(s) fell short; the whole submission is rejected, nothing partially created (research.md §2).
- `404 Not Found` — `event_id` or a `ticket_type_id` doesn't exist or doesn't belong to the given event.

## GET /orders/{order}

Maps to: FR-010, FR-014, FR-015, FR-018, User Stories 3–5. Public — no auth; the order's UUID is the access key (research.md §8). Never enumerable (no endpoint lists orders by attendee/email for an unauthenticated caller).

**Dual-purpose**, same pattern as `GET /events/{event:slug}` above: a non-JSON-expecting request (the emailed link opened in a browser) gets the SPA shell; the app's own `fetch()` to the same URL gets the JSON body below.

**Responses**:
- `200 OK` — Body:
  ```json
  {
    "order": {
      "id": "...",
      "status": "pending | paid | expired",
      "total_amount": "...",
      "payment_method": "mpesa | offline | null",
      "created_at": "...",
      "expires_at": "... (only present while status = pending)",
      "proof_of_payment_uploaded": true,
      "items": [{ "ticket_type_name": "...", "quantity": 2, "unit_price": "...", "subtotal": "..." }],
      "tickets": [{ "id": "...", "pdf_url": "/orders/{order}/tickets/{id}/pdf" }]
    }
  }
  ```
  `tickets` is an empty array unless `status = paid` (FR-015).
- `404 Not Found` — no order with that ID.

## POST /orders/{order}/proof-of-payment

Maps to: FR-019, User Story 3. Public — no auth; same access-by-UUID model as above.

**Request body**: `multipart/form-data`, single `file` field (image or PDF, reasonable size cap — exact limit is an implementation detail, mirrors the hero-image upload pattern from feature 003).

**Responses**:
- `200 OK` — file stored, `orders.proof_of_payment_path` updated. Body: `{ "message": "Proof of payment uploaded." }`
- `409 Conflict` — the order is not `pending` (already paid, or expired) — FR-019 only allows this while pending.
- `422 Unprocessable Entity` — missing file, or fails type/size validation.
- `404 Not Found` — no order with that ID.

## GET /orders/{order}/tickets/{ticket}/pdf

Maps to: FR-014, FR-015, User Story 5. Public — no auth; same access-by-UUID model.

**Responses**:
- `200 OK` — `application/pdf` response, generated on demand (research.md §5). Only reachable if `order.status = paid` and `ticket` belongs to `order`.
- `404 Not Found` — order or ticket doesn't exist, ticket doesn't belong to that order, or the order is not paid (no partial/preview PDF for a pending order — FR-015).
