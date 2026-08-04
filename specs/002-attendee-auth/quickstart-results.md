# Quickstart & Accessibility Validation Results

Executed against a local `php artisan serve` instance backed by the dev MySQL database, using
Guzzle (in a scratch PHP script) in place of `curl` for steps 2–7 — equivalent HTTP requests,
same middleware/exception pipeline, same assertions. Step 8 was exercised in a real Chrome
browser via browser automation. All 92 Pest tests (57 feature-001 + 35 feature-002) pass
independently of this run.

## Steps 1–7 (contracts/auth-api.md + data-model.md validation)

| Step | Result |
|---|---|
| 1. Schema | PASS — `sessions.user_id` is `char`; `attendees` has `password`/`email_verified_at`/`remember_token` |
| 2. Register | PASS — `201`, `email_verified_at` null, notification queued (confirmed via `Notification::assertSentTo` in RegistrationTest and, separately, a real `jobs` row when `queue.default=database`) |
| 3. Login before verification | PASS — `423` with `resend_available: true` |
| 4. Verify + login | PASS — signed link redirects to `/auth/login?verified=1`; repeat login returns `200` with attendee payload; `attendee_login` audit row's `auditable_id` matches |
| 5. Failed login audited | PASS — `422` generic; `attendee_login_failed` row has `changes.reason = "invalid_credentials"` |
| 6. Login throttle | PASS — `429` observed (limiter is cumulative per-IP across the whole script run, so it fired on the loop's 3rd call rather than exactly the 6th overall attempt — same 5/minute enforcement, just counted from an earlier baseline than the doc's clean-slate framing assumes) |
| 7. Password reset end-to-end | PASS — `forgot-password` always `200`; `reset-password` `200`; attendee's `sessions` rows deleted (0 remaining); old password rejected (`422`), new password accepted (`200`) on subsequent login |

**Finding — quickstart.md's example email domain was stale**: the doc used `quickstart@example.com`, but `example.com`/`.net`/`.org` now publish an RFC 7505 "null MX" record (explicit "accepts no mail," not merely "no MX"), which correctly fails FR-002's DNS check. Fixed in `quickstart.md` to use `quickstart@gmail.com` (a domain with genuine deliverable MX records), with a note explaining why.

## Step 8 — Frontend screens (WCAG 2.1 AA accessibility pass)

Verified all three screens (`/auth/register`, `/auth/login`, `/auth/forgot-password`, including
the token-bearing reset-password state) in a real browser: brand palette and typography render
correctly, inline per-field `422` errors appear without a page reload, and the `423` unverified
case shows a distinct message with a working resend action — confirmed end-to-end including a
real signed verification link and a real password-reset token.

**Structural / keyboard / screen-reader checks** (via the accessibility tree, not just visual):
- All inputs have programmatically associated `<label>` elements (`htmlFor`/`id`) — confirmed via
  the browser's accessibility tree reporting "Full name", "Email address", etc. as proper
  textbox accessible names, not just placeholder text.
- Every error and hint message is wired via `aria-describedby`; fields with errors also carry
  `aria-invalid`.
- Error banners use `role="alert"`; success/info banners use `role="status"`.
- Focus states use a visible 2px gold ring (`focus:ring-2 focus:ring-gold`) on inputs and
  `focus-visible:ring-2 focus-visible:ring-gold` on buttons — visible under keyboard navigation.
- All interactive elements are standard `<input>`/`<button>`/`<a>` (via TanStack's `<Link>`) — no
  custom widgets requiring extra keyboard handling; full keyboard operability follows for free.

**Color contrast (WCAG 1.4.3, computed against sRGB relative luminance, 4.5:1 minimum for normal
text)** — three real failures were found and fixed:

| Usage | Before | Ratio | After | Ratio | Fix |
|---|---|---|---|---|---|
| Screen "eyebrow" labels (e.g. "CREATE YOUR ACCOUNT") on the white form panel | `text-gold` (#F2A801) | 2.0:1 | `text-deep-purple` | 14.8:1 | Gold kept only on the deep-purple side panel, where it already passes at 7.3:1; white-panel eyebrows switched to deep-purple |
| Body/secondary text (taglines, hint text, "already registered?" links) on white | `text-deep-purple/55` and `/60` | 3.8:1 / 4.4:1 | `text-deep-purple/70` | 6.0:1 | Bumped opacity |
| Inline validation error text | `text-red` (#FF3502) | 3.6:1 | `text-red-text` (#C42B00, new token) | 5.7:1 | Added a darkened text-only variant in `app.css`; the literal brand red (`border-red`, `bg-red`) is unchanged for non-text accents, where the looser 3:1 non-text threshold already applies and is met |

**Known lower-severity item, not fixed**: link `hover:text-gold` states (e.g. "Sign in" on
hover) briefly present at ~2.0:1 contrast during the hover interaction itself. Left as-is because
the link's accessible identity doesn't depend on color at any point — it's underlined
(`decoration-gold`, later `underline`) and contextually identifiable in every state, at rest and
on hover alike; only the hover-state text color itself falls under the stricter numeric threshold
and even that is transient. Flagged here rather than silently accepted.

All fixes were verified: `npx tsc --noEmit` clean, `npm run build` succeeds, all 92 Pest tests
still pass, and the pages were re-inspected in the browser after the change (screenshots show the
darker purple eyebrow labels and darker red error text).
