# Feature Specification: Staff Admin Panel (Events, Ticket Types & Orders)

**Feature Branch**: `003-staff-admin-panel`

**Created**: 2026-08-04

**Status**: Draft

**Input**: User description: "Build the Filament 5 admin panel at /admin for staff to manage events, ticket types, and view orders, using the "staff" guard (separate from public attendees). Set up Filament with the brand color tokens (primary #3C0D5F, info #F2A801, danger #FF3502) and Barlow as the panel font. Seed two staff accounts for initial testing: a super_admin and an event_manager. Build an EventResource with a form (name, slug, location, date/time, hero image upload, rich-text description, status select: draft/published/closed/archived, internal notes) and a table (name, location, date, status badge, created date) with status filtering and standard CRUD actions. Build a TicketTypeResource nested under events (event select, name, description, price input displayed in MZN but stored/dehydrated as integer cents, total_quantity, read-only available_quantity, sales_start_date, sales_end_date). Prevent editing total_quantity once tickets have started selling (available_quantity < total_quantity). Build an OrderResource that is read-mostly: order info (id, attendee, email, event, status badge, total in MZN, payment method, created date) all disabled/non-editable except through the payment/refund workflow (out of scope for this spec), plus a read-only repeater showing order items (ticket type, quantity, unit price). Implement Filament policies: super_admin has full access; event_manager can create/edit events and ticket types and view orders but cannot delete events; support role can view orders only; gate_operator has no access to these three resources. Add a dashboard stats widget showing total orders, paid orders, revenue (MZN, sum of paid orders), and pending orders count. Write feature tests for: staff login redirect when unauthenticated, event_manager can create an event, support role cannot create an event, unauthenticated request to /admin redirects to /admin/login."

## Clarifications

### Session 2026-08-04

- Q: The already-migrated `events` table stores `start_date`/`end_date` as date-only (no time-of-day), but the request asks for a "date/time" field — how should this be resolved? → A: Add time-of-day precision — the event form captures date and time, and the underlying date columns are extended to store time too.
- Q: Is managing staff accounts themselves (creating new staff users, assigning/changing roles) in scope for this admin panel? → A: Out of scope — no staff-account-management resource in the panel; accounts are created/edited only via seeders or console commands for now.
- Q: Should staff create/edit/delete actions on Events and Ticket Types also be written to the audit_logs trail, or does audit logging stay scoped to payment/order state changes only (which this feature never writes to)? → A: Payment-only — no new audit_logs entries for event/ticket-type CRUD; this matches the constitution's stated scope for audit_logs.
- Q: For an event's status (draft/published/closed/archived), can permitted staff set it to any status at any time, or are certain transitions restricted? → A: Unrestricted — permitted staff can set any status at any time, in any order.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Staff Sign In and Are Gated by Role (Priority: P1)

A staff member (super admin, event manager, support agent, or gate operator) opens the admin panel and can only reach it, and only see the parts of it, that match their role. Anyone not signed in is sent to a staff login page instead of any admin content.

**Why this priority**: Nothing else in the panel is safe to build or trust until access is correctly gated — every other story depends on staff identity and role being enforced first.

**Independent Test**: Visit the admin panel while signed out and confirm a redirect to the staff login screen; sign in as each role and confirm the navigation and available actions match that role's permissions.

**Acceptance Scenarios**:

1. **Given** no active staff session, **When** a visitor requests any admin panel page, **Then** they are redirected to the staff login page.
2. **Given** a valid staff account and correct credentials, **When** the staff member submits the login form, **Then** a session is created under the staff-specific login (kept separate from public attendee accounts) and they land on the admin dashboard.
3. **Given** a signed-in super admin, **When** they browse the admin panel, **Then** they can see and use every management area covered by this feature (events, ticket types, orders).
4. **Given** a signed-in staff member with the gate operator role, **When** they browse the admin panel, **Then** none of the events, ticket types, or orders areas are visible or reachable to them.

---

### User Story 2 - Event Manager Creates and Maintains Events (Priority: P1)

An event manager (or super admin) creates a new event with its core details and a status, sees it in a sortable/filterable list alongside other events, and updates it as plans change. A support agent or gate operator cannot create or change events.

**Why this priority**: Events are the root record everything else (ticket types, orders, the dashboard) hangs off of — this is the first piece of content staff actually produce in the panel.

**Independent Test**: Sign in as an event manager, create an event end-to-end (name, slug, location, date/time, hero image, description, status, internal notes), and confirm it appears correctly in the events list; confirm a support-role account gets an access denied outcome for the same create action.

**Acceptance Scenarios**:

1. **Given** a signed-in event manager or super admin, **When** they fill in and submit a new event with a unique name/slug, location, date/time, hero image, description, status, and internal notes, **Then** the event is saved and appears in the events list.
2. **Given** an events list containing events in multiple statuses, **When** a staff member with view access filters by a specific status, **Then** only events matching that status are shown.
3. **Given** a signed-in support-role staff member, **When** they attempt to create or edit an event, **Then** the action is refused and no event is created or changed.
4. **Given** a signed-in event manager, **When** they attempt to delete an event, **Then** the action is refused — only a super admin can delete events.
5. **Given** an attempt to save an event whose slug duplicates an existing event's slug, **When** the form is submitted, **Then** the save is rejected with a validation error.

---

### User Story 3 - Event Manager Configures Ticket Types for an Event (Priority: P2)

Within a given event, an event manager (or super admin) defines one or more ticket types — what's on sale, how much it costs, how many are available, and the sales window — and cannot accidentally shrink or inflate the ticket count once tickets are already moving.

**Why this priority**: Ticket types are required before any real sales activity exists, but they're scoped underneath an event, so this naturally follows event management.

**Independent Test**: Sign in as an event manager, add a ticket type to an existing event with a price, quantity, and sales window, and confirm it's saved correctly and associated with that event; simulate tickets having started selling and confirm the total quantity field can no longer be changed.

**Acceptance Scenarios**:

1. **Given** a signed-in event manager or super admin viewing an event, **When** they add a ticket type with a name, description, price (entered and shown in MZN), total quantity, and a sales start/end window, **Then** the ticket type is saved and linked to that event.
2. **Given** a newly created ticket type that has not sold any tickets yet, **When** staff edit it, **Then** the total quantity field remains editable.
3. **Given** a ticket type for which at least one ticket has already sold (its remaining availability is below its total quantity), **When** staff open it for editing, **Then** the total quantity field is presented as read-only and cannot be changed.
4. **Given** any ticket type, **When** staff view it, **Then** the remaining/available quantity is always shown as read-only and cannot be directly edited by staff.
5. **Given** a signed-in support-role or gate-operator staff member, **When** they attempt to view or edit ticket types, **Then** the action is refused.

---

### User Story 4 - Staff View Orders and Their Line Items Read-Only (Priority: P2)

Staff with order-viewing permission (super admin, event manager, support agent) can look up an order — who bought it, for which event, its status, total, and payment method — and see exactly what was purchased, without being able to alter any of it from this screen.

**Why this priority**: Order visibility is essential for support and reconciliation, but it's read-only and depends on events/ticket types already existing, so it naturally follows those stories.

**Independent Test**: Sign in as a support-role staff member, open an existing order, and confirm all order fields and its list of purchased items are visible but none are editable; confirm a gate-operator account cannot reach the orders area at all.

**Acceptance Scenarios**:

1. **Given** a signed-in super admin, event manager, or support-role staff member, **When** they open an order, **Then** they can see its id, attendee, email, event, status, total (in MZN), payment method, and created date, and none of these fields can be edited from this screen.
2. **Given** an order with one or more purchased items, **When** staff view that order, **Then** each item's ticket type, quantity, and unit price are listed and none are editable.
3. **Given** a signed-in gate-operator staff member, **When** they attempt to view the orders area, **Then** the action is refused and no order data is shown.

---

### User Story 5 - Staff See At-a-Glance Sales Stats on the Dashboard (Priority: P3)

Staff who can view orders land on a dashboard that immediately shows overall order volume and revenue, without having to open the orders list and count manually.

**Why this priority**: This is a convenience/overview layer built entirely on data already exposed by prior stories — valuable, but not blocking for staff to do their core jobs.

**Independent Test**: Sign in as a role with order-view access, land on the dashboard, and confirm the total orders, paid orders, revenue, and pending orders figures match what's actually in the underlying order data.

**Acceptance Scenarios**:

1. **Given** a signed-in staff member with order-view access, **When** they land on the admin dashboard, **Then** they see the total number of orders, the number of paid orders, total revenue in MZN summed from paid orders only, and the number of pending orders.
2. **Given** new orders are created or change status after the dashboard was first shown, **When** the staff member reloads the dashboard, **Then** the figures reflect the current underlying order data.

---

### Edge Cases

- What happens when a staff account is deactivated/removed while they still have an active session — can they continue using the panel until the session naturally expires, or is access revoked immediately?
- What happens when two staff members edit the same event or ticket type at the same time — is the second save silently accepted, rejected, or does it overwrite the first?
- How does the panel behave if a hero image upload fails or an unsupported file type/size is submitted?
- What happens when an event manager tries to set a ticket type's sales end date before its sales start date?
- How should the panel behave if an order has zero line items (e.g., a corrupted or abandoned record)?
- What happens when a ticket type's total quantity edit is attempted at the exact moment the first ticket sale is being recorded (race between "not yet selling" and "now selling")?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST provide a staff-only admin panel at `/admin`, authenticated separately from public attendee accounts, so a staff identity is never confused with an attendee identity.
- **FR-002**: The system MUST redirect any unauthenticated request to an admin panel page to a staff login page.
- **FR-003**: The system MUST allow a staff member with valid credentials to sign in and reach the admin dashboard.
- **FR-004**: The system MUST enforce four staff roles with distinct permissions across events, ticket types, and orders: super admin (every action these requirements define for the three resources below, including deleting events — the only delete action this feature defines for any resource), event manager (create/edit events and ticket types, view orders, cannot delete events), support (view orders only), and gate operator (no access to events, ticket types, or orders management). A staff account whose role does not match one of these four values (including no role at all) MUST be treated as having the same access as gate operator — no access to events, ticket types, or orders.
- **FR-005**: The system MUST hide navigation entries and refuse actions for any area a staff member's role does not permit, both in what is shown and in what is actually allowed to execute.
- **FR-006**: The system MUST allow permitted staff to create and edit events with: name, a unique slug, location, a date and start time (time-of-day precision, not date-only), a hero image, a formatted description, a status (draft, published, closed, or archived), and internal notes visible only to staff. Staff may set an event's status to any of the four values at any time, in any order — no transition is restricted.
- **FR-007**: The system MUST reject an event save when its slug duplicates another event's slug.
- **FR-008**: The system MUST present a list of events showing name, location, date and time, status, and created date.
- **FR-009**: The system MUST allow staff to filter the events list by status.
- **FR-010**: The system MUST restrict event deletion to the super admin role; event managers can create and edit events but not delete them.
- **FR-011**: The system MUST allow permitted staff to create and edit ticket types scoped to a specific event, capturing: the owning event, name, description, price (entered and displayed in MZN), total quantity, and a sales start/end window. No role, including super admin, can delete a ticket type through this admin panel — ticket type deletion is out of scope for this feature (FR-004's "including delete" refers only to event deletion).
- **FR-012**: The system MUST always present a ticket type's remaining/available quantity as read-only; staff can never set it directly.
- **FR-013**: The system MUST prevent staff from changing a ticket type's total quantity once any tickets for it have sold (i.e., once its remaining quantity is below its total quantity), presenting that field as read-only in that state.
- **FR-014**: The system MUST display and accept ticket type prices in MZN without floating-point rounding or precision loss between what staff enter and what is stored.
- **FR-015**: The system MUST allow staff with order-view permission to see an order's id, attendee, email, associated event, status, total (in MZN), payment method, and created date.
- **FR-016**: The system MUST NOT allow any order field to be directly edited from the admin panel; any change to payment or refund state happens through a separate workflow outside this feature's scope.
- **FR-017**: The system MUST display an order's purchased line items (ticket type, quantity, unit price) as a read-only list.
- **FR-018**: The system MUST restrict orders visibility to super admin, event manager, and support roles; the gate operator role MUST have no access to order data.
- **FR-019**: The system MUST show a dashboard summary, for any role permitted to view orders, of: total order count, paid order count, total revenue in MZN summed from paid orders only, and pending order count.
- **FR-020**: The system MUST provide one seeded super admin account and one seeded event manager account for initial setup and testing. Creating or editing staff accounts and roles through the admin panel itself is out of scope for this feature.
- **FR-021**: The system MUST apply the organization's brand colors and typeface consistently across the admin panel's pages and components.

### Key Entities

- **Staff Account**: A staff member's identity for signing into the admin panel, distinct from attendee accounts, with a name, email, and a single role (super admin, event manager, support, or gate operator) that determines what they can see and do.
- **Event**: A ticketed event staff manage — name, unique slug, location, date and start time (time-of-day precision), hero image, description, status (draft/published/closed/archived), and internal notes visible only to staff. Owns one or more ticket types.
- **Ticket Type**: A category of ticket sold for a specific event — name, description, price (MZN), total quantity, remaining/available quantity, and a sales start/end window. Belongs to exactly one event.
- **Order**: A completed or in-progress purchase made by an attendee — status, total amount (MZN), payment method, associated event, and created date. Visible but not editable from this panel.
- **Order Item**: A single line within an order — the ticket type purchased, quantity, and unit price at time of purchase. Read-only, shown nested within its order.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of unauthenticated attempts to open any admin panel page result in a redirect to the staff login page (verified by automated tests).
- **SC-002**: 100% of create/edit/delete attempts made by a role without the corresponding permission are refused, with zero unintended data changes (verified by automated tests across all four roles).
- **SC-003**: An event manager can take a new event from blank form to visible-in-the-list in a single form submission, with zero failed or dead-end states across automated test coverage.
- **SC-004**: 100% of ticket types that have already sold at least one ticket reject total-quantity edits (verified by automated tests).
- **SC-005**: Dashboard totals (orders, paid orders, revenue, pending orders) match the underlying order records exactly, with no manual recalculation needed by staff.
- **SC-006**: Staff never see a navigation entry or action control for a resource their role cannot use.

## Assumptions

- "Location" refers to the event's existing venue information already tracked by the system; this feature exposes the field staff need to manage that information rather than introducing a new venue model. The event's date/time gains time-of-day precision as resolved in Clarifications; the exact column-level representation is a planning-level decision. The single date/time value staff enter is the sole source of truth for an event's schedule — any other scheduling bookkeeping the system already maintains internally is derived automatically from it and never requires separate staff input.
- Monetary precision (avoiding floating-point rounding loss) is a hard requirement; the specific storage representation (e.g., smallest currency unit vs. decimal) is a technical decision left to the implementation plan, not mandated here.
- Hero images accept standard web image formats within reasonable size limits typical for this kind of admin upload; no specific format/size limit was given, so standard defaults apply.
- Event descriptions and internal notes support standard rich-text formatting (headings, lists, emphasis, links); no specific formatting feature set was mandated.
- The refund/payment status change workflow referenced as "out of scope" is assumed to be a future, separate feature — this panel only ever reads order/payment state, never writes it.
- Audit logging (the immutable `audit_logs` trail) stays scoped to payment/order state changes, per this project's governing constitution's data-integrity principle; staff create/edit/delete actions on Events and Ticket Types in this feature are not separately written to that trail. This is a deliberate scope boundary confirmed during clarification, not an oversight.
- The gate operator role already exists in the system for other purposes (e.g., ticket check-in) outside this feature; this spec only defines that it has zero access to events, ticket types, and orders management.
- Seeded super admin and event manager accounts are for initial setup/testing purposes and use placeholder, non-production credentials.
- "Standard CRUD actions" for events means create, view, edit, and delete are all available to roles permitted to use them (subject to the delete restriction in FR-010); ticket types and orders follow the narrower create/edit or read-only rules stated explicitly above.
