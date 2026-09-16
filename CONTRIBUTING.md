# Contributing — Platinum Security Group Shifts

Thanks for working on the PSG operations platform. This guide covers the expected developer workflow.

## Prerequisites

- PHP 8.3+
- Composer 2
- Node.js 20+
- MySQL 8+ (WAMP/Laragon) or SQLite for quick local use

## Setup

```bash
composer setup
# or manually:
composer install
copy .env.example .env   # Windows
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Demo login: `admin@platinumsecurity.local` / `Password@123` (see [README.md](./README.md)).

Keep a queue worker and scheduler running while testing notifications and background jobs — see [docs/ops-queue-scheduler.md](./docs/ops-queue-scheduler.md).

## Quality gates (run before opening a PR)

```bash
composer format    # Laravel Pint — auto-fix style
composer lint      # Pint --test (CI uses this)
composer analyse   # Larastan / PHPStan level 5 (+ baseline)
composer test      # PHPUnit
npm run build      # Vite production build
```

Or: `composer ci` for lint + analyse + test.

GitHub Actions runs the same checks on push/PR to `main`.

## Branch & PR expectations

1. Branch from `main` with a short descriptive name (`fix/…`, `feat/…`, `chore/…`).
2. Keep PRs focused; avoid mixing unrelated refactors.
3. Ensure CI is green.
4. Prefer Form Requests + policies for new HTTP endpoints.
5. Add or update Feature tests for behaviour changes; Unit tests for pure calculation/services.
6. Do not commit `.env`, secrets, or real backup dumps.

## Architecture notes

- Domain logic lives in `app/Services/`
- Authorization: `app/Policies/` + permission matrix
- Schedules: `routes/console.php`
- Product config: `config/psg.php` and System Settings UI

## Static analysis baseline

`phpstan-baseline.neon` freezes known pre-existing issues so CI fails only on **new** findings. When you touch a file, prefer fixing baseline entries in that area rather than adding more.

## Production

See [DEPLOY.md](./DEPLOY.md) for deploy, backups, and environment hardening.
