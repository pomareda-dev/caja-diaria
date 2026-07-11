# Deployment Notes — Future Shared Hosting

> Current state: **local-only.** This document captures the steps and considerations
> for a future production deployment on shared hosting. It does **not** execute
> any deployment — the user manages the hosting setup.

## Target environment

| Item | Value |
|------|-------|
| Hosting type | Shared hosting (cPanel or equivalent) |
| Domain | Subdomain of `pomareda.dev` (managed by user) |
| PHP | 8.3+ (required) |
| Database | MySQL (shared SQLite has concurrency issues on shared hosting) |
| Web server | Apache/Nginx (provider-dependent) |
| App URL | `https://{subdomain}.pomareda.dev` |

## Why SQLite is local-only

SQLite works for a single local user. On shared hosting, concurrent requests
and file-locking cause `database is locked` errors. MySQL is the natural upgrade
and the app is designed to be MySQL-compatible (no SQLite-specific SQL).

## Pre-deployment checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Set `APP_URL=https://{subdomain}.pomareda.dev` in `.env`
- [ ] Generate a fresh `APP_KEY` (`php artisan key:generate`)
- [ ] Switch `DB_CONNECTION=mysql` and configure `DB_HOST`, `DB_PORT`,
      `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- [ ] Set `SESSION_DRIVER=database` (not file — shared hosting sessions are
      unreliable on the filesystem driver)
- [ ] Set `CACHE_STORE=database` or a supported prefix
- [ ] Set `QUEUE_CONNECTION=database` (no Redis on basic shared hosting)
- [ ] Set `FILESYSTEM_DISK=local` (or a CDN disk if added later)
- [ ] Run `php artisan migrate --force` on the server (remove `--seed` for prod)
- [ ] Run `php artisan storage:link` on the server (avatars symlink)
- [ ] Build frontend: `npm run build` (or `build:ssr` if SSR is desired)
- [ ] Configure HTTPS (Let's Encrypt via provider or manual)
- [ ] Set `APP_LOCALE=es_PE` and confirm `APP_FAKER_LOCALE` is irrelevant in prod

## Database migration: SQLite → MySQL

The schema is MySQL-compatible. To migrate existing local data:

1. Export SQLite data:
   ```bash
   sqlite3 database/database.sqlite .dump > data.sql
   ```
2. Clean SQLite-specific syntax (autoincrement, `IF NOT EXISTS`, etc.) or use
   a tool like `sequelpro`/`DBeaver` to transfer table-by-table.
3. Create the MySQL database on the hosting panel.
4. Import into MySQL via phpMyAdmin or CLI:
   ```bash
   mysql -u {user} -p {database} < data.sql
   ```
5. Run `php artisan migrate --force` to ensure the schema is at the latest
   migration (catches any columns the dump missed).

Alternatively, start fresh on production and re-enter data manually (the app
is for personal use with a manageable volume).

## Web server configuration

### Apache (most shared hosting)

Point the document root to `public/`:

```
DocumentRoot /home/{user}/{subdomain}/public
```

Ensure `.htaccess` in `public/` is present (comes with Laravel). If the
provider doesn't allow `AllowOverride All`, copy the rewrite rules into the
virtual host config.

### Nginx (some providers)

```nginx
server {
    listen 80;
    server_name {subdomain}.pomareda.dev;
    root /home/{user}/{subdomain}/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Optimization commands (run after deploy)

```bash
php artisan config:cache       # cache config (mandatory in prod)
php artisan route:cache        # cache routes
php artisan view:cache         # cache compiled views
php artisan event:cache        # cache event-listener mappings
php artisan optimize           # runs all of the above
```

> After any `.env` change, re-run `php artisan config:cache` to pick up new
> values. Stale config cache is a common source of production bugs.

## Scheduled tasks

The projection generator can run on a schedule to keep projected movements
up-to-date:

```bash
# In app/Console/Kernel.php or routes/console.php:
Schedule::command('app:generate-projections')->daily();
```

On shared hosting without SSH access to the scheduler, use the hosting
panel's cron interface:

```
* * * * * cd /home/{user}/{subdomain} && php artisan schedule:run >> /dev/null 2>&1
```

## Permissions

| Path | Required permission |
|------|---------------------|
| `storage/` | Writable by web server (775 or 755 depending on suPHP) |
| `bootstrap/cache/` | Writable by web server |
| `public/avatars/` | Writable (profile photo uploads) |
| `.env` | Readable by web server, NOT publicly accessible |

## Portability considerations (by design)

- The app stores all config in `.env` — no hard-coded URLs or credentials.
- SQLite is swappable for MySQL via `.env` alone (no code changes).
- No dependency on managed services (queues, Redis, cloud storage).
- Single-currency (PEN) — no exchange-rate APIs to manage.
- The `settings` JSON column on `users` stores preferences without schema
  migrations per new setting.

## Security notes

- `APP_DEBUG=false` is mandatory — debug mode leaks env vars in stack traces.
- Keep `APP_KEY` secret and backed up — it encrypts session/cookie data.
- The app uses Fortify for auth (rate-limited login, 2FA-ready).
- Regenerate `APP_KEY` only on a fresh install — rotating it invalidates all
  existing sessions and encrypted data.