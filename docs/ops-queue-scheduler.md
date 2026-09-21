# Queue workers & scheduler — Platinum Security Group

This runbook covers background processing for local WAMP and production. The app uses the **database** queue by default (`QUEUE_CONNECTION=database`).

## Why this matters

Heavy work (workflow emails, payroll calculation, exports) is queued. If no worker is running, the `jobs` table grows and users see delayed notifications. Gmail SMTP also has **daily sending limits** — when hit, mail jobs fail until the limit resets.

Check health anytime:

```bash
php artisan psg:queue-health
```

## Scheduler (required)

Laravel’s schedule must tick **every minute**.

### Linux (cron)

```cron
* * * * * cd /var/www/psg_shifts && php artisan schedule:run >> /dev/null 2>&1
```

### Windows (Task Scheduler)

Create a task that runs every minute:

```bat
php C:\wamp64\www\psg_shifts\artisan schedule:run
```

For local development you can instead keep this running in a terminal:

```bash
php artisan schedule:work
```

## Scheduled commands (`routes/console.php`)

| Command | Cadence | Purpose |
|---------|---------|---------|
| `psg:backup-database --type=scheduled_daily` | Daily 01:30 (if schedule includes daily) | Catalogued DB + files backup |
| `psg:backup-database --type=scheduled_weekly` | Sunday 02:15 (if schedule includes weekly) | Weekly backup |
| `psg:backup-database --type=scheduled_monthly` | 1st of month 03:00 | Monthly long-retention backup |
| `psg:verify-backup` | Daily 04:00 | SHA-256 integrity of latest backup |
| `psg:test-restore-backup` | Sunday 04:30 | Non-destructive restore drill |
| `psg:backup-health --alert` | Hourly | Freshness / missed-backup alert |
| `psg:mark-overdue-invoices` | Daily 00:15 | Mark past-due invoices |
| `psg:sync-shift-statuses` | Every 15 minutes | In progress / completed / missed |
| `psg:scan-proactive-alerts` | Hourly | Understaffing, leave, documents, SLA, missed backups |
| `psg:export-accounting` | Daily 02:00 | Accounting export files (when enabled) |
| `psg:queue-health` | Hourly | Log/report queue backlog |
| `psg:release-shift-window-guards` | Daily 06:00 and 18:00 | Return pool guards after shift windows |

Backup schedule is controlled by System Settings / `PSG_BACKUP_SCHEDULE` (`daily`, `weekly`, or `daily_and_weekly`). Full DR procedure: [disaster-recovery.md](./disaster-recovery.md).

## Queue worker

### Development (foreground)

```bash
php artisan queue:work --stop-when-empty
# or keep running:
php artisan queue:work
```

### Linux (Supervisor example)

`/etc/supervisor/conf.d/psg-queue.conf`:

```ini
[program:psg-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/psg_shifts/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/psg_shifts/storage/logs/queue-worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start psg-queue:*
```

### Windows (NSSM example)

1. Install [NSSM](https://nssm.cc/).
2. Install a service pointing at `php.exe` with arguments:

```text
C:\wamp64\www\psg_shifts\artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

3. Set startup directory to `C:\wamp64\www\psg_shifts`.
4. Start the service and confirm with `php artisan psg:queue-health`.

## Mail / notification backlog

Workflow emails are queued (`WorkflowActionMail`). If SMTP hits a provider limit:

1. Stop or pause the worker (avoid useless retries).
2. Fix mailer settings or wait for the daily limit reset.
3. Optionally switch local/dev to `MAIL_MAILER=log` or disable workflow email in System Settings.
4. Restart `queue:work` and re-check `psg:queue-health`.

Failed jobs (after max tries) appear in `failed_jobs`:

```bash
php artisan queue:failed
php artisan queue:retry all
```

## Related commands

```bash
php artisan psg:production-check
php artisan psg:backup-database
php artisan psg:test-mail you@example.com
```

See also [DEPLOY.md](../DEPLOY.md) and [CONTRIBUTING.md](../CONTRIBUTING.md).
