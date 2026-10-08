# Replace the live database with the current seed

This replaces the application tables with the dataset on `seed/current-300-guards`. It deletes the company data already in that database. Updating Git does not run this. The replacement command is a separate step and stops unless the exact confirmation phrase is typed.

The production `.env` stays on the server. Do not copy a local `.env` onto the server, and do not commit `.env`.

## 1. Switch the server to the seed branch

From the project directory on the server:

```bash
cd /home/u602000060/domains/YOUR-DOMAIN/public_html
git branch --show-current
git fetch origin
git checkout seed/current-300-guards
git pull origin seed/current-300-guards
git branch --show-current
```

The last command must print `seed/current-300-guards`.

## 2. Install and build

```bash
composer install --no-dev --optimize-autoloader
npm ci
export RAYON_NUM_THREADS=2
npm run build
```

Leave the server `.env` as the production file:

```env
APP_ENV=production
APP_DEBUG=false
```

For this reload only, also set:

```env
PSG_SEED_ALLOW_PRODUCTION=true
```

`yes` is not accepted in place of that setting.

## 3. Replace the database

```bash
php artisan psg:replace-seeded-database
```

The command prints `APP_ENV`, `APP_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, and `DB_USERNAME`. It does not print the database password.

It then requires:

1. The database name, typed exactly.
2. On production, the phrase `REPLACE-PRODUCTION-DATABASE`.

`y`, `yes`, and `true` do not continue the command.

After that phrase, the command backs up the database, checks the backup file, drops and recreates the application tables, runs the current migrations, and runs `DatabaseSeeder`, which calls only `LargeCompanySeeder`. If the backup cannot be created, the command stops. `--allow-missing-backup` is the only override, and production still requires the phrase above.

## 4. Lock the server down again

Set this back before anyone uses the site:

```env
PSG_SEED_ALLOW_PRODUCTION=false
```

Then:

```bash
php artisan optimize:clear
php artisan optimize
php artisan about
```

`about` should show Environment `production` and Debug Mode OFF. Sign in with `shifts@platinumsecurity.local` and `Password@123`, and open the posting board. Today's windows should be Available because the seeded postings are closed.

Do not merge this branch into `main` until that dataset is the one `main` should keep.
