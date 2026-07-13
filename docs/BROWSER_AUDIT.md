# Browser Regression Audit

The browser suite covers the public, admin, staff, and customer experiences at 320, 390, 768, 1024, and 1440 pixel viewports. It checks role routing, the canonical customer mobile dock, conditional filter clearing, duplicate IDs and label targets, locally hosted fonts, and serious or critical axe findings.

The redundancy and design regression checks also cover:

- Admin and staff mobile drawers opening from the keyboard, closing with Escape, and restoring focus to the opener. These assertions run below the 1024px desktop-sidebar breakpoint.
- A visible two-tone keyboard focus treatment.
- Complete booking and availability legends, active appointment status options, and the absence of retired pending/request terminology.
- Representative interactive targets measuring at least 44 by 44 CSS pixels and meaningful visible text measuring at least 14 CSS pixels.
- Reduced-motion overrides for smooth scrolling, transitions, and animations.
- Customer reflow at 200% text zoom, role-representative document overflow, and clipping of the main page regions.

Every test is collected under the five configured viewport projects. Layout-specific assertions skip only when that interface is intentionally replaced, such as the mobile drawer becoming the persistent desktop sidebar.

## Database safety

Never point the browser suite at the normal local or production database. Obtain explicit approval before creating, migrating, resetting, or seeding the dedicated browser-test database.

After approval, create an uncommitted `.env.e2e` from `.env.example`, set `APP_ENV=e2e`, and configure a dedicated database name and credentials. Then prepare and serve only that environment:

```powershell
docker compose exec -T laravel.test php artisan migrate:fresh --seed --env=e2e
docker compose run --rm -p 8010:80 -e APP_ENV=e2e laravel.test
```

The second command starts a separate application container at `http://localhost:8010` using `.env.e2e`. Do not reuse the normal application port if it points to the development database.

## Browser setup

Install the test browser once:

```powershell
npx playwright install chromium
```

Provide the isolated environment URL and seeded test credentials in the current PowerShell session. Do not commit credentials:

```powershell
$env:PLAYWRIGHT_BASE_URL = 'http://localhost:8010'
$env:E2E_ADMIN_EMAIL = '<isolated admin email>'
$env:E2E_ADMIN_PASSWORD = '<isolated admin password>'
$env:E2E_CUSTOMER_EMAIL = '<isolated customer email>'
$env:E2E_CUSTOMER_PASSWORD = '<isolated customer password>'
$env:E2E_STAFF_EMAIL = '<isolated staff email>'
$env:E2E_STAFF_PASSWORD = '<isolated staff password>'
npm run test:e2e
```

The audit spec performs login and read-only navigation only. Its fixtures and credentials must belong to the isolated browser-test database; never substitute local-development or production accounts.

To verify test discovery and project coverage without launching a browser or contacting the application, run:

```powershell
npx playwright test --list
```

Failure artifacts are written below `storage/testing/`, which is excluded from source control.
