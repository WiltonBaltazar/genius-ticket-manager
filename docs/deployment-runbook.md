# Deployment Runbook

## `audit_logs` INSERT/SELECT-only database grant

Per constitution Principle IV ("database-level permissions MUST restrict the application's
database user to INSERT-only on that table") and `research.md` §6, the application's MySQL
user must never be able to `UPDATE` or `DELETE` rows in `audit_logs` — this is enforced at the
database grant level, not just by application code, so it survives even a compromised admin
session or an application-layer bug.

This cannot be expressed in a Laravel migration (migrations run as the same DB user that needs
to be *restricted*), so it must be applied once per environment as a manual step, after the
`audit_logs` table exists:

```sql
REVOKE UPDATE, DELETE ON genius_ticket_manager.audit_logs FROM 'app_user'@'%';
GRANT INSERT, SELECT ON genius_ticket_manager.audit_logs TO 'app_user'@'%';
FLUSH PRIVILEGES;
```

Replace `app_user` with the actual application database user and `genius_ticket_manager` with
the production database name for the target environment.

**Verification**: as that DB user, confirm `UPDATE audit_logs SET action = 'x' WHERE id = 1;`
fails with an access-denied error, while `INSERT INTO audit_logs (...) VALUES (...);` and
`SELECT * FROM audit_logs;` both succeed.

**When to apply**: after every environment's initial `php artisan migrate`, and again after any
migration that drops and recreates the `audit_logs` table (grants do not survive `DROP TABLE`).

## Trusted proxies (attendee-auth feature)

`bootstrap/app.php` trusts `*` (any immediate upstream) for `X-Forwarded-*` headers so
FR-012's login throttle and FR-013/FR-014's audit `ip_address` reflect the real client behind
the hosting provider's reverse proxy/CDN, not a spoofable header from an untrusted source. If
the hosting provider's proxy IP ranges are known and stable, tighten `at: '*'` to that specific
list rather than trusting any upstream — `*` is the safe default only because shared-hosting
setups don't always expose static proxy IPs in advance.

## Session cookie security (attendee-auth feature)

Set `SESSION_SECURE_COOKIE=true` in every non-local environment's `.env` once the site is
served over HTTPS (FR-026, constitution Principle II). `http_only` and `same_site=lax` are
already Laravel's secure-by-default config values and need no override.

## Scheduler cron entry (attendee-checkout feature)

Pending-order expiry (`orders:expire-pending`, FR-012/FR-017) runs via Laravel's scheduler
(`bootstrap/app.php`'s `->withSchedule()`), not a queue worker — per `research.md` §3, this
avoids depending on a persistent queue process that shared hosting may not guarantee is
running. The scheduler itself still needs exactly one cron entry per environment to tick:

```cron
* * * * * cd /path/to/genius-ticket-manager && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/genius-ticket-manager` with the deployed application root. Without this
entry, pending orders never expire and reserved inventory is never released back to
`available_quantity`.

**Verification**: after deploying, confirm the entry is active (`crontab -l` for the deploy
user) and that `php artisan schedule:list` shows `orders:expire-pending` scheduled every five
minutes.

**Docker/Coolify deployment**: the above crontab entry is only needed on shared hosting. The
`Dockerfile` in this repo runs `php artisan schedule:work` as a supervised, always-on process
(see `docker/supervisord.conf`) instead — it ticks internally every minute, so no crontab setup
is required there. The queue worker (`php artisan queue:work`) is supervised the same way, which
is what actually delivers the `ShouldQueue` order/ticket-transfer/auth email notifications.

## Payment settings (WhatsApp number, bank details)

The `WHATSAPP_ORGANIZER_NUMBER`/`BANK_TRANSFER_*` `.env` values only bootstrap the
`payment_settings` table's single row, via the `create_payment_settings_table` migration —
after the first `php artisan migrate`, editing `.env` has no further effect. All later changes
(swapping the WhatsApp number, updating bank details, editing the bank-transfer proof-of-payment
instructions shown to attendees) are made at `/admin/payment-settings`, restricted to the
`super_admin` role (`App\Filament\Pages\PaymentSettings::canAccess()`).
