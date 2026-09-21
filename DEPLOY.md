# Deployment guide — Platinum Security Group Shifts

This document covers production configuration for the PSG Guard Shift, Deployment & Operations Management System.

## 1. Server prerequisites

- PHP 8.3 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`
- Composer 2
- Node.js 20+ (build assets on CI or build host; production only needs `public/build`)
- MySQL 8+ or MariaDB 10.6+
- Web server: Apache (WAMP/Laragon) or Nginx + PHP-FPM
- Cron / Task Scheduler access for Laravel’s scheduler
- `mysqldump` available for database backups

## 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Production `.env` essentials:

```env
APP_NAME="Platinum Security Group"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://shifts.your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=psg_shifts
DB_USERNAME=psg_app
DB_PASSWORD=use-a-strong-secret

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

PSG_BACKUP_KEEP=14
```

Never commit `.env`. Keep `APP_DEBUG=false` in production.

For error monitoring in production, install `sentry/sentry-laravel` when ready and set `SENTRY_LARAVEL_DSN` (see comments in `.env.example`).

### Mail (password reset & alerts)

Local development uses `MAIL_MAILER=log` (messages go to `storage/logs/laravel.log`).

Production example:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=ops@your-domain.com
MAIL_PASSWORD=your-smtp-password
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=ops@your-domain.com
MAIL_FROM_NAME="${PSG_COMPANY_NAME}"
```

Verify after configuring:

```bash
php artisan psg:test-mail admin@your-domain.com
```

Password reset emails use the company name from System Settings / `PSG_COMPANY_NAME`.

## 3. WAMP deployment (Windows)

Typical layout when the project lives at `C:\wamp64\www\psg_shifts`:

1. **Document root** must point to `C:\wamp64\www\psg_shifts\public` (not the project root).
2. Enable **mod_rewrite** in Apache and `AllowOverride All` for that directory.
3. Create a MySQL database (e.g. `psg_shifts`) via phpMyAdmin.
4. Copy `.env.example` to `.env`, set MySQL credentials:

```env
APP_URL=http://localhost/psg_shifts/public
# or a virtual host: APP_URL=http://psg-shifts.local

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=psg_shifts
DB_USERNAME=root
DB_PASSWORD=
```

5. Install and build:

```bash
cd C:\wamp64\www\psg_shifts
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

6. Ensure `storage/` and `bootstrap/cache/` are writable by the Apache user.
7. Add **MySQL bin** to PATH so `mysqldump` works for backups (`C:\wamp64\bin\mysql\mysql8.x.x\bin`).
8. Schedule **Task Scheduler** to run every minute:

```bat
php C:\wamp64\www\psg_shifts\artisan schedule:run
```

Optional virtual host (`httpd-vhosts.conf`):

```apache
<VirtualHost *:80>
    ServerName psg-shifts.local
    DocumentRoot "C:/wamp64/www/psg_shifts/public"
    <Directory "C:/wamp64/www/psg_shifts/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add `127.0.0.1 psg-shifts.local` to `C:\Windows\System32\drivers\etc\hosts`.

## 4. Install & migrate

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force   # first deploy only, if seeding admins
php artisan storage:link
```

## 5. Optimize Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

After `.env` or route changes, refresh caches:

```bash
php artisan optimize:clear
php artisan optimize
```

## 6. Web server

Point the document root to `/public`.

### Apache (example)

Ensure `AllowOverride All` and `mod_rewrite` are enabled so `public/.htaccess` works.

### Nginx (example)

```nginx
root /var/www/psg_shifts/public;
index index.php;
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
}
```

## 7. Scheduler & queues

Run the scheduler every minute:

```cron
* * * * * cd /var/www/psg_shifts && php artisan schedule:run >> /dev/null 2>&1
```

On Windows Task Scheduler, run the equivalent every minute.

Daily database backup is registered at **01:30** (plus weekly/monthly per settings) via `routes/console.php`. Integrity verification, restore drills, and missed-backup health checks are also scheduled. Full procedure: [docs/disaster-recovery.md](./docs/disaster-recovery.md).

Overdue invoice sync runs daily at **00:15**:

```bash
php artisan psg:mark-overdue-invoices
```

Shift lifecycle sync runs **every 15 minutes** (scheduled/confirmed → in progress; elapsed → missed):

```bash
php artisan psg:sync-shift-statuses
```

Backups are written to `storage/app/backups/` (database dump + optional private-files ZIP). Configure `PSG_BACKUP_OFFSITE_DISK=s3` for a separate copy.

If using database queues:

```bash
php artisan queue:work --sleep=3 --tries=3
```

Use a process manager (Supervisor / NSSM) in production. Full schedule table, Supervisor/NSSM samples, and mail-backlog handling: [docs/ops-queue-scheduler.md](./docs/ops-queue-scheduler.md).

## 8. Backups

Manual backup:

```bash
php artisan psg:backup-database --type=manual
```

- **SQLite**: copies the DB file  
- **MySQL/MariaDB**: runs `mysqldump` into `.sql`
- **Files**: zips `storage/app/private` when `PSG_BACKUP_INCLUDE_FILES=true`
- **Verify**: `php artisan psg:verify-backup`
- **Restore drill**: `php artisan psg:test-restore-backup`
- **Restore**: admin console (type `RESTORE`) or `php artisan psg:restore-backup {reference} --force`

Store copies off-server (object storage / network share). Follow [docs/disaster-recovery.md](./docs/disaster-recovery.md) and test restores quarterly.

## 9. Health check

```bash
php artisan psg:production-check
```

Confirms app key, schema, writable storage, and built frontend assets. Warns if mail is still set to `log` in production.

## 10. Security checklist

- [ ] `APP_DEBUG=false`
- [ ] Strong `APP_KEY` and DB password
- [ ] HTTPS + `SESSION_SECURE_COOKIE=true`
- [ ] Inactive users cannot remain signed in (`EnsureUserIsActive`)
- [ ] Audit logs reviewed by Super Admin / Operations
- [ ] Only Ops/Super Admin can authorize shift overrides
- [ ] File permissions: web user owns `storage/` and `bootstrap/cache/`
- [ ] SMTP configured and verified with `psg:test-mail`

- [ ] Disable directory listing

## 11. Rollback

1. Put app in maintenance: `php artisan down`
2. Restore previous release code + `public/build`
3. Restore DB from latest `storage/app/backups/` dump
4. `php artisan migrate --force` only if schema matches the release
5. `php artisan up`

## 12. Smoke test after deploy

1. Login as Super Admin  
2. Open Ops Dashboard and Manpower Coverage  
3. Create/view a shift  
4. Export a CSV report  
5. Confirm a new row appears in Audit Logs  
6. Send `php artisan psg:test-mail` to an inbox you control  
7. Request **Forgot password** on the login page and confirm the email arrives  

## Support contacts

Document internal owners for:

- Application / Laravel releases  
- Database administration  
- Server / SSL certificates  
