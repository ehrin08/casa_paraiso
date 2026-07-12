# Casa Paraiso Implementation Roadmap

## Purpose

Define the build sequence for the Casa Paraiso Spa Appointment and Management System after the MVP scope, tech stack, database design, and screen flow have been approved.

This roadmap is the final pre-build planning document. After it is accepted, the next step is application scaffolding and implementation.

## Guiding Sources

Use these documents as implementation sources of truth:

- `docs/MVP_SCOPE.md`
- `docs/TECH_STACK.md`
- `docs/DATABASE_DESIGN.md`
- `docs/SCREEN_FLOW.md`
- `docs/DOCKER_WORKFLOW.md`

## Current Environment Direction

Docker Compose with Laravel Sail services is the primary local development workflow. XAMPP remains a fallback local workflow. Hostinger shared/web hosting remains the production target, so Docker must not become a production requirement unless the deployment target changes to VPS.

## Phase 0: Pre-Scaffold Preparation

Goal: confirm the local environment is ready before feature development.

Tasks:

- Confirm Docker and Docker Compose are available.
- Use Sail-generated Docker Compose services as the primary local runtime.
- Confirm Docker Compose services start.
- Confirm the MariaDB service is available.
- Keep XAMPP available only as fallback.

Verification:

```bash
php -v
composer --version
composer diagnose
node --version
npm --version
docker --version
docker compose version
docker compose up -d
```

Acceptance:

- Docker, Compose, Sail services, and MariaDB are ready for local development.
- Host PHP/Composer/npm remain usable as fallback only.

## Phase 1: Laravel And Auth Scaffold

Goal: keep the Laravel application foundation reproducible under Docker Compose.

Authentication direction: support verified email/password customer registration and login alongside verified Google OAuth. Staff and administrators remain pre-authorized and establish passwords through a reset link.

Tasks:

- Maintain Laravel 12 and Breeze Blade templates.
- Maintain Tailwind CSS through Vite.
- Configure `.env` for the Docker MariaDB service.
- Set `APP_TIMEZONE=Asia/Manila`.
- Confirm `.env` is not committed.
- Build frontend assets through the app container.
- Run the Laravel test suite through the app container.

Verification:

```bash
docker compose exec -T laravel.test composer install
docker compose exec -T laravel.test npm install
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Laravel loads locally through Docker Compose.
- Breeze login and registration pages render.
- Tailwind styles compile successfully.
- Default tests pass.

## Phase 2: Roles, Redirects, And Access Control

Goal: make authentication match the approved admin, staff, and customer roles.

Tasks:

- Add `role`, `phone`, and `is_active` fields to the `users` table.
- Define role constants or enums for `admin`, `staff`, and `customer`.
- Implement post-login redirects:
  - Admin to `/admin/dashboard`.
  - Staff to `/staff/dashboard`.
  - Customer to `/customer/appointments`.
- Restrict public registration to customer accounts only.
- Add middleware or policies for admin, staff, and customer access.
- Seed one admin user and optional demo staff/customer users.

Verification:

```bash
docker compose exec -T laravel.test php artisan migrate:fresh --seed
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Admin, staff, and customer users land on the correct pages after login.
- Customer registration cannot create admin or staff accounts.
- Users cannot access route groups outside their role.

## Phase 3: Database Schema, Models, And Seeders

Goal: implement the schema defined in `docs/DATABASE_DESIGN.md`.

Tasks:

- Create migrations in the documented migration order.
- Create Eloquent models and relationships.
- Add factories for core records where useful.
- Add seeders for:
  - Admin user.
  - Demo staff users.
  - Demo customer users.
  - Default Casa Paraiso package services: GAIA TOUCH, TETHYS FLOW, HESTIA WARMTH, and AURORA BREEZE.
  - Static public content for add-on prices, business hours, and the reservation tagline.
  - Staff-service assignments.
  - Weekly staff schedules.
  - Default RFM segments.
  - Default promotion rules.
- Add model-level constants or enums for all status values.

Verification:

```bash
docker compose exec -T laravel.test php artisan migrate:fresh --seed
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- All tables migrate successfully on a clean database.
- Seeded users, real package services, staff schedules, and RFM rules exist.
- Key relationships load correctly in tests.

## Phase 4: Layouts And Role Dashboards

Goal: implement the navigation structure from `docs/SCREEN_FLOW.md`.

Tasks:

- Create shared Blade layout foundations.
- Create shared responsive sidebar navigation for all authenticated roles.
- Keep customer navigation appointment-first within the shared sidebar.
- Create dashboard pages:
  - `/admin/dashboard`
  - `/staff/dashboard`
  - `/customer/appointments`
- Add reusable Tailwind components for page headers, buttons, forms, tables, badges, alerts, and empty states.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Each role sees only its own dashboard and navigation.
- Admin and staff sidebar layouts support management workflows.
- Customer sidebar layout prioritizes appointment status and request actions.

## Phase 5: Services, Staff, Schedules, And Customers

Goal: build the management foundations needed before appointments.

Tasks:

- Build admin service CRUD.
- Build admin staff account/profile management.
- Build staff-service assignment screens.
- Build weekly staff schedule management.
- Build schedule exception management.
- Present recurring schedules and date exceptions through the admin Availability calendar while retaining normal Laravel form submissions.
- Block schedule changes that would invalidate future confirmed appointments.
- Build admin customer list/detail screens.
- Build staff customer lookup with limited operational access.
- Build customer profile screen.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Admin can manage services, staff, staff schedules, and customer records.
- Staff can view only operational customer details.
- Customer can update their own profile.

## Phase 6: Appointment Workflow

Goal: implement automated confirmed booking, admin service queue, and atomic completion/payment workflow.

Tasks:

- Build customer appointment request form.
- Build a customer month calendar for requests, confirmed visits, and history plus the appointment detail view.
- Build an admin weekly resource calendar with Bookings and Availability modes plus detail/create screens.
- Build a staff personal weekly calendar with assigned appointments, read-only availability, and eligible demand.
- Implement status transitions:
  - `pending`
  - `confirmed`
  - `completed`
  - `cancelled`
  - `no_show`
- Generate unique appointment numbers.
- Calculate scheduled end time from service duration.
- Prevent overlapping confirmed appointments for the same staff member.
- Store optional therapist preference separately from final assignment.
- Resolve every calendar from the same recurring schedule, exception, business-hour, and confirmed-overlap rules.
- Support services ending exactly at the 12:00 midnight boundary.
- Record appointment status logs.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Customer can request an appointment.
- Customer bookings confirm automatically; admin can reschedule, cancel, complete, or mark no-show.
- Admin can create confirmed reservations directly from effective availability cells in the operational calendar.
- The system blocks overlapping confirmed appointments for the same staff member.
- Customer, staff, and admin calendars expose only role-authorized events.
- Customer can view their own appointment status from the monthly calendar.

## Phase 7: Manual Transactions

Goal: build manual transaction recording and payment status management.

Tasks:

- Build admin transaction list/create/detail screens.
- Keep staff transaction history read-only and build admin completion/payment capture.
- Generate unique transaction numbers.
- Support payment statuses:
  - `unpaid`
  - `partial`
  - `paid`
  - `refunded`
  - `void`
- Support default payment methods:
  - Cash
  - GCash
  - Bank transfer
  - Other
- Show transaction history on customer detail pages.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Admin can record manual payments.
- Transactions link to customers and appointments where applicable.
- Paid completed transactions are available for RFM calculations.

## Phase 8: Feedback And Sentiment

Goal: collect customer feedback and produce simple sentiment insights.

Tasks:

- Build customer feedback form for completed appointments.
- Enforce one feedback record per appointment.
- Store rating, comment, sentiment label, and optional sentiment score.
- Implement simple sentiment classification:
  - Ratings 4-5 default to `positive`.
  - Rating 3 defaults to `neutral`.
  - Ratings 1-2 default to `negative`.
  - Optional keyword rules may refine the label.
- Build admin feedback list/detail and sentiment summary.
- Build staff feedback view for related operational feedback.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Customer can submit feedback for completed appointments.
- Sentiment label is stored without external AI services.
- Admin can review feedback and sentiment summaries.

## Phase 9: RFM Promotion Suggestions

Goal: implement rule-based customer segmentation and stored promotion suggestions.

Tasks:

- Build RFM segment and promotion rule seeders.
- Build admin screens for segments, rules, and suggestion review.
- Implement RFM calculation from completed paid transactions:
  - Recency from latest paid completed transaction.
  - Frequency from completed paid transaction count.
  - Monetary from completed paid transaction total.
- Store promotion suggestion snapshots.
- Support suggestion statuses:
  - `suggested`
  - `reviewed`
  - `applied`
  - `dismissed`
- Ensure suggestions are admin-visible and not automatic discounts.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Admin can generate or view stored promotion suggestions.
- Suggestions include RFM values and suggested offer.
- Admin/staff can review, apply, or dismiss suggestions according to permissions.

## Phase 10: Reports, Exports, And Dashboard Summaries

Goal: support management decisions without requiring database access.

Tasks:

- Build admin reports for:
  - Appointments.
  - Transactions/revenue.
  - Customer activity.
  - Promotion suggestions.
  - Feedback sentiment.
- Add filters for date range, status, service, staff, customer, payment status, promotion status, and sentiment where relevant.
- Add CSV export for tabular reports.
- Add dashboard summary cards using existing appointment, transaction, feedback, and promotion data.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Admin can filter reports and export CSV files.
- Dashboard summaries reflect current database records.
- Reports do not require direct database or phpMyAdmin access.

## Phase 11: Hardening, Deployment, And Handover

Goal: prepare the MVP for capstone review and Hostinger-style deployment.

Tasks:

- Add a security hardening checklist.
- Add validation coverage for all create/update forms.
- Confirm role access restrictions.
- Confirm production credentials stay outside committed files.
- Write deployment notes for Hostinger shared/web hosting.
- Write database export/import notes for phpMyAdmin handover.
- Write a handover manual for non-technical business users.
- Run full verification before delivery.

Verification:

```bash
docker compose exec -T laravel.test composer install
docker compose exec -T laravel.test npm install
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan migrate:fresh --seed
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- App can be installed from a clean checkout.
- Core workflows pass manual and automated checks.
- Deployment and handover documentation are clear enough for non-technical maintenance with occasional developer support.

## Build Order Rule

Do not start a later feature phase until the earlier dependency phase is working locally. For example, appointments should not be built before services, staff, schedules, and customers exist.

## MVP Completion Definition

The MVP is complete when:

- Admin, staff, and customer authentication works.
- Admin can manage services, staff, schedules, customers, transactions, promotions, feedback, and reports.
- Staff can handle daily appointment and transaction workflows.
- Customers can book confirmed appointments, view status/history, cancel before the start, update profile, and submit feedback.
- RFM promotion suggestions are stored and reviewable.
- Feedback sentiment is classified without external AI services.
- CSV reports are available for admin.
- The app builds successfully with Tailwind/Vite.
- Laravel tests pass.
- Hostinger deployment and handover notes exist.
