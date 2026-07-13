# Casa Paraiso Tech Stack

## Approved Stack

Casa Paraiso should be built as a production-ready Laravel monolith that stays compatible with Hostinger shared/web hosting and Docker/Sail local development.

- Backend: Laravel 12
- Runtime: PHP 8.2 or higher
- Views: Laravel Blade server-rendered templates
- Frontend styling: Tailwind CSS v4 through Vite
- Database: MariaDB/MySQL
- Database workflow: Laravel migrations and seeders
- Package management: Composer for PHP dependencies, npm for frontend build dependencies
- Authentication: Laravel Socialite verified Google OAuth as the only public, self-service customer provisioning path; protected super-admin preauthorization plus password login, setup, and reset flows for existing customers and staff/admin accounts
- Primary local development: Docker with Laravel Sail services managed through direct Docker Compose commands
- Fallback local development: XAMPP / Apache
- Production hosting: Hostinger shared/web hosting by default

## Frontend Decision

Use Tailwind CSS instead of Bootstrap for the MVP frontend.

Rationale:

- Tailwind supports a more custom commercial-grade visual identity for Casa Paraiso.
- Tailwind works well with Laravel Blade and Vite.
- Tailwind allows the team to build polished admin, staff, and customer interfaces without adopting a full SPA.
- The extra npm/Vite build step is acceptable because Node is only used during development and asset compilation.

Bootstrap is not selected for the MVP frontend because it prioritizes fastest CRUD scaffolding and built-in components over a more custom product interface.

## Deferred Frontend Options

Do not introduce these unless explicitly approved later:

- Inertia
- React
- Vue
- Livewire
- External UI kits
- Persistent Node.js runtime in production

Use targeted JavaScript only where it directly improves a workflow, such as appointment filters, availability checks, charts, confirmation dialogs, or report filtering.

Turbo Drive 8 is approved as the targeted navigation enhancement for the Blade application. It is bundled into the compiled Vite assets and is enabled only for safe same-origin GET links and filter forms; state-changing forms, exports, and specialized panels retain normal Laravel behavior.

## Database And Data Workflow

- Use Laravel migrations as the source of truth for schema changes.
- Use Laravel seeders for initial roles, default settings, sample services, and demo data.
- Keep schema compatible with MariaDB/MySQL for Hostinger.
- Provide SQL exports when needed for Hostinger/phpMyAdmin handover, but do not use manual phpMyAdmin edits as the primary development workflow.

## Deployment Notes

- Compile production frontend assets with `npm run build`.
- Deploy compiled assets from Laravel's public build output.
- Use Docker/Sail for local development only.
- Do not deploy Sail containers to Hostinger shared/web hosting.
- Do not require Node.js to run on Hostinger production for the MVP.
- Keep `.env` credentials out of committed source files.
- Set `APP_ENV=production` and `APP_DEBUG=false` in production.
- Hostinger must point web requests to Laravel's `public/index.php` entrypoint or an equivalent safe shared-hosting configuration.
- When Hostinger Terminal or SSH is available, run `composer install --no-dev --optimize-autoloader` followed by `php artisan optimize` after the production environment is configured.
- Do not build or upload Laravel configuration/view caches from Windows; production caches must be generated on the Linux hosting environment.

## Local Development Checks

For primary Docker/Sail development:

- Confirm Docker and Docker Compose are available.
- Start services with `docker compose up -d`.
- Use the Sail MariaDB service for local database work.
- Use `docker compose exec laravel.test ...` for Composer, npm, Artisan, migrations, and tests.
- Avoid `.\vendor\bin\sail.bat` on this machine unless Bash/WSL is repaired.

For XAMPP fallback:

- Confirm PHP is 8.2 or higher.
- Confirm required PHP extensions are enabled for Laravel.
- Confirm Node and npm are available for Vite and Tailwind asset builds.
- Confirm MySQL/MariaDB is available locally through XAMPP.

Current local environment observed during planning:

- PHP: 8.2.12
- Composer: 2.9.7
- Node: 24.15.0
- npm: 11.12.1
- Docker: 29.4.2
- Docker Compose: 5.1.3

## Verification Commands After Scaffolding

Use these commands once the Laravel project exists:

```bash
docker compose up -d
docker compose exec -T laravel.test composer install
docker compose restart laravel.test
docker compose exec -T laravel.test npm install
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan migrate
docker compose exec -T laravel.test php artisan test
```

Demo seeding is restricted to local and testing environments. Production deployments must run migrations without `--seed` so predictable demo credentials are never installed or reset.

## References

- Laravel 12 deployment: https://laravel.com/docs/12.x/deployment
- Laravel 12 release support: https://laravel.com/docs/12.x/releases
- Tailwind Laravel guide: https://tailwindcss.com/docs/guides/laravel
- Bootstrap quick start: https://getbootstrap.com/docs/5.3/getting-started/introduction/
