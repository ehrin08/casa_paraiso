# Casa Paraiso Security Hardening Checklist

## Purpose And Status

This checklist is the release gate for the Laravel application. The application-level baseline is implemented and covered by feature tests. Production environment, hosting, TLS, backup, and recovery items must be rechecked on the actual Hostinger target before launch.

This document never authorizes a database write. Migrations, imports, restores, reseeding, and record changes remain subject to the exact-operation approval gate in `AGENTS.md`.

## Implemented Application Controls

- [x] Require authentication, active-account status, verified email, and explicit role middleware for authenticated workspaces.
- [x] Keep Super Administrator identity protection separate from ordinary Admin authorization.
- [x] Protect state-changing forms with Laravel CSRF middleware and validate writes through Form Requests or equivalent controller validation.
- [x] Rotate sessions during authentication and invalidate sessions through the existing account-security workflows.
- [x] Use Laravel password hashing, password reset, email verification, and time-limited Google identity confirmation.
- [x] Apply named rate limiters to registration, password recovery/reset, password confirmation/update, Google identity callbacks, and account deletion flows.
- [x] Add `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and `Cross-Origin-Opener-Policy` headers to browser responses.
- [x] Support HTTPS URL enforcement and HSTS through environment-controlled settings. HSTS is emitted only for secure requests when enabled.
- [x] Support explicit production host allow-listing through `TRUSTED_HOSTS`.
- [x] Keep environment files, local backups, and generated testing artifacts outside version control.
- [x] Keep production demo seeding disabled in `DatabaseSeeder`.
- [x] Restrict Admin Settings to Admin and Super Administrator; keep user provisioning exclusive to the protected Super Administrator.

## Production Environment Gate

Set and verify these values on the production host. Do not copy local secrets or commit the production `.env` file.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-approved-domain.example
FORCE_HTTPS=true
HSTS_ENABLED=true
TRUSTED_HOSTS=your-approved-domain.example,www.your-approved-domain.example

SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true

LOG_LEVEL=warning
```

- [ ] Generate a unique production `APP_KEY`; never reuse a published or sample key.
- [ ] Use a least-privilege database account scoped to the production database.
- [ ] Configure production mail, Google OAuth, database, and protected Super Administrator credentials only in the hosting environment.
- [ ] Verify HTTPS and certificate renewal before enabling HSTS. Keep HSTS disabled until every approved host works over HTTPS.
- [ ] Point the web document root to Laravel's `public` directory; application source, `.env`, storage data, and vendor internals must not be web-accessible.
- [ ] Confirm storage and bootstrap cache directories are writable without broad world-writable permissions.
- [ ] Run `composer install --no-dev --optimize-autoloader` and `php artisan optimize` on the Linux production environment after configuration is complete.
- [ ] Verify error pages reveal no stack traces, credentials, absolute paths, or customer data.

Laravel's deployment guidance requires the web server to direct requests to `public/index.php`, recommends production optimization, and warns that `APP_DEBUG` must be `false` in production: <https://laravel.com/docs/12.x/deployment>.

## Hosting And Data Protection Gate

- [ ] Confirm Hostinger backups cover the application files and MariaDB database and document the retention period.
- [ ] Complete one restore rehearsal in a non-production environment before launch.
- [ ] Store manual SQL exports outside the public web root with restricted access and an agreed retention/deletion schedule.
- [ ] Confirm production logs do not contain passwords, reset tokens, OAuth tokens, session IDs, full payment notes, or unnecessary customer data.
- [ ] Review Admin and Super Administrator accounts before release; deactivate unused accounts and remove stale OAuth credentials.
- [ ] Verify rate limiting, mail delivery, password reset, email verification, Google sign-in, logout, and session revocation on the production domain.
- [ ] Record the deployed commit, migration batch, build date, PHP version, and rollback/restore contact for each release.

## Content Security Policy Decision

A strict Content Security Policy is deferred until inline Alpine expressions and other inline behavior are migrated to a nonce-compatible policy and tested across all four workspaces. Do not deploy an untested CSP that breaks booking, navigation, modals, calendars, or payment workflows. The current header baseline still blocks MIME sniffing and framing while applying conservative browser permissions.

## Verification

Run application checks against the isolated test database:

```powershell
docker compose exec -T --user sail laravel.test php artisan test
docker compose exec -T laravel.test npm run build
docker compose exec -T --user sail laravel.test php artisan view:cache
docker compose exec -T --user sail laravel.test php artisan view:clear
docker compose exec -T laravel.test vendor/bin/pint --test
```

Representative coverage includes `SecurityHardeningTest`, `RoleWorkspaceTest`, `AuthenticatedWorkspaceSmokeTest`, authentication tests, and role-specific workflow suites.

Migration execution is deliberately separate. Before running a migration command, identify the target database and exact command and obtain explicit approval under `AGENTS.md`.

Laravel supports named route rate limiters and `throttle` middleware as used by this application: <https://laravel.com/docs/12.x/routing#rate-limiting>. Trusted proxy and host configuration is documented in Laravel's request guidance: <https://laravel.com/docs/12.x/requests#configuring-trusted-hosts>.
