# Phase 1 Data Model: Attendee Authentication

Derived from `spec.md` and `research.md`. This feature modifies one existing table (`attendees`), widens one existing table (`sessions`), and reuses two existing tables (`password_reset_tokens`, `audit_logs`) without schema changes to either. No new tables are created.

## `attendees` (additive migration on top of feature 001)

| Column | Type | Notes |
|---|---|---|
| password | VARCHAR(255) NULL | Hashed (bcrypt, Laravel default). `NULL` means "guest-checkout-only identity, never registered" (research.md's claim-vs-reject distinction, spec.md FR-004/FR-005). |
| email_verified_at | TIMESTAMP NULL | `NULL` until the signed verification link is used. Gates login per FR-008. |
| remember_token | VARCHAR(100) NULL | Laravel's standard "remember me" token column; unused unless a future feature adds a remember-me checkbox — included now because `Authenticatable` expects it. |

No changes to `id`, `name`, `email`, `email_active`, `phone`, `deleted_at`, or their indexes — feature 001's identity/uniqueness model (FR-023) is untouched.

**Model change**: `Attendee` now extends `Illuminate\Foundation\Auth\User` (matching `Staff`'s existing pattern) instead of the plain `Model`, adds `implements MustVerifyEmailContract` explicitly (research.md §3), and casts `password` as `hashed`.

**Validation rules**:
- Registration (FR-001–FR-003): email RFC+DNS valid, name required, phone optional, password meets the strength policy (12–72 chars, mixed case, number, symbol) and matches its confirmation.
- Claim-vs-reject (FR-004/FR-005): if an active `Attendee` row exists for the email with `password IS NOT NULL` → reject as duplicate. If an active row exists with `password IS NULL` → **update that same row in place** (attach `password`, overwrite `name`/`phone` with the submitted values, leave `email_verified_at` null pending verification) — its `id` never changes and its existing `orders` relationship is therefore preserved automatically, since nothing about the row's identity is altered, only its columns. Otherwise → insert a new row.
- The existing `email_active` unique index (feature 001) is the safety net against a race between two simultaneous *new*-email registrations; a duplicate insert attempt surfaces as a caught unique-constraint violation translated into a normal validation error, not a 500.
- Passwords are cast `hashed` (FR-024) at the model layer — the raw submitted value is never persisted, logged, or otherwise retained once hashed.

**State transitions** (`email_verified_at`):
- `NULL → <timestamp>`: set once, when a valid signed verification link is visited. Re-visiting an already-verified link is a harmless no-op (idempotent), per the Edge Cases entry.
- Never reverts to `NULL` within this feature's scope.

## `sessions` (widened, not recreated)

| Column | Change |
|---|---|
| user_id | `BIGINT UNSIGNED NULL` → `CHAR(36) NULL` (or an equivalent portable string type), index preserved |

**Rationale**: see research.md §2. This is a `Schema::table('sessions', ...)->change()` migration, not a drop/recreate — the table may already hold rows (e.g., guest/unauthenticated sessions) that must survive the column-type change.

**Validation rules**: none beyond what Laravel's session handler already enforces; this column is framework-managed, not application-validated.

## `password_reset_tokens` (reused as-is)

No schema change. `email` (primary key), `token` (hashed reset token), `created_at`. Consumed via the new `'attendees'` broker entry in `config/auth.php` (research.md §4).

**State transitions**: a row is created/overwritten when a reset is requested (FR-016), consumed (deleted) when the reset completes successfully, and treated as expired — without needing deletion — once `created_at` is older than the broker's `expire` window (FR-018/FR-019). Because `email` is this table's primary key, at most one outstanding reset token can ever exist per address: a second reset request simply overwrites the first token row, silently invalidating whatever link was emailed first (the old link's token no longer matches what's stored, so it fails the broker's hash comparison). No separate "invalidate the other token" logic is needed — the schema's own primary key enforces it.
- If the reset is completed with a token whose `Attendee` was soft-deleted after the request but before completion, the broker's user lookup (which respects the default soft-delete scope) finds no matching account and the reset fails the same generic way an invalid/expired token would — no special-case handling required.

## `audit_logs` (reused as-is, new `action` values only)

No schema change. Two new `action` string values are introduced, using the existing polymorphic `auditable_type`/`auditable_id` pair pointed at the `Attendee` row itself (research.md §6):

| action | auditable_type | auditable_id | staff_id | changes | Written when |
|---|---|---|---|---|---|
| `attendee_login` | `Attendee` | the attendee's id | `null` | `null` | A login succeeds (FR-013) |
| `attendee_login_failed` | `Attendee` | the attendee's id | `null` | `{"reason": "invalid_credentials"}` or `{"reason": "unverified_email"}` | A login attempt fails against a **real, identifiable** account (FR-014) |

Failed attempts against an email with no matching `Attendee` row at all are **not** written to `audit_logs` (there is no valid, non-null `auditable_id` to reference) — they are still caught by the IP-based login throttle (below), per research.md §6's scope note.

Per FR-014, the `changes` column for these rows is restricted to the fixed `{"reason": "..."}` shape shown above — the submitted password is never placed in `changes`, `action`, or any other column, on any code path, successful or failed.

## Rate limiting (not a database table — Laravel's cache-based limiter)

| Limiter | Scope | Rate | Backing store |
|---|---|---|---|
| `login` | Per IP address | 5/minute | Laravel's default cache driver (already `database`, per feature 001's `.env`) |
| `password-reset` (existing broker throttle) | Per email (broker-native) | 1/minute (60s) | `password_reset_tokens.created_at` comparison — no separate cache entry needed |

## Entity-Relationship Summary (delta from feature 001)

```
Attendee (existing, feature 001) ──gains──> password, email_verified_at, remember_token
                                   ──uses──> password_reset_tokens (email-keyed, no FK)
                                   ──uses──> sessions (user_id widened to string)
                                   ──referenced-by (polymorphic)──> AuditLog (action=attendee_login[_failed])
```

**Output**: This data model, combined with `research.md`, fully specifies every schema change this feature needs. Proceed to `contracts/` for the HTTP interface this feature exposes, then `quickstart.md` for the validation guide.
