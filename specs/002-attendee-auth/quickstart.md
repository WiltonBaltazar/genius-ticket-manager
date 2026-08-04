# Quickstart: Validating Attendee Authentication

This is a validation guide for the endpoints in `contracts/auth-api.md` and the schema changes in `data-model.md`, not an implementation walkthrough. Run these once the migrations, model changes, actions, controllers, routes, and React screens from `tasks.md` exist.

## Prerequisites

- Everything from feature 001's quickstart.md already working (migrated schema, Pest configured against real MySQL)
- `php artisan migrate` run to apply this feature's two new migrations (`attendees` auth columns, `sessions.user_id` widening)
- Mail configured to a local catch-all (e.g., Laravel's `log` mailer, or Mailpit/Mailtrap) so verification/reset emails can be inspected without a real inbox

## 1. Apply the schema changes

```bash
php artisan migrate
```

**Expected outcome**: `attendees` gains `password`, `email_verified_at`, `remember_token` (all nullable); `sessions.user_id` is now a nullable string/UUID-compatible column, not `BIGINT`.

```bash
php artisan tinker --execute="dd(Schema::getColumnType('sessions', 'user_id'))"
```

**Expected outcome**: a string type (e.g., `string`/`char`), not `bigint`.

## 2. Register a new attendee

Uses `gmail.com` rather than `example.com` — the latter now publishes an RFC 7505 "null MX"
record (an explicit "this domain accepts no mail" signal), which correctly fails FR-002's DNS
check; it is no longer a usable placeholder domain for this validation.

```bash
curl -i -c cookies.txt -b cookies.txt \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -X POST http://localhost/register \
  -d '{"name":"Test Attendee","email":"quickstart@gmail.com","password":"Str0ng!Passw0rd","password_confirmation":"Str0ng!Passw0rd"}'
```

**Expected outcome**: `201 Created`. `Attendee::where('email','quickstart@gmail.com')->first()->email_verified_at` is `null`. A verification email is queued (check the configured mail catcher/log).

## 3. Attempt login before verification

```bash
curl -i -c cookies.txt -b cookies.txt \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -X POST http://localhost/login \
  -d '{"email":"quickstart@gmail.com","password":"Str0ng!Passw0rd"}'
```

**Expected outcome**: `423 Locked` (per `contracts/auth-api.md`), distinct from the `422` used for wrong credentials.

## 4. Verify and log in

Follow the signed link from the queued email (or generate one manually via `URL::temporarySignedRoute(...)` in Tinker for a quick check), then repeat step 3's request.

**Expected outcome**: verification link redirects to `/auth/login?verified=1`; the repeated login request now returns `200 OK` with the attendee payload, and `AuditLog::where('action','attendee_login')->latest()->first()` shows a row referencing this attendee.

## 5. Trigger a failed login and confirm it's audited

Repeat step 3's login request with the wrong password.

**Expected outcome**: `422` generic-credentials error; `AuditLog::where('action','attendee_login_failed')->latest()->first()` shows a row with `changes->reason = "invalid_credentials"`.

## 6. Exercise the login throttle

Send 6 rapid login requests (correct or incorrect, doesn't matter) from the same origin within a minute.

**Expected outcome**: the 6th request returns `429 Too Many Requests` with a `Retry-After` header, per research.md §5's 5/minute limit.

## 7. Password reset end-to-end

```bash
curl -i -X POST http://localhost/forgot-password -H "Content-Type: application/json" -d '{"email":"quickstart@gmail.com"}'
```

**Expected outcome**: `200 OK` every time, including for an email with no account (no enumeration signal). Follow the emailed token, then:

```bash
curl -i -X POST http://localhost/reset-password -H "Content-Type: application/json" \
  -d '{"email":"quickstart@gmail.com","token":"<token-from-email>","password":"NewStr0ng!Pass","password_confirmation":"NewStr0ng!Pass"}'
```

**Expected outcome**: `200 OK`; the session cookie captured in step 4 no longer authenticates (its `sessions` row was deleted/invalidated); logging in again with the *old* password fails, with the *new* one succeeds.

## 8. Frontend screens

Visit `/auth/register`, `/auth/login`, `/auth/forgot-password` in a browser. Confirm: brand palette (deep purple text/labels, gold focus/links, red errors), Barlow/Barlow Condensed typography, inline validation errors appear per-field without a full page reload, and the `423`-locked case from step 3 surfaces a distinct "please verify your email" message with a resend action — not the generic invalid-credentials message.

## Success criteria mapping

| Spec success criterion | Validated by step |
|---|---|
| SC-001 (registration always succeeds; email retried) | Step 2 |
| SC-002 (unverified accounts can't authenticate) | Step 3 |
| SC-003 (every login, success or failure, audited) | Steps 4, 5 |
| SC-004 (password-reset throttle) | Step 7 (repeat rapidly to observe `429`) |
| SC-005 (login throttle) | Step 6 |
| SC-006 (registration UX time) | Step 8, manual timing |
| SC-007 (password reset invalidates prior sessions) | Step 7 |
