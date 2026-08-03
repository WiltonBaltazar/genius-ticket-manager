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
