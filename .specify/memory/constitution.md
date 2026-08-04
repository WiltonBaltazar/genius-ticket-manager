<!--
Sync Impact Report
===================
Version change: 1.0.0 → 1.1.0

Modified principles: N/A (no existing principle redefined)

Materially expanded guidance:
- Technology Stack & Design System → Frontend & Public Site: added a
  requirement that full screens/page layouts combine the `react-builder`
  and `frontend-design` Claude Code skills together, not `react-builder`
  alone, so implementation and aesthetic/UX direction are produced jointly.

Added sections: N/A (existing subsection expanded, not a new section)

Removed sections: N/A

Templates requiring updates:
- .specify/templates/plan-template.md ✅ no changes needed (already
  generic; "Constitution Check" gate references this file dynamically)
- .specify/templates/spec-template.md ✅ no changes needed (technology-agnostic)
- .specify/templates/tasks-template.md ✅ no changes needed (technology-agnostic)
- .claude/skills/speckit-constitution/SKILL.md ✅ no stale agent-specific
  references found
- README.md / docs/deployment-runbook.md ✅ checked — neither references
  react-builder or frontend-design, no updates needed

Follow-up TODOs: none
-->

# Genius Behind the Brands Annual Event Ticketing System Constitution

## Core Principles

### I. SOLID Architecture & Clean Code (NON-NEGOTIABLE)

Business and payment logic MUST live in single-responsibility action/service
classes (e.g., `ProcessPaymentAction`, `GenerateTicketsAction`,
`LockInventoryAction`), never in controllers or models. Controllers MUST
delegate to actions/services; models are restricted to relationships,
accessors, and casts. Any dependency with more than one implementation, or
that requires mocking in tests, MUST be defined behind an interface (e.g.,
`PaymentGatewayInterface`, `NotificationService`) and injected — dependency
injection MUST be preferred over facades in business logic. Complex queries
MUST use the Repository pattern; simple queries MUST use Query Builders
directly. Shared audit-field behavior (`created_by`, `updated_by`) MUST be
implemented once via a trait, not duplicated across models. Code MUST use
descriptive naming (`processRefund()`, never `pr()`), stay in small
functions, avoid duplication, and be formatted consistently (Pint for PHP;
Prettier/ESLint for React) as an enforced pre-merge gate. React components
MUST be small, composable, and prop-typed; shared logic MUST live in hooks
(`useCart`, `useTicketSelection`, `usePaymentState`) rather than being
copy-pasted across components. Filament resources MUST use custom actions
for bulk operations (bulk refund, bulk export) rather than ad-hoc scripts.

**Rationale**: Payment and inventory logic is the highest-risk surface in
this system. Fat controllers/models and copy-pasted logic make that risk
untestable and unauditable; SOLID boundaries keep each unit small enough to
reason about, mock, and verify in isolation.

### II. Security by Design (NON-NEGOTIABLE)

All input MUST be validated server-side via Laravel Form Requests; no
client-side-only validation may gate a mutating action. CSRF protection
MUST be active on every mutating endpoint (Blade tokens, Livewire
auto-tokens). Payment and contact endpoints (including `/api/orders`) MUST
be rate-limited to 5 requests/minute per IP. No secret keys may ship in the
React bundle — only the Stripe publishable key, sourced from `.env.public`,
may reach client code. CSP headers MUST restrict framed/embedded content to
the Stripe iframe origin. HTTPS MUST be enforced at the middleware and
environment level. The Filament admin panel MUST sit behind authentication
and policy-based authorization for every resource and action. Orders and
Refunds MUST carry audit fields (`created_by`, `confirmed_by`,
`confirmed_at`, `refunded_by`), and every payment state change MUST be
written to the immutable `audit_logs` table. Stripe webhook payloads MUST
have their signature verified before any processing occurs. Every payment
request MUST carry an idempotency key to prevent duplicate charges.
Attendee records MUST use soft deletes to support GDPR/POPIA erasure
requests without breaking referential integrity.

**Rationale**: This system moves real money and personal data for a public
event. Each control here closes a specific, known attack or failure class
(duplicate charges, CSRF, forged webhooks, leaked secrets, un-auditable
refunds) rather than being generic best practice — none are optional.

### III. Test-First for Booking-Critical Paths (NON-NEGOTIABLE)

No booking-critical feature (ticket selection, cart, checkout, payment,
webhook handling, refunds, inventory locking, check-in/QR scanning) may be
marked complete without automated tests covering its happy path, its
validation failures, and its concurrency/edge cases. Backend: Pest feature
tests for order creation, payment processing, webhook handling (Stripe
mocked), inventory locking, refund flow, and email notifications; Pest unit
tests for every action/service class; policy tests for every Filament
authorization rule (e.g., Event Manager edits only own events, Support
Agent has view-only access to orders). Concurrency tests MUST simulate two
simultaneous payment attempts against the same ticket type and assert
inventory never oversells. Database tests MUST run inside transactions with
rollback to prevent test pollution. Frontend: Vitest + React Testing
Library component tests for the ticket selection form, cart state,
checkout flow, validation error states, and payment state transitions,
plus integration tests for the TanStack Router route tree.

**Rationale**: Overselling tickets, double-charging attendees, or losing a
refund audit trail are irreversible in a live public sale. Tests must exist
*before* a path is trusted, not be retrofitted after an incident.

### IV. Data Integrity & Immutable Audit Trail

MySQL 8.0+ with the InnoDB engine is the only supported storage engine, to
guarantee transactional integrity and row-level locking. All tables use
UUID primary keys (`CHAR(36)`) except `audit_logs`, which uses a
`BIGINT` auto-increment key to preserve strict insertion order. Foreign
keys MUST be enforced at the database level with `ON DELETE RESTRICT`.
`users` and `orders` MUST support soft deletes via `deleted_at`. Frequently
queried columns MUST be indexed: `(event_id, status)`,
`(attendee_id, created_at DESC)`, `(stripe_payment_intent_id)`,
`(qr_code)`. The `audit_logs` table is append-only: the application layer
MUST NOT issue UPDATE or DELETE against it, and database-level permissions
MUST restrict the application's database user to INSERT-only on that
table. Stripe webhook payloads and other complex audit data MUST be stored
in JSON columns rather than flattened into ad-hoc fields.

**Rationale**: Ticket sales, refunds, and check-ins must be reconstructible
after the fact for financial reconciliation and dispute resolution.
Database-enforced append-only audit logs survive even an application-layer
bug or compromised admin session — a code-level check alone would not.

### V. Accessible, On-Brand Experience

The booking flow MUST remain a short, linear 3-step process: (1) select
tickets and quantity, (2) enter attendee details and email, (3) pay via
Stripe — with no dead ends and no fields beyond what each step requires.
Every step MUST show clear state: live availability in the cart, an order
total with fee breakdown at checkout, and a confirmation page with order ID
and a PDF ticket download link. The experience MUST be fully responsive and
mobile-first, with cart and payment behaving identically on mobile and
desktop. All public-facing and admin UI MUST meet WCAG 2.1 AA (color
contrast, keyboard navigation, ARIA labels on forms). Motion (GSAP + Lenis
on the public site only) MUST reinforce hierarchy or feedback — cart
item fade-in, validation shake, payment success slide-out — and MUST NOT
be purely decorative; it MUST respect `prefers-reduced-motion`. The
Filament admin panel MUST use only native Livewire transitions (simple
fade/scale) to preserve admin responsiveness — GSAP/Lenis are public-site
only.

**Rationale**: Ticket buyers abandon carts over friction and unclear state;
event staff need a fast, predictable admin tool, not marketing polish.
Accessibility is a legal and ethical baseline for a public ticketed event,
not an enhancement.

### VI. Shared-Hosting-Compatible Simplicity

The application MUST run on shared hosting (PHP 8.3+, MySQL 8.0+, SSH) with
no Docker and no dedicated queue workers. Background work (queued jobs,
scheduled tasks) MUST run through the Laravel scheduler triggered by a
once-a-minute cron entry, using the database queue driver
(`QUEUE_CONNECTION=database`) — Redis or any service requiring a persistent
daemon is disallowed. Caching MUST use the file driver
(`CACHE_DRIVER=file`); sessions MUST use the database or file driver.
Any new dependency, package, or architectural pattern MUST be justified
against this hosting constraint before being introduced — if it needs a
persistent process, a container, or infrastructure beyond shared hosting,
it does not belong in this codebase without an explicit, documented
exception.

**Rationale**: The deployment target is fixed and non-negotiable for this
event. Designs that assume Docker, Redis, or long-running workers will not
run in production — catching that mismatch at review time is far cheaper
than discovering it during a live ticket sale.

## Technology Stack & Design System

### Backend & Admin

Laravel 13 is the single application for API, business logic, and
Blade/Livewire views. Filament 5 (Livewire-based) is the only admin panel,
served at `/admin`, for event management, staff operations, and refund
processing. Livewire 3 components handle server-stateful cart and checkout
forms, synchronized via WebSocket broadcasts. PHP backend work uses
standard Laravel tooling (Artisan, Pest/PHPUnit); database migrations run
via `php artisan migrate`.

### Frontend & Public Site

React 19, compiled from `resources/js` through Laravel's Vite integration,
powers the public-facing site (event browsing, ticket cart, checkout flow).
TanStack Router provides client-side-only routing (no SSR); routes are
defined declaratively in the route tree, colocated with the components they
render. Any React component work MUST use the `react-builder` Claude Code
skill, across all project phases. Whenever a full screen or page layout is
needed — not an isolated component in isolation — `react-builder` MUST be
combined with the `frontend-design` Claude Code skill so implementation and
aesthetic/UX direction are produced together in the same pass, rather than
visual design being bolted on after the fact. Styling uses Tailwind CSS v4
with shadcn/ui reserved for interactive/stateful components (Dialog, Form,
Select, Button, Input, Calendar); layout and marketing sections are custom
Tailwind. Typography: Playfair Display (serif, headlines/section titles),
Barlow Condensed (headlines/badges), Barlow (body text, UI) — loaded via
Google Fonts or a self-hosted CDN.

### Design Tokens & Branding

Colors are defined via Tailwind v4 CSS-first `@theme` (no `tailwind.config`
file) and consumed as CSS variables so Filament admin can inherit the same
tokens:

| Token | Hex | Usage |
|---|---|---|
| Deep purple | `#3C0D5F` | Headings, key UI elements, body text on light backgrounds, secondary buttons, form borders |
| Gold | `#F2A801` | Primary CTA buttons, accents, links/hover states (used sparingly) |
| Gold (hover) | `#D88A00` | Primary button hover state |
| Red | `#FF3502` | Validation errors, alerts, refund/cancellation states only — default to purple for secondary actions |
| White | `#FFFFFF` | Backgrounds, text on dark backgrounds |

Forms use a white background, purple borders, gold focus states, and red
error text. Backgrounds are white or soft off-white for subtle section
separation.

### Motion & Interaction

GSAP + Lenis drive motion on the public site only — booking flow
animations, form transitions, success states — with a single Lenis
instance created at the React app root and synced to GSAP ScrollTrigger for
scroll reveals on the event browsing page (hero, testimonials, FAQs). See
Principle V for the non-negotiable rule that motion must be functional, not
decorative, and must respect `prefers-reduced-motion`.

## Development Workflow & Quality Gates

### Spec-Driven Process

All implementation is driven by specs living in `/specs`, generated from
this constitution. Features are tracked via GitHub Issues tagged by spec.
Every PR MUST reference an issue and MUST pass all automated tests before
merge. Code review requires at least one approval.

### Testing Gates

See Principle III for required coverage. A feature is not "done" until its
happy path, validation failures, and (where relevant) concurrency/edge
cases have passing automated tests — this is a merge gate, not a
follow-up task.

### Deployment Pipeline

Deployment to production occurs only after staging validation against the
Stripe sandbox. Deployment is via Git push (webhook-triggered Composer
install + migrations) or manual SFTP upload plus CLI commands, matching the
shared-hosting constraints in Principle VI. SSL is provided by the hosting
provider (e.g., cPanel AutoSSL). Backups: the hosting provider's daily
MySQL backup is the system of record for database backups; a daily export
of uploaded files (QR images, PDFs) to a backup directory MUST also run.
Error tracking uses Sentry (free tier); uptime is monitored via
UptimeRobot. Email is sent via shared-hosting SMTP or Brevo (API), matching
the no-persistent-worker constraint in Principle VI.

## Governance

This constitution supersedes all other project practices, style guides, or
prior informal conventions for the Genius Behind the Brands Annual Event
Ticketing System. Where a PR, spec, or plan conflicts with a principle
here, the constitution wins unless the conflict is resolved through an
amendment (below).

**Amendment procedure**: Amendments are proposed via PR modifying this
file, MUST include the rationale for the change, and MUST update the Sync
Impact Report at the top of this file. An amendment requires at least one
approval from a project maintainer before merge, same as any other PR.

**Versioning policy**: This constitution is versioned independently using
semantic versioning:
- **MAJOR** — backward-incompatible governance changes or the removal/
  redefinition of an existing principle.
- **MINOR** — a new principle or section is added, or existing guidance is
  materially expanded.
- **PATCH** — clarifications, wording fixes, or non-semantic refinements.

**Compliance review**: Every PR and code review MUST verify compliance with
the applicable principles above (the "Constitution Check" gate in
`plan-template.md` exists for this purpose). Any deviation MUST be recorded
and justified in that plan's Complexity Tracking table — undocumented
deviation is treated as a defect, not a shortcut.

**Version**: 1.1.0 | **Ratified**: 2026-07-29 | **Last Amended**: 2026-08-03
