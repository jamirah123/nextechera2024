# Platinum Security Group — Guard Shift, Deployment & Operations Management

Production-oriented Laravel application for PSG operational workflows:

Company → Regions → Supervisors → Sites → Manpower → Guards → Deployments → Shifts → HR → Reports → Audit

## Requirements

- PHP 8.3+
- Composer 2
- Node.js 20+ (for Vite assets)
- MySQL 8+ / MariaDB (recommended for production) or SQLite (local/dev)
- `mysqldump` on PATH when using MySQL backups

## Quick start (local)

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000/login`

Seeded password for demo users: `Password@123`

| Role | Email |
|------|-------|
| Super Admin | `admin@platinumsecurity.local` |
| Managing Director | `md@platinumsecurity.local` |
| Operations Manager | `operations@platinumsecurity.local` |
| HR Manager | `hr@platinumsecurity.local` |
| Shift Manager | `shifts@platinumsecurity.local` |
| Finance Manager | `finance@platinumsecurity.local` |
| Procurement Officer | `procurement@platinumsecurity.local` |

## Tests & quality

```bash
composer test       # PHPUnit
composer lint       # Pint style check
composer analyse    # Larastan / PHPStan
composer format     # Auto-fix style
npm run build
```

See [CONTRIBUTING.md](./CONTRIBUTING.md) for the full developer workflow. CI runs on GitHub Actions for pushes/PRs to `main`.

## Production

See [DEPLOY.md](./DEPLOY.md) for deployment, backups, caching, and scheduler setup.  
Queue workers and the full schedule list: [docs/ops-queue-scheduler.md](./docs/ops-queue-scheduler.md).  
Backup & disaster recovery runbook: [docs/disaster-recovery.md](./docs/disaster-recovery.md).  
High-concurrency & graceful failure requirement: [docs/high-concurrency.md](./docs/high-concurrency.md).  
Load-test scaffolding: [tests/load/README.md](./tests/load/README.md).

Useful commands:

```bash
php artisan psg:production-check
php artisan psg:backup-database
php artisan psg:verify-backup
php artisan psg:test-restore-backup
php artisan psg:backup-health --alert
php artisan psg:test-mail you@example.com
php artisan schedule:work
php artisan queue:work
php artisan psg:queue-health
```

## Modules delivered

1. Auth & role dashboards  
2. Organization (regions, supervisors, clients, sites, manpower)  
3. Guards  
4. Deployments & transfers  
5. Shifts (validation, calendar, recurring, overrides)  
6. HR (leave, absence, desertion, attendance)  
7. Reporting (CSV + print)  
8. Operational dashboards (company → region → site → guard)  
9. Audit logs & active-user hardening  
10. Production tooling (backup, checks, deploy docs)  
11. Finance (UGX billing, headcount invoicing, payments, profitability, payroll)  
12. Administration (users, roles matrix, system settings, database backups)  
13. Live notifications (role-aware audit feed in the nav bar)
14. Procurement (assets/uniforms, supplier purchases)
15. Manpower gaps with overtime resolution
16. Historical / past-date operations (operational vs entry date; monthly period finalize under Organization → Operational periods)

## Demo seed

```bash
php artisan migrate:fresh --seed
```

Runs **SmallCompanySeeder** — a lean footprint for a small security company:

| Record | Count |
| --- | --- |
| HQ users | 5 (admin, ops, HR, shifts, finance) |
| Region supervisor users | 2 (Kampala + Western) |
| Regions | 2 (Kampala, Western) |
| Field supervisors | 2 (1 per region) |
| Clients | 4 |
| Sites | 4 (2 per region) |
| Guards | 16 (8 per region; 12 deployed, 4 awaiting) |
| Office staff | 3 |
| Active deployments | 12 |

Password for all seeded users: `Password@123`  
Primary login: `admin@platinumsecurity.local`  
Western supervisor: `supervisor.western@platinumsecurity.local`

### Realistic operational history (Jan → today)

After the lean seed (or on an existing small-company database), load multi-month history **through the same services** used by live users (deployments, duties, absences, leave, billing, invoices, payments, payroll, audits):

```bash
php artisan psg:seed-realistic
# optional:
php artisan psg:seed-realistic --from=2026-01-01 --to=2026-09-24
# wipe + lean seed + realistic history:
php artisan psg:seed-realistic --fresh
```

Does **not** invent orphan rows or bypass validations. Closed months get historical postings; the current month stays on active postings. Payroll is opened only for completed calendar months.

Scheduled maintenance (requires `php artisan schedule:work` or cron — full table in [docs/ops-queue-scheduler.md](./docs/ops-queue-scheduler.md)):

- Application backups (daily/weekly per settings + monthly; verify, restore drill, health alerts — [docs/disaster-recovery.md](./docs/disaster-recovery.md))
- Overdue invoice sync (`00:15`)
- Shift status sync (every 15 minutes)
- Proactive alerts (hourly)
- Accounting export (`02:00`, when enabled)
- Queue health check (hourly)
- Shift-window guard release (`06:00` and `18:00`)

## License

Proprietary — Platinum Security Group operations software.
