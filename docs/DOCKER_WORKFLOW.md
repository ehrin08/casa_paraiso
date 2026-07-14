# Casa Paraiso Docker Workflow

## Purpose

Use Laravel Sail's Docker services as the primary local development environment for Casa Paraiso.

Docker is for local development only. Production remains Hostinger shared/web hosting, so the project must not require Docker, VPS access, persistent Node.js services, Redis, custom daemons, or server-level package management in production.

## Services

The Sail-generated Compose setup uses:

- `laravel.test`: Laravel app container using Sail PHP 8.2.
- `mariadb`: local MariaDB database for development.
- `mailpit`: local email testing.
- `cloudflared`: optional, profile-gated HTTPS tunnel for short-lived remote testing.

The app is served through the Laravel app container. It does not use a custom Nginx/PHP-FPM stack for this MVP.

The container mounts its `vendor/` directory from the `sail-vendor` named volume. Keeping Composer packages on Docker's Linux filesystem avoids repeatedly loading Laravel framework files through the slower Windows bind mount. Application source files remain bind-mounted for immediate editing.

On this Windows machine, use `docker compose` directly. The `vendor\bin\sail.bat` wrapper depends on a working Bash/WSL shim, and that shim is not currently reliable here.

## Local URLs

- App: `http://localhost:8001`
- Vite: `http://localhost:5173`
- MariaDB forwarded port: `3307`
- Mailpit SMTP forwarded port: `1026`
- Mailpit dashboard: `http://localhost:8025`

These ports avoid common conflicts with XAMPP Apache on port 80 and XAMPP MySQL on port 3306.

## First-Time Setup

From the project root, install dependencies first if `vendor/` or `node_modules/` is missing:

```powershell
composer install
npm install
```

Then start the Docker services and verify the app:

```powershell
docker compose up -d
docker compose exec -T laravel.test composer install
docker compose restart laravel.test
docker compose exec -T --user sail laravel.test php artisan migrate:fresh --seed
docker compose exec -T laravel.test npm run build
docker compose exec -T --user sail laravel.test php artisan test
```

The host `composer install` is still needed after a clean clone because Sail's Docker build context comes from `vendor/laravel/sail`. The in-container install populates the separate `sail-vendor` volume used by the running application. Restart `laravel.test` afterward because its web process may have stopped while the empty volume was being populated.

When `composer.lock` changes, refresh both dependency locations:

```powershell
composer install
docker compose exec -T laravel.test composer install
```

Do not delete the `sail-vendor` volume during normal shutdown. `docker compose down` keeps it; `docker compose down -v` removes it and requires another in-container Composer install.

## Daily Commands

Start containers:

```powershell
docker compose up -d
```

Stop containers:

```powershell
docker compose down
```

Run migrations:

```powershell
docker compose exec --user sail laravel.test php artisan migrate
```

Refresh PHP dependencies after a Composer change:

```powershell
composer install
docker compose exec -T laravel.test composer install
```

Run tests:

```powershell
docker compose exec --user sail laravel.test php artisan test
```

Build frontend assets:

```powershell
docker compose exec laravel.test npm run build
```

If a host-side `npm run build` reports a missing Rollup optional dependency such as
`@rollup/rollup-win32-x64-msvc`, restore the host dependency tree without changing
`package-lock.json`:

```powershell
Remove-Item -Recurse -Force node_modules
npm install
```

Use the Docker build command above as the primary verification path.

Start Vite dev server:

```powershell
docker compose exec laravel.test npm run dev
```

## Environment Defaults

Use these local Docker values:

```env
APP_URL=http://localhost:8001
APP_PORT=8001
VITE_PORT=5173
DB_CONNECTION=mariadb
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=casa_paraiso
DB_USERNAME=sail
DB_PASSWORD=password
FORWARD_DB_PORT=3307
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=mailpit
MAIL_PORT=1025
FORWARD_MAILPIT_PORT=1026
FORWARD_MAILPIT_DASHBOARD_PORT=8025
```

Mailpit remains the safe default for clean clones. It captures messages locally and never delivers them to a real inbox.

## Real Gmail Delivery

Use Gmail SMTP only when real delivery is intentional. The Gmail account must have 2-Step Verification enabled and use a generated app password, not the normal account password. Keep the app password only in the ignored local `.env`; never commit it, paste it into chat, or print it in terminal output. Remove the display spaces from Google's 16-character value and keep the resulting value inside double quotes so Laravel can parse it safely.

Set the local mail values to:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME="ehrinjohn08@gmail.com"
MAIL_PASSWORD="replace-with-the-local-app-password"
MAIL_FROM_ADDRESS="ehrinjohn08@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Laravel selects SMTP with automatic STARTTLS for port `587`. This mailer is global, so both password-reset and email-verification notifications will be delivered through Gmail. After saving the secret, clear cached configuration without displaying it:

```powershell
docker compose exec -T --user sail laravel.test php artisan optimize:clear
```

Google references:

- App passwords: https://support.google.com/accounts/answer/185833
- Gmail SMTP settings: https://support.google.com/a/answer/176600

## Temporary Public Reset Links

The `cloudflared` service is disabled by default through the `tunnel` Compose profile. A Quick Tunnel creates a random public `https://*.trycloudflare.com` URL and is for temporary development testing only. It has no uptime guarantee, and the URL changes whenever the tunnel is recreated.

Before starting a tunnel, disable debug output and require HTTPS-only session cookies in the ignored local `.env`:

```env
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
```

Apply those settings, then start the profile and read the generated public URL from its logs:

```powershell
docker compose exec -T --user sail laravel.test php artisan optimize:clear
docker compose --profile tunnel up -d cloudflared
docker compose logs cloudflared
```

Copy the generated HTTPS URL into `APP_URL`, clear configuration again, and submit the forgot-password form through that public URL:

```env
APP_URL=https://random-words.trycloudflare.com
```

```powershell
docker compose exec -T --user sail laravel.test php artisan optimize:clear
```

The entire local application is public while the tunnel runs. Keep `APP_DEBUG=false`, do not share the URL, and stop the tunnel immediately after the reset-link check:

```powershell
docker compose --profile tunnel stop cloudflared
docker compose --profile tunnel rm -f cloudflared
```

Restore the normal local settings and clear configuration:

```env
APP_URL=http://localhost:8001
APP_DEBUG=true
SESSION_SECURE_COOKIE=null
```

```powershell
docker compose exec -T --user sail laravel.test php artisan optimize:clear
```

Gmail SMTP may remain enabled after the tunnel stops. Reset links generated through localhost will then work only on this development computer. Old Quick Tunnel links stop working when their tunnel closes.

Cloudflare reference: https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/do-more-with-tunnels/trycloudflare/

## XAMPP Fallback

XAMPP can remain installed as a fallback local environment, but future project work should prefer Docker Compose commands.

If using XAMPP instead of Sail:

- Set `DB_HOST=127.0.0.1`.
- Set `DB_USERNAME=root`.
- Use the XAMPP MySQL database manually.
- Run PHP/Composer/npm commands on the host machine.

## Production Boundary

Do not deploy Sail containers to Hostinger shared/web hosting.

For Hostinger:

- Build assets locally with Docker Compose or host Node.
- Upload/deploy the Laravel application using Hostinger-compatible PHP hosting.
- Configure production `.env` with Hostinger database credentials.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Point web requests to Laravel's `public/index.php` entrypoint or equivalent shared-hosting setup.
- When Hostinger Terminal or SSH is available, install and optimize after the production `.env` is configured:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize
```

`php artisan optimize` caches Laravel configuration, events, routes, and Blade views. Run it on the Linux hosting environment, not during normal Windows/XAMPP development. Re-run it after deploying application, route, configuration, or view changes.

## CRUD Remediation Verification And Recovery

Use the following sequence after deploying CRUD remediation code.

The remediation adds `2026_07_12_020000_add_generation_key_to_promotion_suggestions.php`. This nullable unique key makes promotion generation idempotent without rewriting historical suggestions. Treat it as an approval-gated schema change in every non-testing environment.

1. Create a database export before applying migrations or data repairs.
2. Obtain explicit approval for the exact migration or repair operation.
3. Apply migrations with `php artisan migrate --force` only in the intended environment.
4. Run the read-only integrity audit:

```powershell
docker compose exec -T --user sail laravel.test php artisan casa:audit-crud-integrity
```

5. Run the isolated Feature suite configured by `phpunit.xml` (the 2026-07-12 remediation baseline is 104 tests and 828 assertions):

```powershell
docker compose exec -T --user sail laravel.test php artisan test --testsuite=Feature
```

6. Run the remaining quality gates:

```powershell
docker compose exec -T laravel.test ./vendor/bin/pint --test
docker compose exec -T laravel.test composer validate
docker compose exec -T --user sail laravel.test php artisan view:cache
docker compose exec -T laravel.test npm run build
```

7. Verify the admin, staff, and customer workspaces in signed-in browser sessions, then inspect the browser console and `storage/logs/laravel.log` for new errors.

`composer validate` currently succeeds with an advisory that `laravel/socialite` is pinned to exact version `5.28`. Changing that dependency policy requires a separate Composer lockfile review and is not part of the CRUD remediation.

The integrity audit reports orphaned operational relationships and inconsistent status metadata. It never modifies records. A non-zero exit code means the reported records require review before deployment.

For current orphaned appointment findings and the approval-gated repair sequence, see `docs/CRUD_DATA_REPAIR_PLAN.md`.

The approved local repair was completed on 2026-07-12 using `casa:repair-approved-appointment-references --execute`. The command performs a safe dry run unless `--execute` is supplied and refuses to proceed if appointment status, therapist eligibility, schedule coverage, overlap, or deleted-reference conditions have changed.

### Rollback

- Keep the pre-change database export until post-deployment verification is complete.
- Roll back application files to the previous known-good release before restoring data.
- Restore the database export through the matching local database tool or Hostinger hPanel/phpMyAdmin.
- Do not run a broad production `migrate:rollback` without confirming every migration in the batch is safe to reverse.
- After restoration, run `php artisan optimize:clear`, then repeat the read-only integrity audit and browser smoke test.
