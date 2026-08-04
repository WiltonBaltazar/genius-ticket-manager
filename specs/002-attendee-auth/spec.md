# Feature Specification: Attendee Authentication

**Feature Branch**: `main` (no dedicated feature branch created — no git hook configured for automatic branch creation)

**Created**: 2026-08-03

**Status**: Draft

## Clarifications

### Session 2026-08-03

- Q: The spec has no protection against repeated failed login attempts (brute-force/credential-stuffing). What should happen after too many failed attempts? → A: IP-based rate limit only — throttle login attempts per IP address, matching the existing password-reset throttle pattern; no per-account lockout.
- Q: Should any auth events beyond successful logins be permanently recorded to audit_logs? → A: Also audit failed login attempts, to give a forensic trail for investigating account-targeting/brute-force activity.
- Q: If sending the verification email fails at registration time, what should happen? → A: Registration still succeeds; the email is queued/retried through the standard mail pipeline, and the attendee can also trigger a resend.

**Input**: User description: "Implement attendee authentication: registration, email verification, login, logout, and password reset, using Laravel's built-in auth scaffolding against the attendees table (separate guard from staff). Registration requires email (unique, RFC+DNS validated), full name, optional phone, and a password meeting Laravel's Password::min(12)->mixedCase()->numbers()->symbols() rule with confirmation. On success, send a signed email verification link that expires in 24 hours. Users cannot log in until verified — a failed login due to unverified email should return a distinct error so the frontend can offer a "resend verification" action. Login checks credentials against the attendees table, requires a verified email, and creates a session under the "web" guard. Log every successful login to audit_logs with event_type=attendee_login, actor_id, ip_address. Password reset uses Laravel's Password broker: request endpoint sends a reset link (throttled to 1/minute per IP), reset tokens expire in 1 hour, and resetting a password invalidates the user's existing session. Build the corresponding React registration and login forms (email, name, phone, password, confirm password fields with inline validation errors) styled with the brand palette (deep purple #3C0D5F text/labels, gold #F2A801 focus/links, red #FF3502 errors) and Barlow/Barlow Condensed typography, using TanStack Router for navigation between /auth/register, /auth/login, /auth/forgot-password. Write feature tests covering: successful registration, weak password rejection, duplicate email rejection, login blocked before verification, successful login after verification, and password reset end-to-end."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - New Attendee Can Register for an Account (Priority: P1)

A prospective attendee creates an account with their email, name, optional phone number, and a strong password, then receives an email to verify their address before they can log in.

**Why this priority**: Registration is the entry point for every other capability in this feature; nothing else is testable without it.

**Independent Test**: Submit valid registration data via the registration form; confirm the account is created in an unverified state and a verification email is sent.

**Acceptance Scenarios**:

1. **Given** valid, unique registration data with a password meeting the strength policy, **When** the attendee submits the registration form, **Then** an account is created in an unverified state and a verification email is sent.
2. **Given** a password that doesn't meet the minimum strength policy, **When** the attendee submits the registration form, **Then** registration is rejected with a validation error identifying the failed requirement.
3. **Given** an email address that already belongs to an account that has completed registration (already has a password), **When** someone attempts to register with that same email, **Then** registration is rejected as a duplicate.
4. **Given** an email address that already has an attendee record with no password set yet (e.g., created by a prior guest checkout), **When** someone registers with that email, **Then** the existing record is claimed — credentials are attached to it — rather than a new duplicate record being created or the registration being rejected.

---

### User Story 2 - Verified Attendees Can Log In, Unverified Attendees Cannot (Priority: P1)

Only attendees who have verified their email address can log in; an attendee who hasn't yet verified receives a distinct message telling them to check their email, with an option to resend the verification link.

**Why this priority**: Enforcing the verification gate is core to this feature's security value and directly follows registration.

**Independent Test**: Attempt to log in with a registered-but-unverified account and confirm a distinct "not verified" response; verify the account and confirm login now succeeds.

**Acceptance Scenarios**:

1. **Given** an account that has not verified its email, **When** that attendee attempts to log in with correct credentials, **Then** login is refused with a distinct "email not verified" response (not the generic invalid-credentials message), and a way to resend the verification link is available.
2. **Given** an account with a verified email, **When** that attendee logs in with correct credentials, **Then** a session is created and the login is permanently recorded as an audit event.
3. **Given** incorrect credentials, **When** any attendee attempts to log in, **Then** login is refused with a generic invalid-credentials message that does not reveal whether the email exists.

---

### User Story 3 - Attendee Can Reset a Forgotten Password (Priority: P2)

An attendee who forgets their password can request a reset link by email and set a new password, without needing to remember the old one.

**Why this priority**: Important for account recovery and reducing support burden, but not required for the core registration/login loop to function.

**Independent Test**: Request a password reset for a known account, use the emailed link to set a new password, and confirm the old password no longer works while the new one does.

**Acceptance Scenarios**:

1. **Given** a registered email address, **When** a password reset is requested, **Then** a time-limited reset link is sent to that address, and repeated requests within the same minute from the same origin are throttled.
2. **Given** a valid, unexpired reset link, **When** the attendee submits a new password meeting the strength policy, **Then** the password is updated and all of that attendee's previously active sessions are invalidated.
3. **Given** a reset link older than its expiry window, **When** the attendee attempts to use it, **Then** the reset is rejected and a new request is required.

---

### User Story 4 - Attendee Can Log Out (Priority: P3)

A logged-in attendee can end their session on demand.

**Why this priority**: Simple, low-risk capability that completes the authentication lifecycle but isn't blocking for the higher-priority flows.

**Independent Test**: Log in, then log out, and confirm the session no longer grants access to authenticated actions.

**Acceptance Scenarios**:

1. **Given** an active session, **When** the attendee logs out, **Then** the session is terminated and subsequent requests are treated as unauthenticated.

---

### Edge Cases

- An email address is syntactically valid but has no deliverable mail server (DNS/MX validation).
- A verification link is used twice (must be harmless the second time) or after its 24-hour expiry (must fail distinctly).
- A password-reset request is submitted for an email address with no matching account (must not reveal whether the account exists).
- More than one password-reset request is submitted for the same origin within the same minute (throttling).
- An attendee requests a new verification email while already verified.
- A password reset is attempted with a new password that fails the strength policy.
- A guest-checkout Attendee record (feature 001) and a new registration attempt collide on the same email — resolved by claiming the existing record (see User Story 1, Acceptance Scenario 4), not by feature 001's checkout-time dedup logic.
- An IP address exceeds the login-attempt rate limit — including a legitimate attendee retrying their own correct-but-mistyped password, and an attacker targeting multiple different accounts from the same IP.
- The verification email fails to send at registration time (e.g., a transient mail-provider outage) — registration must still succeed, with delivery retried rather than lost.
- Rapid repeated registration attempts from a single IP address (mail-queue flooding / at-volume enumeration via the duplicate-email response) — throttled per FR-023.
- An already-authenticated attendee navigates to the registration, login, or forgot-password screen — the frontend redirects them away rather than presenting a form that doesn't apply to a logged-in user (see Assumptions); the backend endpoints themselves don't need special-case logic since they operate on submitted credentials regardless of the requester's own session state.
- A password-reset link is used after the underlying Attendee record was soft-deleted (feature 001 erasure) between the reset request and its completion — the reset fails the same way an invalid/expired token would, since a soft-deleted record is excluded from normal lookups by default.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow a prospective attendee to register with an email address, full name, an optional phone number, and a password, plus password confirmation.
- **FR-002**: System MUST validate the registration email address for correct syntax and a deliverable domain — accepting a domain with an explicit mail-exchange record or, per RFC 5321's implicit-MX fallback, a domain with only a valid A/AAAA record — and MUST reject registration if the address is syntactically invalid or the domain resolves to neither.
- **FR-003**: System MUST enforce a minimum password strength policy — 12 to 72 characters (the upper bound matching the chosen hashing algorithm's input limit), including uppercase, lowercase, a number, and a symbol — and MUST reject registration if the password and its confirmation do not match or the policy is not met.
- **FR-004**: System MUST reject registration when the supplied email address already belongs to an account that has previously completed registration (i.e., already has a password set).
- **FR-005**: System MUST allow registration to attach credentials to an existing attendee record that has no password set yet (e.g., created by a prior guest checkout per feature 001's FR-023), rather than creating a duplicate record or rejecting the attempt.
- **FR-006**: System MUST send a time-limited, tamper-evident email verification link upon successful registration, valid for 24 hours from issuance. Registration itself MUST succeed regardless of whether the email send succeeds immediately; delivery MUST be retried through the standard notification pipeline rather than blocking or rolling back account creation.
- **FR-007**: System MUST allow an attendee to request the verification email be resent, throttled to at most one resend per minute per origin, if they have not yet verified; the response MUST be identical (`200 OK`, generic message) whether the submitted email is unverified, already verified, or has no matching account at all, so no variant discloses account state.
- **FR-008**: System MUST prevent login until the attendee's email address has been verified.
- **FR-009**: System MUST return a distinct, identifiable response when login is refused specifically because the email is unverified, separate from the response for incorrect credentials.
- **FR-010**: System MUST NOT reveal, via the login error response, whether a given email address has a registered account when the failure is due to incorrect credentials.
- **FR-011**: System MUST create an authenticated session for an attendee who supplies correct credentials for a verified account.
- **FR-012**: System MUST throttle login attempts per originating IP address at 5 attempts per minute, rejecting further attempts from that IP once the limit is exceeded, independent of which account is being targeted.
- **FR-013**: System MUST permanently record every successful attendee login as an audit event, capturing the attendee as the actor and the originating network address (the resolved client address, not necessarily the immediate TCP peer, on a proxied deployment — see FR-012's trusted-proxy handling).
- **FR-014**: System MUST permanently record every failed attendee login attempt as a distinct audit event, capturing the targeted email/account (where identifiable), the originating network address, and the failure reason (incorrect credentials vs. unverified email). Audit records MUST NEVER include the submitted password, a reset token, or any other credential material, in this or any other field.
- **FR-015**: System MUST allow an attendee to end their authenticated session on demand.
- **FR-016**: System MUST allow an attendee to request a password reset by email address, without revealing whether that address has an account.
- **FR-017**: System MUST throttle password reset requests to at most one per minute from the same origin.
- **FR-018**: System MUST issue a time-limited password reset link valid for 1 hour from issuance, and MUST reject a reset attempt using an expired link.
- **FR-019**: System MUST enforce the same password strength policy (FR-003) on a password reset as on registration.
- **FR-020**: System MUST invalidate all of an attendee's existing authenticated sessions and any persistent "remember me" credentials when their password is successfully reset.
- **FR-021**: System MUST keep attendee authentication (this feature) and staff authentication (the existing, separate staff/admin access) fully independent — an attendee session MUST NOT grant staff access, or vice versa.
- **FR-022**: System MUST provide a registration screen and a login screen, each collecting the fields above with inline validation feedback, and a forgot-password screen for initiating a reset.
- **FR-023**: System MUST throttle registration attempts per originating IP address (5 per minute, matching the project's standard rate-limit convention), independent of the submitted email address.
- **FR-024**: System MUST store passwords only in irreversibly hashed form — never in plaintext, and never reversibly encrypted — and MUST NOT write a password (submitted, hashed, or otherwise) to logs, audit records, or error messages under any circumstance.
- **FR-025**: System MUST issue a new session identifier upon successful authentication, distinct from any session identifier that existed immediately before login, so an attacker cannot pre-set and later reuse a victim's session (session-fixation protection).
- **FR-026**: System MUST transmit and store the session cookie with attributes preventing script access and cross-site transmission (equivalent to `HttpOnly`, `Secure`, and a restrictive `SameSite` policy).

### Key Entities

- **Attendee (extended)**: The existing Attendee entity (feature 001) gains authentication capability — credentials, email verification state and timestamp, and session identity — without altering its role as the canonical one-record-per-email purchaser identity established by feature 001's FR-023.
- **Verification Token**: A time-limited, single-purpose credential proving control of an email address, expiring 24 hours after issuance.
- **Password Reset Token**: A time-limited, single-use credential proving the right to set a new password, expiring 1 hour after issuance.
- **Session**: An authenticated attendee's active login, invalidated in bulk when that attendee's password is reset.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of registrations with valid data (unique or claimable email, matching strength-compliant passwords) succeed, independent of the verification email's delivery outcome; 100% of successful registrations result in a verification email being sent or retried.
- **SC-002**: 0% of unverified accounts are able to establish an authenticated session.
- **SC-003**: 100% of successful logins and 100% of failed login attempts against identifiable accounts each produce exactly one corresponding audit record; attempts against nonexistent emails are covered by the IP throttle rather than individually audited (per FR-014's "where identifiable" scope).
- **SC-004**: Password reset requests exceeding one per minute from the same origin are rejected, while the first request in each window always succeeds.
- **SC-005**: Login and registration attempts from a single IP address exceeding their rate limits (5/minute each) are rejected regardless of which account they target, while attempts within the limit are always evaluated normally.
- **SC-006**: A new attendee can complete registration — from first keystroke on the registration form to the in-app "check your email" confirmation screen — in under 2 minutes; this excludes any time spent afterward in the attendee's own mail client, which this system doesn't control.
- **SC-007**: 100% of password resets using a valid link result in all of that attendee's prior sessions becoming unusable afterward.

## Assumptions

- Full name is stored as a single field, matching the existing `attendees.name` column from feature 001 (no first/last name split).
- Phone number format is not strictly validated beyond being an optional free-text field, consistent with feature 001's existing `attendees.phone` column.
- Resend-verification requests are rate-limited using a standard, conservative default (no more than one resend per minute), since no specific throttle was specified for this action.
- "Invalidates the user's existing session" (password reset) is interpreted as invalidating all active sessions for that attendee across all devices, not just the session that initiated the reset, since a reset is a credential-recovery event with no session context worth preserving.
- The distinct "email not verified" (`423`) login error is an intentional UX tradeoff explicitly requested for this feature: it discloses that the email/password pair is valid but unverified — a narrower, deliberate exception to FR-010's general non-disclosure rule, scoped only to the unverified-account case. It does not extend to incorrect-credentials failures (FR-010, generic `422` regardless of whether the email exists) or to the specific case FR-004 already carves out at registration time (an email that already has a completed account). These are three distinct, independently-justified disclosure decisions, not one blanket policy: registration confirms account existence (needed to drive the claim-vs-reject behavior), unverified-login confirms validity-but-unverified (needed for the resend UX), and incorrect-credentials login reveals nothing (standard credential-stuffing defense).
- Staff authentication (existing, separate) is unaffected by this feature; only the attendee-facing guard is introduced/extended here.
- Specific visual styling (brand palette, typography) and routing details are captured at the requirement level here (FR-022) and will be elaborated in the implementation plan, consistent with keeping this document technology-agnostic.
- Changing a password while already authenticated (re-entering the current password to set a new one) is a distinct capability from the forgot-password flow in User Story 3 and is out of scope for this feature; only self-service recovery via emailed reset link is covered here.
- Multi-factor authentication is out of scope for this feature; login is single-factor (email + password) only.
- Recovery for an attendee who has lost all access to the email address on their account (and so cannot receive a verification or reset link) is out of scope — email is the sole account-recovery channel this feature provides; a support-mediated recovery path, if ever needed, belongs to a future feature.
- DNS/MX lookups (FR-002) depend on outbound DNS resolution being available and reasonably fast from the shared-hosting environment (constitution Principle VI); this is a standard PHP capability on virtually all shared hosts, but is called out here as a dependency worth confirming during deployment rather than assumed silently.
- Verification and password-reset emails are dispatched through Laravel's `database` queue driver (no dedicated worker, per constitution Principle VI) and are processed by the same once-a-minute cron-triggered scheduler that already drains that queue for other work — this feature adds queue volume but no new infrastructure, consistent with that principle.
- An already-authenticated attendee visiting `/auth/register`, `/auth/login`, or `/auth/forgot-password` is redirected away by the frontend router rather than being shown a form; this is a client-side routing concern, not a backend requirement, since the endpoints themselves remain valid to call regardless of the caller's session state.
