# Implementation Plan: Attendee Authentication

**Branch**: `002-attendee-auth` (working on `main`; no feature-specific git branch was created — no branch-creation hook configured) | **Date**: 2026-08-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-attendee-auth/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Add self-service authentication for attendees — registration, email verification, login, logout, and password reset — against the existing `attendees` table (feature 001), using Laravel's built-in auth primitives (`Authenticatable`, `MustVerifyEmail`, `CanResetPassword`, the `Password` broker) under a dedicated `web` guard kept fully independent from staff/admin access. Includes IP-based login throttling, full audit logging of both successful and failed login attempts, and the corresponding React registration/login/forgot-password screens built to brand spec.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13), TypeScript/React 19 for the frontend

**Primary Dependencies**: Laravel 13 (`Illuminate\Auth`, `Illuminate\Auth\Passwords` broker, Notifications), Pest (test runner); React 19 + TanStack Router + Tailwind CSS v4 for the three new screens. No new Composer/npm package is required — Laravel's built-in auth scaffolding covers every requirement in spec.md without Sanctum, Breeze, Fortify, or Jetstream (see research.md §1 for why).

**Storage**: MySQL 8.0+, InnoDB (existing). Adds authentication columns to `attendees` via a new migration (not a rewrite of feature 001's migration) and widens the existing `sessions.user_id` column to accept UUID keys (see research.md §2).

**Testing**: Pest feature tests per constitution Principle III — one test class per flow (registration, verification, login incl. throttling, password reset), hitting real HTTP routes against the same MySQL test database feature 001 established (`genius_ticket_manager_test`), run inside the existing `DatabaseTransactions` wrapper (no concurrency concerns here, unlike feature 001's oversell test).

**Target Platform**: Shared hosting (PHP-FPM), same-origin React SPA served through Laravel's Vite integration — no separate frontend domain, so no cross-origin session/CSRF handling is needed (constitution Principle VI: no new infrastructure).

**Project Type**: Web application — single Laravel monolith (this feature adds controllers, Form Requests, action classes, migrations, model changes, and React screens; no new services/infrastructure).

**Performance Goals**: No new latency targets beyond spec.md's SC-006 (registration completable in under 2 minutes, a UX metric, not a server-response-time metric).

**Constraints**: No Sanctum/token-based API auth (session-based `web` guard only, per spec.md and existing same-origin architecture); no Redis (throttling uses Laravel's default cache-based rate limiter against the existing `database` cache/queue drivers, per constitution Principle VI); email delivery must not block registration (spec.md FR-006 — queued/retried, not synchronous-and-blocking).

**Scale/Scope**: Same scale envelope as feature 001 (low thousands of attendees) — 3 new screens, 7 new HTTP endpoints (contracts/auth-api.md), 1 model change, 2 migrations.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Applies to this feature? | Status |
|---|---|---|
| I. SOLID Architecture & Clean Code | Yes — this feature has real business logic for the first time in this project | ✅ PASS (by design, enforced in Project Structure below): registration/authentication/password-reset logic lives in `app/Actions/Auth/*`, controllers only orchestrate, Form Requests own validation, React auth components stay small and use a shared `useAuth` hook rather than duplicating fetch logic |
| II. Security by Design | Yes, fully | ✅ PASS (by design): Form Request server-side validation on every mutating endpoint; CSRF via Laravel's standard `web` middleware group (session + `VerifyCsrfToken`, no client-side-only checks); login and password-reset endpoints rate-limited (spec.md FR-012, FR-017); passwords hashed via Laravel's default bcrypt hashing (never stored/logged in plain text); every successful *and* failed login written to the immutable `audit_logs` table (spec.md FR-013, FR-014) |
| III. Test-First for Booking-Critical Paths | Partially — auth isn't itself a "booking-critical path" (ticket selection/checkout/payment/inventory/check-in) per the principle's own enumeration, but the principle's spirit and spec.md's explicit test list still require full coverage | ✅ PASS: Pest feature tests for every acceptance scenario in spec.md (registration success/failure, verification gate, login success/failure/throttling, password reset end-to-end), written before the corresponding controller/action per the constitution's test-first requirement |
| IV. Data Integrity & Immutable Audit Trail | Yes | ✅ PASS: new `attendees` columns added via an additive migration (UUID PK convention preserved, no change to feature 001's existing columns); failed/successful login audit rows use the existing append-only `audit_logs` table and its polymorphic `auditable` reference (no schema change needed there) |
| V. Accessible, On-Brand Experience | Yes — this feature's first UI | ✅ PASS (by design): registration/login/forgot-password screens (FR-022) meet WCAG 2.1 AA and use the brand palette and Barlow/Barlow Condensed typography per spec.md's Assumptions (sourced from the original request), elaborated in tasks.md T011, and — per the constitution's amended Frontend & Public Site guidance — are built using the `react-builder` and `frontend-design` Claude Code skills together, since these are full screens, not isolated components |
| VI. Shared-Hosting-Compatible Simplicity | Yes | ✅ PASS: no Sanctum, no Redis, no new queue/cache infrastructure — rate limiting uses Laravel's cache-based limiter against the existing `database` cache driver, email verification/reset use the existing `database` queue driver already configured in feature 001 |

No unjustified violations. Complexity Tracking table below is intentionally empty.

**Post-Phase-1 re-check**: `data-model.md`, `contracts/auth-api.md`, and `research.md` were reviewed against this table after Phase 1 design. Two design choices are worth surfacing explicitly rather than treating as automatic:
- Reusing `audit_logs`'s polymorphic `auditable` pair as the attendee-login actor reference (research.md §6), instead of adding a dedicated actor column, keeps Principle IV's immutable-audit-trail guarantee intact without altering an already-implemented table — a schema-minimalism choice, not a compliance gap.
- The decision to skip Sanctum (research.md §1) is a Principle VI (shared-hosting simplicity) call, not a security shortcut — CSRF and session security are still fully enforced via Laravel's standard `web` middleware group.

Gate remains PASS; no Complexity Tracking entries needed.

## Project Structure

### Documentation (this feature)

```text
specs/002-attendee-auth/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command) — this feature DOES expose
│                          an interface (HTTP endpoints), unlike feature 001
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

Same single Laravel 13 monolith as feature 001; this feature adds real application logic (the first in this project) alongside the first React screens.

```text
app/
├── Models/
│   └── Attendee.php                          # MODIFIED: Authenticatable, MustVerifyEmail, CanResetPassword
├── Actions/
│   └── Auth/
│       ├── RegisterAttendeeAction.php         # create-or-claim + dispatch verification email
│       ├── AuthenticateAttendeeAction.php     # credential + verified-email check, session, audit log
│       └── ResetAttendeePasswordAction.php    # apply new password, invalidate other sessions
├── Http/
│   ├── Controllers/Auth/
│   │   ├── RegisteredAttendeeController.php
│   │   ├── AuthenticatedSessionController.php  # login (store) + logout (destroy)
│   │   ├── EmailVerificationController.php     # verify (signed route) + resend
│   │   └── PasswordResetController.php         # forgot-password (store) + reset (update)
│   └── Requests/Auth/
│       ├── RegisterAttendeeRequest.php
│       ├── LoginRequest.php
│       ├── ForgotPasswordRequest.php
│       └── ResetPasswordRequest.php
└── Notifications/
    └── Auth/
        ├── VerifyAttendeeEmail.php      # extends built-in VerifyEmail + implements ShouldQueue (FR-006)
        └── ResetAttendeePassword.php    # extends built-in ResetPassword + implements ShouldQueue

database/
├── migrations/
│   ├── xxxx_add_authentication_columns_to_attendees_table.php
│   └── xxxx_change_sessions_user_id_to_uuid_compatible_string.php

config/
└── auth.php                                   # MODIFIED: web guard → attendees provider,
                                                  new "attendees" password broker, verification.expire

routes/
└── web.php                                     # MODIFIED: adds the auth routes below

resources/js/
├── routes/
│   └── auth/                                   # TanStack Router route definitions
│       ├── register.tsx   (/auth/register)
│       ├── login.tsx      (/auth/login)
│       └── forgot-password.tsx (/auth/forgot-password)
├── components/auth/
│   ├── RegisterForm.tsx
│   ├── LoginForm.tsx
│   └── ForgotPasswordForm.tsx
└── lib/
    └── auth.ts                                  # fetch wrappers + CSRF-token handling

tests/
└── Feature/Auth/
    ├── RegistrationTest.php
    ├── EmailVerificationTest.php
    ├── LoginTest.php
    ├── PasswordResetTest.php
    └── LogoutTest.php
```

**Structure Decision**: Same single Laravel application as feature 001, now gaining its first real application layer (`app/Actions`, `app/Http/Controllers`, `app/Http/Requests`) and its first React screens. Business logic is isolated in `app/Actions/Auth` per constitution Principle I — controllers stay thin, and Form Requests own all validation rules (password policy, RFC+DNS email, field presence). No `app/Filament` work — staff/admin auth remains out of scope here per spec.md's FR-021.

## Complexity Tracking

> No Constitution Check violations were identified — this table is intentionally left empty.
