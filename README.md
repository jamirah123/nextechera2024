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

Useful commands:

```bash
php artisan psg:production-check
php artisan psg:backup-database
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

Scheduled maintenance (requires `php artisan schedule:work` or cron — full table in [docs/ops-queue-scheduler.md](./docs/ops-queue-scheduler.md)):

- Database backups (daily and/or weekly per settings)
- Overdue invoice sync (`00:15`)
- Shift status sync (every 15 minutes)
- Proactive alerts (hourly)
- Accounting export (`02:00`, when enabled)
- Queue health check (hourly)
- Shift-window guard release (`06:00` and `18:00`)

## License

Proprietary — Platinum Security Group operations software.
