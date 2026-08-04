# Phase 0 Research: Attendee Authentication

All Technical Context fields were resolvable from the constitution, the existing feature-001 codebase, and Laravel 13's source — no `NEEDS CLARIFICATION` markers remain. Several items below are genuine discoveries made by reading the actual installed code, not just the spec.

## 1. No Sanctum/Breeze/Fortify — plain Laravel auth primitives suffice

**Decision**: Use Laravel's built-in `Authenticatable`, `MustVerifyEmail`, `CanResetPassword`, and the `Password` broker directly, wired to a dedicated `web` guard/provider. No additional Composer package.

**Rationale**: The constitution establishes that `resources/js` is compiled through Laravel's own Vite integration and served same-origin — this is not a cross-domain SPA. Sanctum's `EnsureFrontendRequestsAreStateful` middleware exists specifically to solve stateful auth for a frontend on a *different* (sub)domain than the API; it adds nothing here that plain session + Laravel's standard CSRF cookie doesn't already provide, and the constitution requires justifying any new dependency against the shared-hosting constraint (Principle VI). Breeze/Fortify/Jetstream all assume a Blade or Inertia frontend and would fight the existing TanStack Router SPA rather than help it.

**Alternatives considered**: Laravel Sanctum (rejected — solves a cross-origin problem this app doesn't have); Fortify (rejected — its opinionated Blade/Inertia views and route registration would need to be substantially overridden to serve a separate SPA, adding complexity without benefit); a custom token-based scheme (rejected — spec.md explicitly requires session-based auth under the "web" guard).

## 2. `sessions.user_id` must be widened for UUID-keyed Attendee

**Decision**: A new migration alters `sessions.user_id` from `BIGINT UNSIGNED` (Laravel's default scaffold type, sized for the default `users.id` auto-increment key) to a nullable `CHAR(36)`/string column.

**Rationale**: `SESSION_DRIVER=database` is already configured (feature 001). Laravel's `DatabaseSessionHandler` writes the authenticated user's `getAuthIdentifier()` value into `sessions.user_id` on every request. `Attendee`'s primary key is a UUID string (feature 001's `HasUuids`), which cannot be stored in a `BIGINT` column. This was found by reading `database/migrations/0001_01_01_000000_create_users_table.php` and Laravel's session handler source, not stated anywhere in the request — a genuine schema gap this feature must close.

**Alternatives considered**: Switching `SESSION_DRIVER` to `file` or `cookie` to sidestep the column entirely (rejected — `database` sessions are what feature 001 already configured and what "invalidate all of an attendee's existing sessions" (FR-020) depends on being able to query/delete by user; a file/cookie driver can't be queried and deleted server-side). Giving `Attendee` a secondary auto-increment column just to satisfy this table (rejected — adds a second identity concept to an entity whose UUID-only identity was a deliberate feature-001 decision).

## 3. Email verification expiry, and the `MustVerifyEmail` contract gotcha

**Decision**: Add a `'verification' => ['expire' => 1440]` entry to `config/auth.php` (1440 minutes = 24 hours, per spec.md FR-006). `Attendee` must explicitly `implements MustVerifyEmailContract` — using the `MustVerifyEmail` trait alone is not sufficient.

**Rationale**: Laravel's `VerifyEmail` notification computes its signed-URL expiry via `Config::get('auth.verification.expire', 60)` (default 60 minutes) — reading the notification's source confirms there is no other place this is configured. Separately, `Illuminate\Foundation\Auth\User` (the base class both `Staff` and, per this feature, `Attendee` extend) mixes in the `MustVerifyEmail` *trait* but does not declare `implements Illuminate\Contracts\Auth\MustVerifyEmail` — Laravel's `verified` middleware checks `$user instanceof MustVerifyEmailContract`, so without explicitly declaring the interface on `Attendee` itself, the verification gate (spec.md FR-008) would silently never apply.

**Alternatives considered**: A custom signed-URL implementation (rejected — reinvents what Laravel already provides correctly once the config/interface gaps above are closed).

**Correction (post-`/speckit-analyze`)**: The original version of this decision claimed the built-in notifications could be reused "customized only via config." Verified in vendor source: neither `Illuminate\Auth\Notifications\VerifyEmail` nor `ResetPassword` implements `ShouldQueue` — both send synchronously, which would let a transient SMTP failure abort the registration request, violating FR-006's non-blocking requirement. Resolution: thin queued subclasses (`App\Notifications\Auth\VerifyAttendeeEmail`, `ResetAttendeePassword`) extending the built-ins with `implements ShouldQueue`, dispatched via the existing `database` queue driver; `Attendee` overrides `sendEmailVerificationNotification()` / `sendPasswordResetNotification()` to use them.

## 4. Password reset: reuse the existing `password_reset_tokens` table via a new named broker

**Decision**: Add an `'attendees'` entry to `config/auth.php`'s `passwords` array (`provider: attendees`, `table: password_reset_tokens`, `expire: 60`, `throttle: 60`), and set it as the default broker. No new migration needed for this table.

**Rationale**: `password_reset_tokens` (created by Laravel's default scaffold migration) is keyed by `email` (string primary key) with no foreign key to any specific user table — it is provider-agnostic by design. `expire: 60` (minutes) and `throttle: 60` (seconds) already match spec.md's FR-018 (1 hour) and FR-019 (1/minute) numerically, so no custom values are needed beyond registering the broker itself.

**Alternatives considered**: A dedicated `attendee_password_reset_tokens` table (rejected — unnecessary duplication; the existing table has no attendee-specific data that would require a separate schema).

## 5. Login throttle rate: 5 attempts/minute per IP

**Decision**: Rate-limit the login endpoint to 5 requests/minute per IP address, using Laravel's cache-based rate limiter (`RateLimiter::for('login', ...)`).

**Rationale**: The clarification session settled on IP-based throttling but didn't specify a number. The constitution (Principle II) already establishes "5 requests/minute per IP" as this project's standard for payment/contact endpoints — reusing the same figure for login keeps the codebase's rate-limit posture consistent rather than introducing a second, arbitrary threshold.

**Alternatives considered**: Laravel's Fortify-style default of "5 per minute keyed by email+IP combined" (rejected — the clarification explicitly chose IP-only, not per-account, to avoid a distributed attacker rotating target accounts from evading a per-account limit).

## 6. Reconciling the request's `event_type`/`actor_id` language with feature 001's actual `audit_logs` schema

**Decision**: `action` (not `event_type`) stores the string `'attendee_login'` / `'attendee_login_failed'`. There is no `actor_id` column — the existing polymorphic `auditable_type`/`auditable_id` pair is set to `Attendee::class` / the attendee's own id, since for a self-service login the actor and the subject are the same entity. `staff_id` stays `null` (no staff involved). `ip_address` is a direct match — feature 001's `audit_logs.ip_address` column already exists for exactly this purpose.

**Rationale**: The original request's literal field names (`event_type`, `actor_id`) don't exist in feature 001's already-implemented `audit_logs` table (`action`, and a polymorphic `auditable`/`staff_id` actor model instead). Rather than alter an already-tested, running table, this feature maps the requested behavior onto the existing columns. This preserves feature 001's `audit_logs` schema exactly as built.

**Constraint discovered**: `audit_logs.auditable_id` is `NOT NULL`. A failed login attempt against an email with **no matching Attendee at all** has no valid entity to reference and therefore cannot be written to `audit_logs` without violating that constraint. Per spec.md FR-014's own "(where identifiable)" qualifier, this feature only writes a failed-login audit row when a real `Attendee` record exists to attach it to (wrong password, or correct password but unverified) — attempts against wholly nonexistent emails are still caught and throttled by the IP rate limiter (research.md §5), just not individually audited. This is a scope boundary worth stating plainly, not a gap: auditing an event needs something to audit.

**Alternatives considered**: Adding a nullable `attendee_id` column to `audit_logs` (rejected — unnecessary schema change; the polymorphic pair already models "an action concerning an Attendee" without a redundant second actor column). Making `auditable_id` nullable to allow logging nonexistent-email attempts (rejected — bigger, riskier change to an already-implemented, tested table for a low-value forensic gain the IP throttle already covers).

## 7. Email verification link: signed backend route, then redirect to the SPA

**Decision**: The verification link points at a Laravel-signed backend route (`GET /email/verify/{id}/{hash}`, `signed` + `throttle` middleware). On success, it redirects (not JSON) to a frontend URL (e.g., `/auth/login?verified=1`) so the React app can show a confirmation state; on failure (expired/invalid signature) it redirects to a distinct frontend query state (e.g., `/auth/login?verification=failed`).

**Rationale**: Email verification is inherently a link a user clicks from their inbox, landing outside the SPA's client-side router — the backend must validate the signature before the SPA ever loads. A redirect-with-query-param handoff is the standard pattern for a signed-URL flow feeding into a client-rendered SPA, and requires no additional package.

**Alternatives considered**: Having the email link point directly at a frontend route that then calls an API to verify (rejected — the signed hash must be validated server-side before any React code runs; routing it through the SPA first adds a redundant round-trip for no benefit).

## 8. CSRF for the React SPA: standard session cookie + meta tag, no Sanctum

**Decision**: The root Blade view exposes `<meta name="csrf-token" content="{{ csrf_token() }}">`; `resources/js/lib/auth.ts` reads it and sends it as the `X-CSRF-TOKEN` header on every mutating request. All auth routes live in `routes/web.php` (the `web` middleware group — session, CSRF, cookie encryption already active by default).

**Rationale**: This is the standard mechanism for any same-origin form submission in Laravel and requires nothing beyond what `withRouting(web: ...)` already provides in `bootstrap/app.php`. Consistent with decision §1 (no Sanctum needed).

**Alternatives considered**: Sanctum's `/sanctum/csrf-cookie` endpoint convention (rejected along with Sanctum itself, §1).

---

**Output**: All decisions above resolve directly into `data-model.md` (Attendee column changes, sessions migration) and `contracts/` (endpoint behavior). No open questions remain for Phase 1.
