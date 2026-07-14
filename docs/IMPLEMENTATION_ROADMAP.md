# Casa Paraiso Implementation Roadmap

## Purpose

Define and maintain the build sequence for the Casa Paraiso Spa Appointment and Management System after the MVP scope, tech stack, database design, and screen flow were approved.

This roadmap began as the final pre-build plan and now serves as a living implementation and hardening reference. Completed behavior should remain aligned with the source documents below, while unfinished handover and deployment work stays tracked in the later phases.

## Guiding Sources

Use these documents as implementation sources of truth:

- `docs/MVP_SCOPE.md`
- `docs/TECH_STACK.md`
- `docs/DATABASE_DESIGN.md`
- `docs/SCREEN_FLOW.md`
- `docs/DOCKER_WORKFLOW.md`
- `docs/BRAND_UI_GUIDE.md`

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

Goal: implement the navigation and authenticated-workspace structure from `docs/SCREEN_FLOW.md` and `docs/BRAND_UI_GUIDE.md`.

Tasks:

- Create shared Blade layout foundations.
- Create a compact shared authenticated shell with a 256px desktop sidebar.
- Use a mobile drawer for admin and staff and keep customer mobile navigation appointment-first with the three-item bottom dock.
- Create dashboard pages:
  - `/admin/dashboard`
  - `/staff/dashboard`
  - `/customer/appointments`
- Add reusable Tailwind/Blade components for page headings, compact stat strips, cards, buttons, forms, tables, badges, alerts, and empty states.
- Use responsive list toolbars that show totals, disclose filters below 1024px, report active-filter counts, and offer Clear filters only when applicable.
- Register the compact paginator globally and use `casa.pagination.per_page` as the fixed 15-record page size for authenticated record lists.
- Preserve filter and sort state through pagination, use independent page keys for multiple lists, and keep calendars and bounded operational previews unpaginated.
- Keep tables and compact calendar date strips in labeled keyboard-focusable overflow regions with 44px interaction targets.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Each role sees only its own dashboard and navigation.
- Admin and staff sidebar layouts support management workflows.
- Customer desktop navigation and mobile dock prioritize appointments, feedback, and profile actions.
- Authenticated lists have consistent responsive filters, result ranges, empty states, and desktop/mobile pagination controls.
- Shared headings, stat strips, cards, tables, and calendar selectors remain readable and operable from narrow mobile widths through desktop.

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
- Paginate the Team & Services staff list and embedded service catalog independently so navigating one list does not reset the other.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Admin can manage services, staff, staff schedules, and customer records.
- Team & Services preserves both paginator states and returns service-page navigation to the embedded catalog.
- Staff can view only operational customer details.
- Customer can update their own profile.

## Phase 6: Appointment Workflow

Goal: implement automated confirmed booking, admin service queue, and atomic completion/payment workflow.

Tasks:

- Build customer confirmed-booking form and expose it from My Appointments with a full-page fallback.
- Build a customer month calendar for requests, confirmed visits, and history plus the appointment detail view.
- Build an admin weekly resource calendar with Bookings and Availability modes plus detail/create screens.
- Build a staff personal weekly calendar with assigned appointments, read-only availability, and eligible demand.
- Keep the operational week selector horizontally scrollable with tab semantics and Left/Right/Home/End keyboard movement; use a selected-day agenda on mobile and the resource timeline on desktop.
- Keep the customer month grid unpaginated and horizontally scrollable inside a labeled keyboard-focusable region on narrow screens.
- Implement status transitions:
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

- Customer can book an appointment with immediate confirmation.
- Customer bookings confirm automatically; admin can reschedule, cancel, complete, or mark no-show.
- Admin can create confirmed reservations directly from effective availability cells in the operational calendar.
- The system blocks overlapping confirmed appointments for the same staff member.
- Customer, staff, and admin calendars expose only role-authorized events.
- Customer can view their own appointment status from the monthly calendar.
- Week and month date selectors retain full-size keyboard-accessible targets without forcing the application viewport to overflow horizontally.

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
- Apply the shared responsive filter disclosure and compact pagination contract to on-screen report records while leaving CSV export requests on their existing paths.

Verification:

```bash
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan test
```

Acceptance:

- Admin can filter reports and export CSV files.
- Dashboard summaries reflect current database records.
- Reports do not require direct database or phpMyAdmin access.

## Phase 10.5: Reception, Embedded Booking, And Therapist Commissions

Goal: support front-desk operations and traceable therapist compensation without changing the confirmed-booking model.

Completed tasks:

- Classify staff profiles as therapists while retaining the internal `staff` role.
- Add the restricted Receptionist role and `/reception` workspace.
- Embed customer booking in My Appointments while retaining the full-page fallback.
- Generate 22% therapist earnings for fully paid completed services and signed post-payout adjustments.
- Add Admin payout recording and read-only Therapist commission history.

Acceptance:

- Receptionists can perform front-desk workflows without schedule-management, analytics, administration, or commission access.
- Commission payout records are traceable, immutable after settlement, and never transfer money.

## Phase 11: Hardening, Deployment, And Handover

Goal: prepare the MVP for capstone review and Hostinger-style deployment.

Completed hardening tasks:

- Added the application and production security hardening checklist in `SECURITY_HARDENING.md`.
- Added validation coverage for the implemented Admin Settings create/update surface.
- Confirmed representative cross-role access restrictions through automated workspace smoke tests.
- Added security headers, sensitive-route rate limits, production HTTPS/HSTS and trusted-host controls, and ignore rules for environment, backup, and test artifacts.

Remaining delivery tasks:

- Confirm production credentials stay outside committed files on the target host.
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

- Admin, receptionist, therapist, and customer authentication works.
- Admin can manage services, staff, schedules, customers, transactions, promotions, feedback, and reports.
- Receptionists can handle front-desk appointment, customer, and payment workflows; therapists retain assigned operational access.
- Admin can record therapist commission payouts, and therapists can review only their own history.
- Customers can book confirmed appointments, view status/history, cancel before the start, update profile, and submit feedback.
- RFM promotion suggestions are stored and reviewable.
- Feedback sentiment is classified without external AI services.
- CSV reports are available for admin.
- The app builds successfully with Tailwind/Vite.
- Laravel tests pass.
- Hostinger deployment and handover notes exist.
