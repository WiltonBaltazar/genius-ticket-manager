# Quickstart: Validating the Staff Admin Panel

This is a validation guide for the schema changes in `data-model.md` and the resource/policy structure in `plan.md`, not an implementation walkthrough. Run these once the migrations, `StaffRole` enum, Filament panel/resources/policies/widget, seeder, and tests from `tasks.md` exist. Unlike feature 002, this feature is server-rendered Livewire UI with no JSON API, so most steps are either `artisan`/`tinker` checks or manual browser steps — automated coverage lives in the Pest tests (step 7).

## Prerequisites

- Everything from feature 001/002's quickstart.md already working (migrated schema, Pest configured against real MySQL, `staff` table populated by feature 001's factory)
- `composer require filament/filament:"^5.0"` installed, and Filament's frontend assets built (`php artisan filament:install --panels` if not already scaffolded, then `npm run build` or `npm run dev`)
- `php artisan migrate` run to apply this feature's two new migrations (`events` admin fields + time precision, `ticket_types` sales window)
- `php artisan db:seed` run so the two seeded staff accounts (super admin, event manager) exist

## 1. Apply the schema changes

```bash
php artisan migrate
```

**Expected outcome**: `events` gains `slug` (unique), `hero_image_path`, `internal_notes`, and `start_date` is now a `DATETIME`. `ticket_types` gains `sales_start_date`, `sales_end_date` (both nullable).

```bash
php artisan tinker --execute="dd(Schema::getColumnType('events', 'start_date'))"
```

**Expected outcome**: `datetime`, not `date`.

## 2. Confirm the seeded staff accounts and roles

```bash
php artisan tinker --execute="dd(App\Models\Staff::query()->pluck('role', 'email'))"
```

**Expected outcome**: exactly two rows, one with role `super_admin`, one with role `event_manager` (spec.md FR-020).

## 3. Unauthenticated access redirects to staff login

```bash
curl -i http://localhost/admin
```

**Expected outcome**: `302` redirect to `/admin/login`, not the panel content and not the attendee-facing `/auth/login` (spec.md FR-002, SC-001).

## 4. Log in as the seeded event manager and create an event

In a browser: visit `/admin/login`, sign in with the seeded event manager's credentials, navigate to Events → Create. Fill in name, slug, location, date/time, status, description, internal notes (hero image optional). Submit.

**Expected outcome**: redirected to the events list; the new event appears with the correct name, location, date/time, and status badge. `internal_notes` is visible in the panel but was never sent to any public-facing route (spec.md FR-006, Key Entities).

## 5. Ticket type total_quantity locks once selling starts

Create a ticket type under the event from step 4 (total quantity, e.g., 100). Confirm `total_quantity` is editable while `available_quantity` still equals `total_quantity`. Then, via tinker, simulate a sale:

```bash
php artisan tinker --execute="App\Models\TicketType::latest()->first()->update(['available_quantity' => 99])"
```

Reopen the ticket type's edit form in the browser.

**Expected outcome**: `total_quantity` now renders read-only; `available_quantity` was already read-only before and after (spec.md FR-012, FR-013, SC-004).

## 6. Role-based access — the four roles behave differently

Using `php artisan tinker`, create one `Staff` row per remaining role not already seeded (`support`, `gate_operator`), e.g. `App\Models\Staff::factory()->support()->create(['email' => 'support@example.test'])` (per `data-model.md`'s factory states). Then, in the browser (or via the Pest tests in step 7):

| Role | Try | Expected |
|---|---|---|
| `support` | Open Events nav / `/admin/events` | Nav item absent; direct URL access refused |
| `support` | Open Orders | List and detail view work; no edit control anywhere |
| `gate_operator` | Open Events, Ticket Types, or Orders | All three absent from nav; all three refused on direct URL |
| `event_manager` | Delete an event | Action absent from the UI; direct delete attempt refused (spec.md FR-010) |

## 7. Run the automated test suite

```bash
php artisan test --filter=StaffAdminPanel
```

**Expected outcome**: all pass, covering the four scenarios spec.md calls out explicitly — unauthenticated redirect to `/admin/login`, event manager can create an event, support role cannot create an event, and the general "staff login redirect when unauthenticated" case — plus the broader policy matrix in `data-model.md`'s Role → Resource access table.

## 8. Dashboard stats match underlying order data

Log in as the super admin (or `support`) and open the dashboard.

```bash
php artisan tinker --execute="
dd([
    'total' => App\Models\Order::count(),
    'paid' => App\Models\Order::where('status', App\Enums\OrderStatus::Paid)->count(),
    'revenue' => App\Models\Order::where('status', App\Enums\OrderStatus::Paid)->sum('total_amount'),
    'pending' => App\Models\Order::where('status', App\Enums\OrderStatus::Pending)->count(),
])
"
```

**Expected outcome**: the four numbers from tinker match the four stat tiles shown on the dashboard exactly (spec.md FR-019, SC-005). Log in as `gate_operator` and confirm the dashboard shows no order stats at all.

## 9. Brand styling

Visit any `/admin` page as any staff role. Confirm: primary purple (`#3C0D5F`), gold info/accent (`#F2A801`), red danger states (`#FF3502`), and Barlow as the panel's typeface (spec.md FR-021).

## Success criteria mapping

| Spec success criterion | Validated by step |
|---|---|
| SC-001 (unauthenticated → login redirect) | Step 3 |
| SC-002 (unauthorized actions refused, zero side effects) | Step 6, Step 7 |
| SC-003 (event creation has no dead end) | Step 4 |
| SC-004 (sold ticket types reject total_quantity edits) | Step 5 |
| SC-005 (dashboard totals match underlying data) | Step 8 |
| SC-006 (staff never see controls their role can't use) | Step 6, Step 8 (gate_operator dashboard check) |
