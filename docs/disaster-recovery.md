# Disaster Recovery Runbook — PSG Shifts

This runbook describes how to recover Platinum Security Group’s operations system after server failure, database corruption, accidental deletion, or other major incidents. Backups are only considered reliable after integrity verification and periodic restore drills.

## What is backed up

| Asset | Location in backup | Notes |
| --- | --- | --- |
| Application database | `.sqlite` or `.sql` dump | Guards, staff, deployments, shifts, payroll, audit logs, settings |
| Private uploads | `psg-files-*.zip` | `storage/app/private` (employee docs, attachments) |
| Catalog metadata | `database_backups` table | Reference, checksums, initiator, status, off-site path |

Public branding assets under `storage/app/public` are not included in the private-files zip; redeploy from release artifacts or restore separately if needed.

## Schedule (Laravel scheduler)

Requires `php artisan schedule:work` (or cron `schedule:run` every minute) plus a queue worker.

| Job | When | Command |
| --- | --- | --- |
| Daily backup | 01:30 | `psg:backup-database --type=scheduled_daily` |
| Weekly backup | Sunday 02:15 | `psg:backup-database --type=scheduled_weekly` |
| Monthly backup | 1st 03:00 | `psg:backup-database --type=scheduled_monthly` |
| Verify latest | Daily 04:00 | `psg:verify-backup` |
| Restore drill | Sunday 04:30 | `psg:test-restore-backup` |
| Health / missed alert | Hourly | `psg:backup-health --alert` |

Retention uses a GFS model (configurable in Platform Settings): keep N daily, N weekly, and N monthly copies.

## Off-site storage

Set `PSG_BACKUP_OFFSITE_DISK=s3` (and AWS credentials) or choose **Amazon S3** in Platform Settings. After each successful local backup, the database dump and files zip are copied to the off-site disk under `PSG_BACKUP_OFFSITE_PATH` (default `psg-backups`).

**Principle:** keep at least one copy off the primary application server so a single host failure cannot destroy both live data and backups.

## Access control

All backup create / download / verify / restore / restore-files actions require the `admin.backups_manage` permission (Super Admin and Managing Director by default). Every action is written to the audit log.

## Manual backup

```bash
php artisan psg:backup-database --type=manual --notes="Pre-upgrade safety copy"
```

Or use **Administration → Backups & recovery → Create backup now**.

## Integrity verification

```bash
php artisan psg:verify-backup
php artisan psg:verify-backup BKP-20260921-001
```

Verification recomputes SHA-256 for the database dump and (when present) the files archive. Failed checks mark the backup as failed and raise `backup.verify_failed`.

## Restore drill (non-destructive)

```bash
php artisan psg:test-restore-backup
php artisan psg:test-restore-backup BKP-20260921-001
```

- **SQLite:** copies the dump to a temp file, opens read-only, runs a users-table smoke query.
- **MySQL:** confirms checksum + non-empty dump; full import drills need a staging database.

Scheduled weekly. Results are stored on the backup row (`restore_tested_at`, `restore_test_notes`) and audited as `backup.restore_tested`.

## Recovering the database (destructive)

1. Put the app in maintenance: `php artisan down`
2. Identify a **verified** (or completed) backup in the admin console or catalog.
3. Prefer UI restore (type `RESTORE`) or:

```bash
php artisan psg:restore-backup BKP-20260921-001 --force
```

4. The system always creates a **safety pre-restore** backup of the current database first, then re-verifies checksums, then replaces the live database.
5. If the selected backup included files and uploads are missing, restore them:

```bash
# Via UI: type RESTORE FILES on the backup detail page
```

6. Clear caches / reconnect workers: `php artisan optimize:clear`
7. Smoke-test login, dashboard, recent shifts, and a payroll/report screen.
8. `php artisan up`

## Recovering after total server loss

1. Provision a new host with PHP, web server, MySQL/SQLite, Composer, Node (for assets if rebuilding).
2. Deploy application release (code + `public/build`).
3. Configure `.env` (`APP_KEY`, DB, mail, `PSG_BACKUP_*`).
4. Copy the chosen backup dump (and files zip) from off-site storage into `storage/app/backups/`.
5. If the catalog is empty, restore the dump with the native client first, then run migrations only if the dump schema matches the release:

```bash
# MySQL example
mysql -u USER -p DATABASE < psg-mysql-YYYYMMDD-HHMMSS-ID.sql

# SQLite example
copy psg-sqlite-YYYYMMDD-HHMMSS-ID.sqlite storage/app/database.sqlite
```

6. Extract files zip into `storage/app/` so `private/` lands correctly.
7. `php artisan migrate --force` only when upgrading schema after restore.
8. Start `schedule:work` and `queue:work`.
9. Run `php artisan psg:backup-database --type=manual` immediately after recovery.
10. Document the incident in the audit trail / ops notes.

## Monitoring

- Admin backups index shows a freshness banner when the last success is older than `backup_stale_hours` (default 36).
- `psg:backup-health --alert` and the proactive alert scanner emit `backup.missed`.
- Workflow emails notify users with `admin.backups_manage` when notify is enabled.

## Quarterly checklist

- [ ] Confirm scheduled backups appear in the catalog with `verified` or recent restore-drill timestamps.
- [ ] Confirm off-site objects exist for recent references.
- [ ] Perform a full restore to a **staging** host from a production backup.
- [ ] Review retention counts vs disk capacity.
- [ ] Review who holds `admin.backups_manage`.

## Related commands

```bash
php artisan psg:backup-database
php artisan psg:verify-backup
php artisan psg:test-restore-backup
php artisan psg:backup-health --alert
php artisan psg:restore-backup {reference} --force
php artisan psg:production-check
```

See also [DEPLOY.md](../DEPLOY.md) and [docs/ops-queue-scheduler.md](./ops-queue-scheduler.md).
