# Casa Paraiso Docker Workflow

## Purpose

Use Laravel Sail's Docker services as the primary local development environment for Casa Paraiso.

Docker is for local development only. Production remains Hostinger shared/web hosting, so the project must not require Docker, VPS access, persistent Node.js services, Redis, custom daemons, or server-level package management in production.

## Services

The Sail-generated Compose setup uses:

- `laravel.test`: Laravel app container using Sail PHP 8.2.
- `mariadb`: local MariaDB database for development.
- `mailpit`: local email testing.

The app is served through the Laravel app container. It does not use a custom Nginx/PHP-FPM stack for this MVP.

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
docker compose exec -T laravel.test php artisan migrate:fresh --seed
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

The first `composer install` is still needed after a clean clone because Sail's Docker build context comes from `vendor/laravel/sail`.

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
docker compose exec laravel.test php artisan migrate
```

Run tests:

```powershell
docker compose exec laravel.test php artisan test
```

Build frontend assets:

```powershell
docker compose exec laravel.test npm run build
```

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
MAIL_HOST=mailpit
MAIL_PORT=1025
FORWARD_MAILPIT_PORT=1026
FORWARD_MAILPIT_DASHBOARD_PORT=8025
```

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
- Point web requests to Laravel's `public/index.php` entrypoint or equivalent shared-hosting setup.
