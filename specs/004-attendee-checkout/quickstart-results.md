# Quickstart Results: Attendee Checkout

Run 2026-08-14 against a local `php artisan serve` + `npm run dev` environment, seeded via
`php artisan migrate:fresh --force` plus a tinker-seeded staff account and one published event
with a ticket type.

| Step | Result | How verified |
|---|---|---|
| 1. Live availability on event page | ✅ Pass | Live browser: event page ticket type list matched admin panel's `available_quantity` |
| 2. Cart won't exceed availability | ✅ Pass | `tests/Feature/Schema/TicketTypeOversellTest.php` (concurrency) + `TicketTypeSelector`'s `atLimit` disables the add button once `inCart >= availableQuantity` |
| 3. Guest checkout creates pending order, decrements inventory | ✅ Pass (after fix) | Live browser: submitted a real order end-to-end. First attempt hit a 500 — see Bugs Found below; fixed and re-verified |
| 4. Duplicate submission is idempotent | ✅ Pass | `tests/Feature/Checkout/OrderSubmissionIdempotencyTest.php` |
| 5. WhatsApp and bank-transfer payment instructions | ✅ Pass | Live browser: order-status page's WhatsApp button opened a `wa.me` link pre-filled with order ID, total, and the order-status URL; bank-transfer tab showed the configured account details |
| 6. Staff confirms payment; tickets appear | ✅ Pass (after fix) | Live browser: clicked "Confirm Payment" in `/admin/orders/{id}`, order flipped to paid, order-status page showed "Payment confirmed" with 2 ticket links. First attempt hit a `TypeError` — see Bugs Found below; fixed and re-verified |
| 7. Ticket PDF downloads and is inspectable | ✅ Pass | Live browser + `curl`: downloaded a real ticket PDF, inspected with `pdftotext` — contained event name, ticket type, attendee name, venue, date, and the ticket's QR code value |
| 8. Pending order expires after 24h, releases inventory | ✅ Pass | `tests/Feature/Checkout/OrderExpiryTest.php` |
| 9. Proof-of-payment upload | ✅ Pass | `tests/Feature/Checkout/ProofOfPaymentUploadTest.php` |

Full automated suite: 163/163 passing, 500 assertions (`php artisan test`). `vendor/bin/pint --test` clean.

## Bugs found and fixed during this run

Both were caught only by clicking through the real flow in a browser — neither showed up in the
test suite, since one is environment-specific and the other was a genuine test-coverage gap
(closed below).

1. **Dev database schema drift** — checkout submission 500'd with `Unknown column
   'payment_method' in field list`. The original Stripe→M-Pesa migration edit (earlier in this
   feature's history) modified an already-applied migration file in place; the dev DB's
   migration tracker doesn't detect content changes to a migration it already ran. Fixed with
   `php artisan migrate:fresh --force` (disposable local data) and re-seeding. No code change —
   a reminder that migration edits-in-place only affect environments that haven't run them yet.

2. **Filament reserved `$action` parameter name** — clicking "Confirm Payment" threw
   `TypeError: Argument #1 ($action) must be of type ConfirmOrderPaymentAction, Action given`.
   Filament always binds a closure parameter named `$action` to its own `Action` component,
   regardless of the declared type hint. Fixed in `app/Filament/Resources/Orders/Pages/ViewOrder.php`
   by renaming the injected parameter to `$confirmOrderPaymentAction`. Added
   `tests/Feature/Filament/OrderConfirmPaymentPolicyTest.php`'s
   "actually confirms payment when the ConfirmPayment action is invoked through the admin panel"
   test, which calls the action via `Livewire::test(...)->callAction('confirmPayment')` instead
   of only checking button visibility — this is the test that would have caught the bug, and now
   does.

## Accessibility spot-check (WCAG 2.1 AA)

Static review of the four new public-facing components/routes
(`TicketTypeSelector`, `CheckoutDetailsForm`, `PaymentMethodStep`, `OrderStatus`, and the
`events/$slug`, `checkout`, `orders/$orderId` routes):

- Found and fixed two real gaps in `PaymentMethodStep.tsx`:
  - The WhatsApp/bank-transfer toggle buttons had no exposed selected state — added
    `aria-pressed` and a `role="group"` wrapper.
  - The proof-of-payment file input's `<label>` wasn't programmatically associated with its
    `<input>` — added matching `htmlFor`/`id`. Also added `role="alert"` to the upload-error
    message for consistency with the rest of the flow's error handling.
- Everything else already followed the patterns established in the attendee-auth feature:
  `role="alert"` on form errors, `role="status"` on order-state banners, `focus-visible` rings
  on primary actions, `aria-label`s on the icon-only cart +/− buttons, and empty `alt=""` on the
  purely decorative event hero image (the event name is already present as visible text).
- Mobile-viewport parity: all new pages are single-column (`max-w-xl`/`max-w-2xl` centered,
  `px-6` padding) with no fixed-width elements, so there is nothing to reflow between mobile and
  desktop — confirmed no horizontal scroll at a 375px-wide viewport.
