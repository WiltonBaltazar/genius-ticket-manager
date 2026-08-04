---

description: "Task list for Attendee Authentication"
---

# Tasks: Attendee Authentication

**Input**: Design documents from `/specs/002-attendee-auth/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/auth-api.md, quickstart.md

**Tests**: Included and REQUIRED — the original feature request explicitly enumerates the test cases, and constitution Principle III mandates test-first for auth-gating logic. Every test task is written and failing before its corresponding implementation task.

**Organization**: Tasks are grouped by user story (from spec.md) so each story is an independently testable increment.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no ordering dependency on an incomplete task)
- **[Story]**: Which user story this task belongs to (US1–US4); Setup/Foundational/Polish tasks carry no story label
- File paths are exact and relative to the repository root

## Path Conventions

Single Laravel 13 monolith (per plan.md): backend under `app/`, `config/`, `database/`, `routes/`, `tests/Feature/Auth/`; frontend under `resources/js/` (React 19 + TanStack Router + Tailwind v4, compiled by Laravel's Vite plugin). Constitution v1.1.0 note: the three auth screens are full screens, so their tasks MUST use the `react-builder` and `frontend-design` Claude Code skills together.

---

## Phase 1: Setup

**Purpose**: Auth configuration and the React/TypeScript toolchain (this project's first React code — only Tailwind v4 is currently scaffolded)

- [X] T001 Configure `config/auth.php` — add an `attendees` provider (`eloquent`, `App\Models\Attendee`), point the `web` guard at it, add an `attendees` password broker (`provider: attendees`, `table: password_reset_tokens`, `expire: 60`, `throttle: 60`) set as default broker, and add `'verification' => ['expire' => 1440]` (24h, research.md §3) — leave the scaffold's `users` provider/model/table in place untouched (the future Filament/staff-auth feature will repurpose or replace it; not this feature's concern)
- [X] T002 [P] Register the `login` rate limiter (5 requests/minute keyed by IP, research.md §5) in `app/Providers/AppServiceProvider.php` `boot()`
- [X] T003 [P] Install frontend toolchain — `npm install react react-dom @tanstack/react-router` and `npm install -D typescript @types/react @types/react-dom @vitejs/plugin-react`; add `tsconfig.json` at repo root; update `vite.config.js` to add the React plugin and change the JS input to `resources/js/app.tsx`
- [X] T004 Create the SPA shell — root Blade view `resources/views/app.blade.php` with `<meta name="csrf-token">`, Vite directives, and a `#root` mount div; catch-all route in `routes/web.php` serving it for `/auth/*` paths; `resources/js/app.tsx` entry bootstrapping TanStack Router with a root route tree in `resources/js/routes/__root.tsx` (depends on T003)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema changes, model rework, and shared frontend utilities every user story depends on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T005 [P] Create migration `database/migrations/xxxx_add_authentication_columns_to_attendees_table.php` — add nullable `password` VARCHAR(255), nullable `email_verified_at` TIMESTAMP, nullable `remember_token` VARCHAR(100) to `attendees` (additive; no change to feature 001's columns/indexes, per data-model.md §attendees)
- [X] T006 [P] Create migration `database/migrations/xxxx_change_sessions_user_id_to_uuid_compatible_string.php` — alter `sessions.user_id` from BIGINT to nullable CHAR(36)/string via `->change()`, preserving the index and any existing rows (research.md §2)
- [X] T007 Rework `app/Models/Attendee.php` — extend `Illuminate\Foundation\Auth\User`, explicitly `implements Illuminate\Contracts\Auth\MustVerifyEmail` (research.md §3's trait-vs-interface gotcha), add `Notifiable`, add `password` to `$fillable` and `$hidden`, cast `password` as `hashed` and `email_verified_at` as `datetime`; keep `HasUuids`, `SoftDeletes`, and the existing `orders()` relation untouched (depends on T005)
- [X] T008 [P] Update `database/factories/AttendeeFactory.php` — default state includes a hashed password and `email_verified_at => now()`; add `unverified()` state (`email_verified_at => null`) and `guest()` state (`password => null`, `email_verified_at => null`) for the claim-flow tests
- [X] T009 Run `php artisan migrate` against dev and `--env=testing` databases; verify `Schema::getColumnType('sessions', 'user_id')` reports a string type and `attendees` has the three new columns (depends on T005, T006)
- [X] T010 [P] Create `resources/js/lib/auth.ts` — fetch wrapper sending `X-CSRF-TOKEN` from the meta tag and `Accept: application/json`, plus a shared parser for Laravel's `422`/`423`/`429` error shapes per contracts/auth-api.md §Cross-cutting
- [X] T011 [P] Define brand design tokens in `resources/css/app.css` via Tailwind v4 `@theme` — deep purple `#3C0D5F`, gold `#F2A801`, gold-hover `#D88A00`, red `#FF3502`, plus Barlow / Barlow Condensed / Playfair Display font families (constitution Design Tokens table); replace the scaffold's Instrument Sans font entry in `vite.config.js` with Barlow/Barlow Condensed

**Checkpoint**: Schema, model, config, and frontend foundations ready — user story work can begin.

---

## Phase 3: User Story 1 - New Attendee Can Register (Priority: P1) 🎯 MVP

**Goal**: Registration with validation (RFC+DNS email, 12+ char mixed password), create-or-claim semantics, and a queued 24h verification email (spec FR-001–FR-007, SC-001)

**Independent Test**: POST valid data to `/register` → `201`, unverified account exists, verification email queued; weak password / duplicate email → `422`; passwordless guest record → claimed, not duplicated

### Tests for User Story 1 ⚠️ write first, must fail

- [X] T012 [P] [US1] Pest feature test `tests/Feature/Auth/RegistrationTest.php` — covers: successful registration returns `201` with unverified account and queued verification notification (`Notification::fake()` + assert the notification class implements `ShouldQueue`); weak password rejected `422`; a password over 72 characters rejected `422` (FR-003); mismatched confirmation rejected `422`; invalid-DNS email rejected `422`; an email whose domain has only an A/AAAA record (no MX) is accepted (FR-002 implicit-MX fallback); duplicate email (existing account **with** password) rejected `422`; existing **passwordless** guest record is claimed — same `id` retained, its `orders` still resolve, password attached, no second row (data-model.md claim-vs-reject); the stored `password` column is a bcrypt hash, never the raw submitted value (FR-024); registration succeeds even when mail sending would fail (queued, not blocking, FR-006); 6th rapid registration attempt from one IP → `429` (FR-023)

### Implementation for User Story 1

- [X] T013 [P] [US1] Create `app/Http/Requests/Auth/RegisterAttendeeRequest.php` — rules: `name` required string; `email` required, `email:rfc,dns` (Laravel's `dns` check already implements RFC 5321's implicit-MX-via-A/AAAA fallback, satisfying FR-002 with no custom code); `phone` nullable string; `password` required, confirmed, `max:72`, `Password::min(12)->mixedCase()->numbers()->symbols()` (FR-003); plus the duplicate-email check that treats a passwordless active row as claimable rather than a violation
- [X] T014 [US1] Create `app/Actions/Auth/RegisterAttendeeAction.php` — create-or-claim per data-model.md §attendees (claim = update existing passwordless row's password/name/phone, keep `id` and orders; else insert), then `$attendee->sendEmailVerificationNotification()` (dispatches the queued notification from T037); wrap the claim/insert in a transaction and translate a raced unique-constraint violation into a validation error (depends on T007, T037)
- [X] T015 [US1] Create `app/Http/Controllers/Auth/RegisteredAttendeeController.php` (thin — delegates to the action, returns `201` per contracts/auth-api.md §POST /register) and register `POST /register` in `routes/web.php` behind a `throttle:` limiter (5/min per IP, FR-023) (depends on T013, T014)
- [X] T016 [US1] Build the registration screen — `resources/js/routes/auth/register.tsx` (TanStack route at `/auth/register`) and `resources/js/components/auth/RegisterForm.tsx` with name/email/phone/password/confirm fields, inline per-field `422` errors, success state pointing to "check your email" — **using the `react-builder` + `frontend-design` skills together** (constitution v1.1.0), brand tokens from T011, WCAG 2.1 AA labels/focus states (depends on T004, T010, T011)

**Checkpoint**: Registration works end-to-end — the MVP increment.

---

## Phase 4: User Story 2 - Verified Login Gate (Priority: P1)

**Goal**: Login only for verified accounts, distinct `423` unverified response with resend, generic `422` otherwise, IP throttling, and audit rows for every success and identifiable failure (spec FR-008–FR-014, SC-002/SC-003/SC-005)

**Independent Test**: Login unverified → `423`; verify via signed link → login `200` + `attendee_login` audit row; wrong password → `422` generic + `attendee_login_failed` audit row; 6th rapid attempt → `429`

### Tests for User Story 2 ⚠️ write first, must fail

- [X] T017 [P] [US2] Pest feature test `tests/Feature/Auth/EmailVerificationTest.php` — signed link verifies and redirects to `/auth/login?verified=1`; expired (>24h) or tampered link redirects to `/auth/login?verification=failed`; already-verified link is an idempotent no-op; resend endpoint queues a new notification for unverified accounts and returns an identical `200` body for an unverified account, an already-verified account, and a nonexistent email (FR-007 — three cases, one indistinguishable response); a second resend within 60 seconds → `429` (FR-007's 1/minute)
- [X] T018 [P] [US2] Pest feature test `tests/Feature/Auth/LoginTest.php` — unverified + correct credentials → `423` with `resend_available`; verified + correct → `200` with attendee payload, session established, **session ID differs from the pre-login session ID** (FR-025 fixation guard), exactly one `attendee_login` audit row (correct `auditable`, `ip_address`, `staff_id` null, and `changes` contains no password/credential material — FR-014/FR-024); wrong password on real account → `422` generic + one `attendee_login_failed` audit row with `changes->reason = invalid_credentials` (never the submitted password); nonexistent email → identical `422` body and **no** audit row (research.md §6); 6 rapid attempts from one IP → `429` with `Retry-After`; config-level guard-independence check: `Auth::guard('web')->getProvider()` resolves to the `attendees` provider, and no `staff` guard exists yet in `config/auth.php` (FR-021 — full cross-guard verification is deferred to the future Filament/staff-auth feature, since no staff guard exists to be independent *from* yet)
- [X] T019 [P] [US2] Create `app/Http/Requests/Auth/LoginRequest.php` — `email` required email, `password` required string
- [X] T020 [US2] Create `app/Actions/Auth/AuthenticateAttendeeAction.php` — credential check via the `web` guard; on unverified: fail with the distinct 423 signal and write `attendee_login_failed` (`reason: unverified_email`); on bad credentials for a real account: write `attendee_login_failed` (`reason: invalid_credentials`) and fail generic; on success: regenerate the session ID (session-fixation guard), write `attendee_login`; never include submitted passwords in any audit field (depends on T007)
- [X] T021 [US2] Create `app/Http/Controllers/Auth/AuthenticatedSessionController.php` with `store()` (login per contracts/auth-api.md §POST /login) and register `POST /login` in `routes/web.php` behind `throttle:login` (depends on T019, T020)
- [X] T022 [US2] Create `app/Http/Controllers/Auth/EmailVerificationController.php` — `verify()` on `GET /email/verify/{id}/{hash}` (`signed` + throttle middleware, redirect handoff per research.md §7) and `resend()` on `POST /email/verification-notification` (throttled, non-disclosing per contracts/auth-api.md, dispatches the queued notification from T037); register both routes in `routes/web.php` (depends on T007, T037)
- [X] T023 [US2] Build the login screen — `resources/js/routes/auth/login.tsx` (route at `/auth/login`, reading `?verified=1` / `?verification=failed` query states) and `resources/js/components/auth/LoginForm.tsx` branching on status code: `423` → "verify your email" message with a resend button, `422` → generic inline error, `429` → retry-later notice — **using `react-builder` + `frontend-design` together**, brand tokens, WCAG AA (depends on T004, T010, T011)

**Checkpoint**: Registration + verified login both work independently.

---

## Phase 5: User Story 3 - Password Reset (Priority: P2)

**Goal**: Non-disclosing reset request (1/min throttle), 1-hour tokens, strength-checked new password, all prior sessions invalidated (spec FR-016–FR-020, SC-004/SC-007)

**Independent Test**: POST `/forgot-password` → `200` always; second request within a minute → `429`; valid token resets password, old session cookie stops working, old password fails, new succeeds; expired token → `422`

### Tests for User Story 3 ⚠️ write first, must fail

- [X] T024 [P] [US3] Pest feature test `tests/Feature/Auth/PasswordResetTest.php` — request returns `200` for known **and** unknown emails (no enumeration); repeat within 60s → `429`; end-to-end reset with a real broker token succeeds, deletes the attendee's `sessions` rows and cycles `remember_token` (SC-007); token older than 60 minutes → `422`; weak new password → `422`; old password rejected and new accepted on subsequent logins; a second reset request for the same email overwrites the first token row, so the first emailed link no longer works (data-model.md's single-row-per-email note); a reset attempt whose `Attendee` was soft-deleted after the request but before completion → `422`, same as an invalid token
- [X] T025 [P] [US3] Create `app/Http/Requests/Auth/ForgotPasswordRequest.php` — `email` required email
- [X] T026 [P] [US3] Create `app/Http/Requests/Auth/ResetPasswordRequest.php` — `token` and `email` required; `password` required, confirmed, `max:72`, same `Password::min(12)->mixedCase()->numbers()->symbols()` rule as registration (FR-003 via FR-019)
- [X] T027 [US3] Create `app/Actions/Auth/ResetAttendeePasswordAction.php` — `Password::broker('attendees')` reset; inside the reset callback: set the hashed password, cycle `remember_token` (so remember-me cookies die with the reset), and delete all `sessions` rows for the attendee's id (FR-020, enabled by T006's column widening) (depends on T007)
- [X] T028 [US3] Create `app/Http/Controllers/Auth/PasswordResetController.php` with `store()` (`POST /forgot-password`, `throttle:1,1` per FR-017, always-`200` non-disclosing response, dispatches the queued notification from T037) and `update()` (`POST /reset-password` per contracts/auth-api.md); register both routes in `routes/web.php` (depends on T025, T026, T027, T037)
- [X] T029 [US3] Build the forgot-password screen — `resources/js/routes/auth/forgot-password.tsx` (route at `/auth/forgot-password`, including the token-bearing reset form state) and `resources/js/components/auth/ForgotPasswordForm.tsx` — **using `react-builder` + `frontend-design` together**, brand tokens, WCAG AA (depends on T004, T010, T011)

**Checkpoint**: Stories 1–3 all work independently.

---

## Phase 6: User Story 4 - Logout (Priority: P3)

**Goal**: An attendee can terminate their session on demand (spec FR-015)

**Independent Test**: Authenticated POST `/logout` → `204`; the same session cookie no longer authenticates; unauthenticated POST → `401`

### Tests for User Story 4 ⚠️ write first, must fail

- [X] T030 [P] [US4] Pest feature test `tests/Feature/Auth/LogoutTest.php` — logged-in attendee posts `/logout` → `204`, session invalidated and token regenerated, subsequent authenticated request treated as guest; unauthenticated post → `401`

### Implementation for User Story 4

- [X] T031 [US4] Add `destroy()` to `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (logout: guard logout, session invalidate + token regenerate, `204` per contracts/auth-api.md §POST /logout) and register `POST /logout` in `routes/web.php` behind `auth` middleware (depends on T021)

**Checkpoint**: All 4 user stories independently functional.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Whole-feature validation and consistency with the rest of the codebase

- [X] T032 Run the full Pest suite (`php artisan test`) — all new Auth tests pass AND all 57 feature-001 tests still pass (the `sessions`/`attendees` migrations and `Attendee` model rework must not regress them)
- [X] T033 [P] Execute `specs/002-attendee-auth/quickstart.md` steps 1–8 end-to-end and record results in `specs/002-attendee-auth/quickstart-results.md`
- [X] T034 [P] Accessibility pass on the three screens against WCAG 2.1 AA — form labels/`aria-describedby` on inline errors, visible gold focus states, full keyboard operability — recording findings in `specs/002-attendee-auth/quickstart-results.md`
- [X] T035 Run Pint (`vendor/bin/pint`) on all new/modified PHP files and Prettier on `resources/js`, per constitution Principle I's formatting gate
- [X] T036 Re-validate `specs/002-attendee-auth/checklists/security.md` — check off items this implementation resolves (e.g., CHK001 hashed cast, CHK008 max password length behavior, CHK009 session regeneration in T020; CHK002/CHK005/CHK006/CHK016/CHK017 were already resolved during planning) and flag any still-open requirements-level items for follow-up

### Also required during Phase 2 (Foundational) — added by `/speckit-analyze`

Appended here (rather than inserted mid-sequence) to avoid renumbering T001–T036, but **T037–T039 all belong to Phase 2 timing-wise**: T037 is a hard blocker for T014/T022/T028; T038 and T039 are independent hardening tasks with no dependents but are foundational in nature (config-level, not story-specific) — see Dependencies & Execution Order below.

- [X] T037 [P] Create queued notification subclasses `app/Notifications/Auth/VerifyAttendeeEmail.php` and `app/Notifications/Auth/ResetAttendeePassword.php` (extend Laravel's built-ins, `implements ShouldQueue`), and override `sendEmailVerificationNotification()` / `sendPasswordResetNotification()` on `app/Models/Attendee.php` to dispatch them via the existing `database` queue driver (FR-006; research.md §3 correction — the built-ins send synchronously) — required by T014, T022, T028
- [X] T038 Configure trusted proxies in `bootstrap/app.php` (`->withMiddleware` `trustProxies`) appropriate to the shared-hosting deployment so IP-based throttling (FR-012, FR-023) and audit `ip_address` values reflect the real client, not a spoofable forwarded header; document the production proxy assumption in `docs/deployment-runbook.md`
- [X] T039 [P] Set `SESSION_SECURE_COOKIE=true` in `.env`/`.env.example` for non-local environments and confirm `config/session.php`'s `http_only` (already `true` by default) and `same_site` (already `lax` by default) settings satisfy FR-026; document the production HTTPS-cookie requirement in `docs/deployment-runbook.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately; T004 depends on T003
- **Foundational (Phase 2)**: Depends on Setup; T007 depends on T005; T009 depends on T005+T006; blocks all user stories
- **User Stories (Phases 3–6)**: All depend on Foundational; US4 (T031) additionally depends on US2's T021 (same controller file)
- **T037–T039 (Added by `/speckit-analyze` remediation, listed after Phase 7)**: despite their position and numbers, all three belong to Phase 2 (Foundational) timing. T037 is a hard blocker for T014 (US1), T022 (US2), and T028 (US3) — T012's test also can't correctly assert `implements ShouldQueue` until T037's classes exist. T038/T039 have no downstream task dependents but are config-level hardening, not story-specific, so there's no reason to defer them to Polish.
- **Polish (Phase 7)**: Depends on all user stories

### User Story Dependencies

- **US1 (P1)**: Independent — the MVP
- **US2 (P1)**: Independent of US1's implementation (its tests create verified/unverified attendees via the factory, not via the register endpoint)
- **US3 (P2)**: Independent (broker + factory-created attendees)
- **US4 (P3)**: Shares `AuthenticatedSessionController` with US2 — implement after T021 (or coordinate on the same file)

### Within Each User Story

- Test task first, confirmed failing, before any implementation task (constitution Principle III)
- Form Requests before Actions before Controllers/routes before screens

### Parallel Opportunities

- T002, T003 in parallel after T001; T005, T006, T008, T010, T011, **T037, T038, T039** in parallel (all belong here despite their task numbers/position — see Dependencies above)
- All `[P]` test tasks across stories can be written in parallel once Foundational is done
- US1, US2, US3 phases can proceed in parallel across developers (different files); US4 waits for T021

---

## Parallel Example: User Story 2

```bash
# Write both US2 test files and the Form Request together:
Task: "EmailVerificationTest in tests/Feature/Auth/EmailVerificationTest.php"
Task: "LoginTest in tests/Feature/Auth/LoginTest.php"
Task: "LoginRequest in app/Http/Requests/Auth/LoginRequest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1 (Setup) → Phase 2 (Foundational) → **T037** (queued notifications — required before US1's T014 will work correctly, despite its task number/position; see Dependencies above)
2. Phase 3 (US1 Registration) — test first (T012), then T013–T016
3. **STOP and VALIDATE**: registration + claim flow + queued verification email all green
4. Deliver/demo the registration screen as the MVP increment

### Incremental Delivery

1. Setup + Foundational → schema/model/toolchain ready
2. US1 → registration works (MVP)
3. US2 → verification gate + audited login
4. US3 → password reset end-to-end
5. US4 → logout
6. Polish → full-suite regression check (including feature 001), quickstart run, a11y pass, formatting, security-checklist reconciliation

### Parallel Team Strategy

After Foundational: Developer A takes US1, B takes US2, C takes US3 — no shared files between those three. US4 is a small follow-on to US2 for whoever finishes first.

---

## Notes

- [P] tasks = different files, no ordering dependency
- Every test task must fail before its implementation tasks, per constitution Principle III
- All three screen tasks (T016, T023, T029) are full screens and therefore MUST combine the `react-builder` and `frontend-design` skills per constitution v1.1.0
- The 30-item `checklists/security.md` formal gate is intentionally reconciled at the end (T036) — items that are requirements-level (not implementation-level) may need spec edits before `/speckit-implement`'s checklist gate
- FR-021 (attendee/staff guard independence) is only partially verifiable today — no staff guard exists yet in `config/auth.php` (Filament/staff-auth arrives in a later feature). T018's config-level check confirms the attendee side is correctly isolated; full cross-guard verification is deferred to that future feature.
- Commit after each task or logical group
