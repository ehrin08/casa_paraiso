# Casa Paraiso Agent Notes

## Project Status

Application code has been scaffolded with Laravel 12, Laravel Breeze, Blade, Tailwind CSS, Vite, Laravel Sail, and MariaDB/MySQL configuration. Project planning documents exist for MVP scope, technology stack, database design, screen flow, Docker workflow, and implementation roadmap.

## Planning Documents

- MVP scope: `docs/MVP_SCOPE.md`
- Tech stack: `docs/TECH_STACK.md`
- Database design: `docs/DATABASE_DESIGN.md`
- Screen flow: `docs/SCREEN_FLOW.md`
- Docker workflow: `docs/DOCKER_WORKFLOW.md`
- Implementation roadmap: `docs/IMPLEMENTATION_ROADMAP.md`
- Brand and UI guide: `docs/BRAND_UI_GUIDE.md`
- Use the MVP scope, tech stack, database design, screen flow, Docker workflow, and implementation roadmap as the first-build source of truth before API design, routes, controllers, views, migrations, models, or implementation.

## Project Objectives

### General Objective

Design and develop a centralized Spa Appointment and Management System for Casa Paraiso - Body and Wellness Spa that streamlines scheduling, integrates an RFM-driven (Recency, Frequency, Monetary) rule-based promotion engine, and uses sentiment analytics to improve data-driven decision-making and customer experience.

### Specific Objectives

- Develop a centralized system that provides accurate, real-time management of bookings, records, and transactions by project completion.
- Implement personalized promotions based on customer behavior to improve customer engagement by project completion.
- Provide analytics and customer feedback insights that support timely management decisions by project completion.
- Ensure the system meets ISO/IEC 25010 standards for usability, security, and effectiveness before deployment.

## Working Directory

- Root: `C:\xampp\htdocs\casa_paraiso`
- Primary local server context: Docker with Laravel Sail services managed through direct `docker compose` commands
- Fallback local server context: XAMPP / Apache
- Git repository is initialized.

## Deployment Target

- Planned production host: Hostinger.
- Default hosting assumption: Hostinger shared/web hosting unless the project later moves to VPS.
- Primary local development environment: Docker with Laravel Sail services managed through direct `docker compose` commands.
- Fallback local development environment: XAMPP / Apache.
- Prefer PHP-compatible backend decisions while the project remains Hostinger shared-hosting compatible.
- Use MariaDB/MySQL-compatible database design for Hostinger Web/Cloud hosting compatibility.
- Avoid requiring Docker, persistent Node.js services, custom daemons, server-level packages, or long-running background workers in production unless the deployment target changes to VPS.

## Database Deployment Notes

- Database safety approval gate: do not reset, reseed, migrate, import, truncate, delete, update, insert, or otherwise modify database schema or data unless the user explicitly approves the specific operation first. Read-only database inspection is allowed.
- Hostinger databases should be managed through hPanel and phpMyAdmin unless a later hosting plan provides a better workflow.
- Keep production database credentials outside committed source files.
- Separate local database configuration from production database configuration.
- Document database creation, import/export, migrations, and seed steps once the schema exists.

## Low-Maintenance Operations Strategy

- Approved direction: design the system for a business without 24/7 IT support.
- Use Hostinger shared/web hosting by default instead of VPS to avoid server administration responsibilities.
- Keep RFM promotions and sentiment analytics simple, rule-based, and application-driven.
- Avoid analytics designs that require separate AI services, background workers, custom schedulers, or manual monitoring to remain useful.
- Design for recovery instead of constant monitoring by relying on backups, database exports, and documented restore steps.
- Add admin-facing export or report download features where they reduce dependency on technical staff.

## Future Decisions

- Detailed security hardening checklist is still undecided.
- Full handover and operations manual is still undecided.

## Development Guidelines

- Prefer small, focused changes that are easy to inspect.
- Keep project-specific setup notes in this file as the application takes shape.
- Use Laravel 12, PHP 8.2+, Blade, Tailwind CSS through Vite, and MariaDB/MySQL unless a later planning decision changes the stack.
- Use Laravel Sail as the primary local development runtime, but prefer direct `docker compose` commands on this Windows machine because `vendor\bin\sail.bat` depends on a working Bash/WSL shim.
- Keep the container's PHP dependencies in the `sail-vendor` Docker volume; after first creation or a Composer lock change, run `docker compose exec -T laravel.test composer install` in addition to the host install.
- Keep Hostinger shared/web hosting compatibility in mind; Docker is not the production target.
- Keep Apache/XAMPP compatibility as a fallback local workflow only.
- Use Node/npm for frontend asset builds only; do not require a production Node.js runtime for the MVP.
- Use Turbo Drive only for safe same-origin GET links and filter forms; keep state-changing forms, exports, and panel links on their existing Laravel request paths.
- Appointment workspaces are calendar-only: customer month view, admin Bookings/Availability week view, and staff personal week view. Keep mutations on normal Laravel form routes and use the role-scoped JSON feeds only for calendar reads.
- Treat 1:00 PM to 12:00 midnight in Asia/Manila as the hard booking window with 30-minute start intervals; `ends_next_day` represents midnight-ending staff windows.
- Pending requests show demand but do not reserve capacity. Final confirmation must use the transactional appointment workflow, and therapist availability changes must remain guarded against future confirmed conflicts.
- Use Laravel migrations and seeders as the primary database workflow.
- Document setup, build, migration, seed, and deployment commands as the application is scaffolded.
- Keep usability, security, and effectiveness visible in design and implementation decisions to support the ISO/IEC 25010 quality target.
- XAMPP CLI PHP has `zip` enabled for Composer package extraction when using host PHP fallback.
- Global Composer may still report version 2.9.7 because self-update is blocked by Windows permissions on `C:\ProgramData\ComposerSetup\bin\composer.phar`; Phase 1 scaffolding used a temporary Composer 2.10.2 PHAR instead.

## Verification

Current scaffold verification:

- Laravel app: `docker compose up -d`, then open `http://localhost:8001`
- PHP dependencies after creating the volume: `docker compose exec -T laravel.test composer install`, then `docker compose restart laravel.test`
- Frontend assets: `docker compose exec -T laravel.test npm run build`
- Database (run only after explicit user approval because this resets and reseeds data): `docker compose exec -T laravel.test php artisan migrate:fresh --seed`
- Tests: `docker compose exec -T laravel.test php artisan test`

## Notes For Future Agents

- Check this file before making changes.
- Read the existing code structure before introducing new conventions.
- Preserve user-created files and avoid broad refactors unless requested.
- Follow `docs/BRAND_UI_GUIDE.md` when adding or changing public, authentication, admin, staff, or customer interface elements.
