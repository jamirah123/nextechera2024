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
LOG_LEVEL=warning

PSG_BACKUP_KEEP=14
```

Never commit `.env`. Keep `APP_DEBUG=false` in production.

## 3. Install & migrate

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force   # first deploy only, if seeding admins
php artisan storage:link
```

## 4. Optimize Laravel

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

## 5. Web server

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

## 6. Scheduler & queues

Run the scheduler every minute:

```cron
* * * * * cd /var/www/psg_shifts && php artisan schedule:run >> /dev/null 2>&1
```

On Windows Task Scheduler, run the equivalent every minute.

Daily database backup is registered at **01:30** via:

```php
Schedule::command('psg:backup-database --keep=14')
```

Backups are written to `storage/app/backups/`.

If using database queues:

```bash
php artisan queue:work --sleep=3 --tries=3
```

Use a process manager (Supervisor / NSSM) in production.

## 7. Backups

Manual backup:

```bash
php artisan psg:backup-database --keep=14
```

- **SQLite**: copies the DB file  
- **MySQL/MariaDB**: runs `mysqldump` into `.sql`

Store copies off-server (object storage / network share). Test restores quarterly.

## 8. Health check

```bash
php artisan psg:production-check
```

Confirms app key, schema, writable storage, and built frontend assets.

## 9. Security checklist

- [ ] `APP_DEBUG=false`
- [ ] Strong `APP_KEY` and DB password
- [ ] HTTPS + `SESSION_SECURE_COOKIE=true`
- [ ] Inactive users cannot remain signed in (`EnsureUserIsActive`)
- [ ] Audit logs reviewed by Super Admin / Operations
- [ ] Only Ops/Super Admin can authorize shift overrides
- [ ] File permissions: web user owns `storage/` and `bootstrap/cache/`
- [ ] Disable directory listing

## 10. Rollback

1. Put app in maintenance: `php artisan down`
2. Restore previous release code + `public/build`
3. Restore DB from latest `storage/app/backups/` dump
4. `php artisan migrate --force` only if schema matches the release
5. `php artisan up`

## 11. Smoke test after deploy

1. Login as Super Admin  
2. Open Ops Dashboard and Manpower Coverage  
3. Create/view a shift  
4. Export a CSV report  
5. Confirm a new row appears in Audit Logs  

## Support contacts

Document internal owners for:

- Application / Laravel releases  
- Database administration  
- Server / SSL certificates  
