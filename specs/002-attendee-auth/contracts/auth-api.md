# API Contract: Attendee Authentication

All endpoints are session-based (Laravel `web` middleware group — cookies + CSRF), same-origin, JSON request/response bodies. The `X-CSRF-TOKEN` header (from the `csrf-token` meta tag) is required on every mutating request per research.md §8. No bearer tokens; no `/api/*` prefix (these are `web`-guarded routes, not stateless API routes).

## POST /register

Maps to: FR-001 through FR-006, User Story 1.

**Request body**:
```json
{
  "name": "string, required",
  "email": "string, required, RFC+DNS valid",
  "phone": "string, optional",
  "password": "string, required, meets strength policy",
  "password_confirmation": "string, required, must match password"
}
```

**Responses**:
- `201 Created` — account created (new or claimed); verification email queued. Body: `{ "message": "Registration successful. Check your email to verify your account." }`
- `422 Unprocessable Entity` — validation failure. Body: standard Laravel validation error shape, `{ "errors": { "password": ["..."], "email": ["..."] } }`. Includes the case from FR-004 (email already has a password-protected account) as an `email` field error.
- `429 Too Many Requests` — IP rate limit exceeded (FR-023, 5/minute). Standard Laravel throttle response with `Retry-After` header, same shape as `/login`'s.

## POST /login

Maps to: FR-008 through FR-012, User Story 2.

**Request body**:
```json
{ "email": "string, required", "password": "string, required" }
```

**Responses**:
- `200 OK` — session created. Body: `{ "attendee": { "id": "...", "name": "...", "email": "..." } }`
- `422 Unprocessable Entity`, generic — incorrect credentials (FR-010). Body: `{ "errors": { "email": ["These credentials do not match our records."] } }` — deliberately identical whether the email exists or not.
- `423 Locked` — **distinct** response for an unverified account (FR-009). Body: `{ "message": "Please verify your email address before logging in.", "resend_available": true }`. Chosen specifically so the frontend can branch on status code alone rather than parsing message text.
- `429 Too Many Requests` — IP rate limit exceeded (FR-012, research.md §5). Standard Laravel throttle response with `Retry-After` header.

## POST /logout

Maps to: FR-015, User Story 4.

**Request body**: none.

**Responses**:
- `204 No Content` — session terminated.
- `401 Unauthorized` — no active session.

## GET /email/verify/{id}/{hash}

Maps to: FR-006, FR-008. Signed URL (research.md §7) — not called directly by the frontend; this is the link inside the verification email.

**Responses** (redirects, not JSON — this is a browser navigation, not a fetch call):
- `302` → `/auth/login?verified=1` — verification succeeded (including the idempotent already-verified case, per Edge Cases).
- `302` → `/auth/login?verification=failed` — invalid or expired (>24h) signature.

## POST /email/verification-notification

Maps to: FR-007 (resend).

**Request body**: `{ "email": "string, required" }`

**Responses**:
- `200 OK` — identical body and status whether the email is unverified (resend actually queued), already verified (no-op), or matches no account at all (no-op) — none of these three cases is distinguishable from the response, per FR-007. Body: `{ "message": "If that account needs verification, a new link has been sent." }`
- `429 Too Many Requests` — resend throttle exceeded (1/minute per origin, FR-007).

## POST /forgot-password

Maps to: FR-016, FR-017, User Story 3.

**Request body**: `{ "email": "string, required" }`

**Responses**:
- `200 OK` — always, regardless of whether the email has an account (FR-016's "without revealing whether that address has an account"). Body: `{ "message": "If that email is registered, a reset link has been sent." }`
- `429 Too Many Requests` — exceeds 1/minute per origin (FR-017).

## POST /reset-password

Maps to: FR-018 through FR-020, User Story 3.

**Request body**:
```json
{
  "email": "string, required",
  "token": "string, required (from the emailed link)",
  "password": "string, required, meets strength policy",
  "password_confirmation": "string, required"
}
```

**Responses**:
- `200 OK` — password updated, all prior sessions for that attendee invalidated (FR-020). Body: `{ "message": "Password reset successfully. Please log in again." }`
- `422 Unprocessable Entity` — invalid/expired token, or password fails the strength policy. Body: `{ "errors": { "token": ["This password reset token is invalid or has expired."] } }` or `{ "errors": { "password": ["..."] } }`.

## Cross-cutting

- Every `422`/`423`/`429` response uses Laravel's standard validation-error JSON shape (`{ "message": "...", "errors": { ... } }`) except where noted, so the React forms can use one shared error-parsing helper (`resources/js/lib/auth.ts`) across all three screens.
- No endpoint ever returns whether a specific email address has an account, except `/register`'s `422` for an already-password-protected email — which is an intentional, spec-approved exception (spec.md Assumptions), not an oversight.
