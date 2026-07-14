# Casa Paraiso Project Memory

> Last reviewed: 2026-07-14
> Review basis: current repository working tree
> Role: fast orientation for agents and developers; not an independent source of truth

## Purpose

This document is the shortest reliable path into the Casa Paraiso codebase. It summarizes the current application shape, durable decisions, important invariants, and the files most likely to matter for a task.

It does not eliminate source inspection. Use it to avoid a broad repository scan, then verify the behavior you will change in the relevant implementation, tests, and authoritative planning document.

## Required Reading Sequence

1. Read [`AGENTS.md`](../AGENTS.md) for mandatory safety, environment, and development rules.
2. Read this memory for project orientation and task routing.
3. Read the authoritative planning documents and current source files relevant to the task.
4. Read representative tests before changing established behavior.

Do not treat this memory as permission for a database operation. The database safety gate in `AGENTS.md` always applies.

## Authority Map

| Question | Authority | Use |
| --- | --- | --- |
| What operations and constraints are mandatory? | [`AGENTS.md`](../AGENTS.md) | Safety gates, environment rules, deployment boundaries, and development conventions |
| What should the MVP do? | [`MVP_SCOPE.md`](MVP_SCOPE.md) | Product scope, users, features, exclusions, and acceptance |
| Which stack and hosting assumptions are approved? | [`TECH_STACK.md`](TECH_STACK.md) | Runtime, frontend, authentication, deployment, and verification |
| What data shape and state vocabulary are intended? | [`DATABASE_DESIGN.md`](DATABASE_DESIGN.md) | Tables, relationships, statuses, indexes, and data rules |
| How should each role move through the product? | [`SCREEN_FLOW.md`](SCREEN_FLOW.md) | Screens, workflows, navigation, and access expectations |
| How is local Docker work performed? | [`DOCKER_WORKFLOW.md`](DOCKER_WORKFLOW.md) | Sail services, dependency installation, and local commands |
| What is the approved build order and remaining delivery work? | [`IMPLEMENTATION_ROADMAP.md`](IMPLEMENTATION_ROADMAP.md) | Phases, dependencies, verification, and completion definition |
| How should the interface look and behave? | [`BRAND_UI_GUIDE.md`](BRAND_UI_GUIDE.md) | Brand, density, components, responsive behavior, and accessibility |
| What security checks gate a release? | [`SECURITY_HARDENING.md`](SECURITY_HARDENING.md) | Implemented controls plus production environment, hosting, backup, and recovery checks |
| What does the application currently do? | Current routes, controllers, services, models, migrations, configuration, and tests | Implemented behavior |
| Where should a new task begin? | This document | Fast navigation only |

When sources disagree, identify whether the question concerns intended or current behavior, inspect the corresponding authority, and record a durable discrepancy under Known Gaps. Do not silently guess.

## Current Project State

- The Laravel 12 monolith contains the core MVP workspaces for authentication and roles, services, therapist and customer records, scheduling, calendar-based appointments, receptionist operations, transactions, therapist commissions, feedback and sentiment, RFM promotion suggestions, reports, and CSV export.
- Authenticated-workspace refinement is active in the current working tree, standardizing compact density, responsive filters, accessible overflow regions, shared page headings and stat strips, and fixed pagination.
- Customer appointments use a month calendar with embedded booking, admin and receptionist appointments use weekly Bookings/Availability workspaces, and therapists use a personal weekly calendar.
- Application behavior is covered primarily by Laravel feature tests under `tests/Feature`; factories exist for all business models.
- Admin Settings persists editable business identity/contact details and a payment-form default while displaying code-controlled operating and security safeguards.
- The Phase 11 application security baseline and checklist are implemented. Target-host validation, Hostinger delivery preparation, and the non-technical handover/operations manual remain incomplete.
- CRUD audit and repair information is tracked separately in [`CRUD_REMEDIATION_CHECKLIST.md`](CRUD_REMEDIATION_CHECKLIST.md) and [`CRUD_DATA_REPAIR_PLAN.md`](CRUD_DATA_REPAIR_PLAN.md). Never copy its record-level findings here or infer approval to execute a repair.

## Stack and Operating Boundaries

| Area | Current decision |
| --- | --- |
| Backend | Laravel 12 on PHP 8.2+ as a server-rendered monolith |
| Authentication | Laravel Breeze email/password and verification, plus Laravel Socialite Google OAuth |
| Views | Blade templates with reusable Blade components |
| Frontend | Tailwind CSS 4, Vite 7, Alpine.js for local state, and Turbo Drive 8 for safe navigation |
| Data | MariaDB/MySQL with Laravel migrations, Eloquent models, factories, and seeders |
| Primary local runtime | Laravel Sail services through direct `docker compose` commands |
| Local fallback | XAMPP/Apache with compatible PHP and MariaDB/MySQL |
| Production target | Hostinger shared/web hosting unless a later decision moves the project to VPS |
| Timezone | `Asia/Manila` |
| Business window | Every day, 1:00 PM through 12:00 midnight, with 30-minute starts |

Production must not require Docker, a persistent Node.js process, a custom daemon, an external AI service, or a continuously running queue worker. Node is used for asset compilation. RFM and sentiment logic remain application-driven.

`config/casa.php` is the fallback source for business identity and the code-controlled source for business hours, booking interval, commission rate, fixed pagination size, package content, security feature switches, and display-only add-ons. `ApplicationSetting` stores the editable business identity/contact fields and payment-form default. Schema remains migration-driven.

## Roles and Access Boundaries

All authenticated workspaces require `auth`, `active`, and `verified` middleware. Role middleware aliases are registered in `bootstrap/app.php`.

| Role | Primary access |
| --- | --- |
| Guest | Public landing page, customer registration, email/password login, Google sign-in, and password recovery |
| Customer | Own profile, availability, booking, appointment calendar/history, pre-start cancellation, and eligible feedback |
| Receptionist | Restricted front-desk dashboard plus appointment management, customer contact/history, and payment recording; availability is read-only |
| Staff/Therapist | Personal dashboard/calendar plus assigned appointments, operational customer records, related transactions/feedback, and own commission history |
| Admin | Dashboard and management of appointments, customers, staff, schedules, services, transactions, commissions, RFM settings/suggestions, feedback, reports, and limited application settings |
| Super administrator | All admin access plus protected user provisioning, role assignment, activation, and deactivation |

Important identity rules:

- Public registration always creates an active customer and customer profile.
- A new verified Google identity defaults to customer unless its email matches the configured protected super-administrator identity.
- Pre-authorized users retain their assigned role when linking Google.
- Inactive users are logged out and cannot use authenticated workspaces.
- Only the protected super administrator can access `admin.users.*`; the protected account cannot be edited through user management.
- Receptionist users have neither staff nor customer profiles and land at `reception.dashboard`.
- Google-only users must reconfirm the linked Google identity before creating a local password. Passwordless non-Google accounts use password reset.

Key identity entry points are `routes/auth.php`, the shared profile routes in `routes/web.php`, `app/Http/Controllers/Auth`, `app/Models/User.php`, `app/Http/Middleware`, and `app/Services/PasswordSetupConfirmation.php`.

## Architecture and Domain Map

### HTTP and Application Entry Points

- `routes/web.php` owns public, shared profile, admin, staff, and customer workspaces. Read its middleware groups before changing access.
- `routes/auth.php` owns registration, login, Google OAuth, verification, password reset, confirmation, and logout.
- `app/Http/Controllers/{Admin,Reception,Staff,Customer}` separates role-specific request handling.
- `app/Http/Requests` contains workflow validation; business invariants that require transactions, locks, or cross-record checks live in services.
- `app/Models` contains state vocabulary, casts, and relationships.
- `app/Services` contains reusable scheduling, completion, sentiment, RFM, numbering, identity-confirmation, session, and conflict logic.

### Core Records and Relationships

- `User` has at most one `StaffProfile` or `CustomerProfile`; role changes provision, restore, or soft-delete the corresponding profile.
- `Service` is assigned to therapists through `StaffService` and connects to appointments, transactions, and feedback.
- `StaffProfile` owns service eligibility, recurring `StaffWeeklySchedule` rows, date-specific `StaffScheduleException` rows, and assigned/preferred appointments.
- `Appointment` connects customer, service, assigned therapist, optional preferred therapist, status logs, transactions, and at most one feedback record.
- `Transaction` connects a customer to a service and optionally an appointment; it records amount, payment state, payment metadata, and recorder.
- `TherapistCommission` stores one primary earning per eligible transaction plus signed reconciliation adjustments and external payout metadata.
- `ApplicationSetting` stores the singleton-style editable business profile and default payment method; it falls back safely to configuration before its migration is applied.
- `Feedback` belongs to one completed appointment, customer, and service.
- `RfmSegment` and `PromotionRule` classify customer transaction behavior; `PromotionSuggestion` stores the generated snapshot and review outcome.

### Domain Services

| Domain | Main services | Responsibility |
| --- | --- | --- |
| Effective schedules | `ScheduleWindowResolver` | Resolve recurring windows, date exceptions, business-hour clipping, merging, and unavailable subtraction |
| Eligibility protection | `StaffScheduleConflictGuard` | Block staff, service, or availability changes that invalidate future confirmed visits |
| Appointment lifecycle | `AppointmentWorkflow` | Validate starts, choose/lock eligible staff, prevent overlap, schedule, transition status, and log changes |
| Customer availability | `AppointmentAvailability` | Build bookable month/day slots from effective staff capacity |
| Calendar reads | `AppointmentCalendar` | Produce role-scoped admin, staff, and customer calendar payloads |
| Service completion | `AppointmentCompletion` | Atomically create one transaction and complete an eligible confirmed visit |
| Appointment administration | `AppointmentManagement` | Share confirmed create/update invariants across Admin and Receptionist controllers |
| Transaction writes | `TransactionWorkflow` | Normalize Admin/Receptionist payment writes and invoke commission synchronization atomically |
| Therapist commissions | `TherapistCommissionSynchronizer` | Create or recalculate pending earnings and immutable-payout adjustments |
| Identifiers | `AppointmentNumberGenerator`, `TransactionNumber` | Allocate collision-resistant business identifiers |
| Sentiment | `SentimentClassifier` | Combine rating defaults with simple positive/negative keyword and negation rules |
| Promotions | `RfmPromotionGenerator` | Compute RFM metrics, match active rules, and store de-duplicated suggestion snapshots |
| Identity safety | `PasswordSetupConfirmation`, `UserSessionRevoker` | Handle short-lived Google confirmation and session invalidation |

## Critical Workflows and Invariants

### Scheduling and Booking

- `config/casa.php` defines a hard 1:00 PM-to-midnight business window in `Asia/Manila`; starts must align to 30-minute intervals.
- `ends_next_day` represents a schedule or exception ending exactly at midnight. Never model midnight as an earlier same-day end.
- Effective availability is recurring availability plus date-specific availability, minus unavailable exceptions, clipped to business hours and confirmed overlaps.
- Unavailable exceptions take precedence. A schedule, service assignment, staff role, active state, or bookable-state change must not orphan a future confirmed appointment.
- Every new appointment is confirmed transactionally and reserves therapist capacity.
- Customer booking uses `AppointmentWorkflow::autoBook` to confirm transactionally, honor an eligible preference when possible, and assign an eligible therapist without overlap. Admin-created bookings also require an eligible therapist and schedule.
- Confirmed appointments reserve capacity. Final scheduling must go through `AppointmentWorkflow`, not direct model updates.
- Appointment states are `confirmed`, `completed`, `cancelled`, and `no_show`. Terminal states do not reopen. The pending-state retirement migration converts legacy pending rows to cancelled records with a system status log.
- Role calendars are read-only JSON projections; mutations remain ordinary Laravel form routes.
- Receptionist calendars expose all bookings and therapist coverage but never availability-edit links.

### Completion and Transactions

- Only a confirmed appointment whose start time has arrived can use the finish workflow.
- `AppointmentCompletion` locks the appointment, rejects duplicate transactions, creates the transaction, and marks the appointment completed in one database transaction.
- Payment states are `unpaid`, `partial`, `paid`, `refunded`, and `void`. Payment method and paid timestamp are cleared when the state does not represent received funds.
- Admin manages transactions. Staff transaction views are operational and read-only.

### Therapist Commissions

- The system rate is `casa.commissions.therapist_rate = 0.22`; new earnings snapshot that rate.
- Only fully paid transactions linked to completed appointments with an assigned therapist earn commission.
- Pending earnings recalculate with source changes. Paid rows are immutable, and later corrections create signed pending adjustments.
- Admin records external settlement; no money is transferred. Therapists see only their own commission records, and Receptionists have no commission access.

### Settings and Security

- `admin.settings.index` and `admin.settings.update` are available only to Admin and Super Administrator. User provisioning and role/activation changes remain exclusive to the protected Super Administrator.
- Editable settings are business name, contact email, phone, address, and the default payment method used to prefill new Admin and Receptionist forms. The default never settles a transaction by itself.
- `AddSecurityHeaders` supplies the browser header baseline. `AppServiceProvider` registers named guest/user sensitive rate limiters and can force HTTPS in production.
- `casa.security` reads `FORCE_HTTPS`, `HSTS_ENABLED`, and `TRUSTED_HOSTS`. Production release checks live in `SECURITY_HARDENING.md`; HSTS must wait until HTTPS is verified.

### Feedback and Sentiment

- A customer may submit one feedback record for an eligible completed appointment.
- Ratings 4–5 default positive, 3 neutral, and 1–2 negative. Keyword polarity and nearby negation may refine the label; no external AI service is used.
- Sentiment labels are `positive`, `neutral`, and `negative`.
- Admin sees full feedback insights; staff access is limited to related operational feedback.

### RFM Promotions

- RFM uses only `paid` transactions attached to `completed` appointments.
- Recency is based on the latest paid timestamp, frequency is the qualifying transaction count, and monetary value is the qualifying amount total.
- Active segments and rules are evaluated in application code.
- A generation key prevents duplicate snapshots for the same customer, rule, and metric values.
- Suggestion states are `suggested`, `reviewed`, `applied`, and `dismissed`. Suggestions are admin-reviewed guidance, never automatic discounts.

### Reports and Exports

- `Admin\ReportController` builds filtered appointment, transaction, customer, promotion, and feedback reports from current records.
- CSV export stays on its normal GET route and must not require a background worker or direct database access by business users.

## Shared UI Contract

- Keep the application server-rendered. Alpine.js may manage local disclosure, modal, and calendar state; URLs, filters, sorting, and pagination remain Laravel requests.
- Turbo Drive applies only to safe same-origin GET links and filter forms. State-changing forms, exports, and specialized panel links use their normal request behavior.
- Use `page-heading` for authenticated page titles and `stat-strip` for compact detail/calendar context. Reserve `metric-card` for dashboards and analytics.
- Use `list-toolbar` for result totals and responsive filter disclosure, and `table-shell` for labeled, keyboard-focusable horizontal overflow.
- `AppServiceProvider` registers `pagination.compact`. The fixed page size is `casa.pagination.per_page = 15`; preserve query state with `withQueryString()` and never accept request-provided page size.
- Multiple lists need distinct page keys and useful fragments. Calendars, active queues, selector collections, and bounded previews remain unpaginated.
- Appointment workspaces remain calendar-only: customer month, admin/receptionist weekly Bookings/Availability, and therapist personal week.
- Customer booking opens inside My Appointments; `/customer/appointments/create` remains the full-page fallback.
- Preserve 44px interaction targets, visible focus, accessible names, labeled overflow regions, keyboard calendar navigation, and reduced-motion support.

Primary UI sources are `docs/BRAND_UI_GUIDE.md`, `docs/TECH_STACK.md`, `resources/views/components`, `resources/views/layouts`, `resources/css/app.css`, `resources/js/app.js`, `config/casa.php`, and `AppServiceProvider`.

## Task Routing Table

| Task area | Read first | Inspect next | Representative tests |
| --- | --- | --- | --- |
| Login, registration, Google, passwords, profile deletion | `MVP_SCOPE.md`, `GOOGLE_AUTH_SETUP.md` | `routes/auth.php`, Auth controllers, `User`, profile controller, identity services | `Auth/AuthenticationTest`, `Auth/RegistrationTest`, `Auth/PasswordSetupTest`, `ProfileTest` |
| Roles, activation, or account provisioning | `SCREEN_FLOW.md`, `AGENTS.md` | `routes/web.php`, middleware, `Admin/UserManagementController`, profile models, conflict guard | `RoleAccessTest`, `SuperAdminUserManagementTest`, `IdentityAndEligibilityRemediationTest` |
| Services, staff, customers, or assignments | `DATABASE_DESIGN.md`, `SCREEN_FLOW.md` | Admin/staff controllers and requests, related models, factories, and migrations | `AdminServiceManagementTest`, `AdminStaffManagementTest`, `DatabaseFoundationTest` |
| Weekly schedules, exceptions, or availability | `DATABASE_DESIGN.md`, `SCREEN_FLOW.md` | `ScheduleWindowResolver`, `StaffScheduleConflictGuard`, schedule models/controllers | `AdminStaffScheduleManagementTest`, `CalendarSchedulingTest`, `AutomatedAppointmentQueueTest` |
| Appointment booking or lifecycle | `MVP_SCOPE.md`, `SCREEN_FLOW.md` | `Appointment`, `AppointmentWorkflow`, availability/calendar/completion services, role controllers | `AppointmentCrudRemediationTest`, `CalendarSchedulingTest`, `AutomatedAppointmentQueueTest` |
| Transactions or payment states | `DATABASE_DESIGN.md`, `SCREEN_FLOW.md` | `Transaction`, `AppointmentCompletion`, `TransactionNumber`, transaction controllers/requests | `TransactionRemediationTest`, `PhaseFiveToTenWorkflowTest` |
| Receptionist or therapist commissions | `MVP_SCOPE.md`, `DATABASE_DESIGN.md`, `SCREEN_FLOW.md` | Reception controllers/routes, `TherapistCommission`, `TransactionWorkflow`, commission synchronizer | `ReceptionistWorkspaceTest`, `TherapistCommissionTest` |
| Admin Settings or security hardening | `SCREEN_FLOW.md`, `SECURITY_HARDENING.md`, `TECH_STACK.md` | `ApplicationSetting`, Admin setting controller/request/view, security middleware, providers, auth routes, environment example | `AdminSettingsTest`, `SecurityHardeningTest`, `AuthenticatedWorkspaceSmokeTest` |
| Feedback, sentiment, RFM, promotions, or reports | `MVP_SCOPE.md`, roadmap phases 8–10 | Related models/services, admin/customer/staff controllers, report export | `InsightRemediationTest`, `PhaseFiveToTenWorkflowTest` |
| Schema, factories, seeders, or data integrity | `DATABASE_DESIGN.md`, `AGENTS.md` | `database/migrations`, factories, `DatabaseSeeder`, audit command; obtain approval before mutations | `DatabaseFoundationTest`, `SeederSafetyTest`, `CrudIntegrityCommandTest` |
| Authenticated UI, lists, calendars, or accessibility | `BRAND_UI_GUIDE.md`, `TECH_STACK.md` | Shared components/layouts, CSS/JS, pagination view, provider, relevant role view | `CompactWorkspacePaginationTest`, `InteractiveListControlsTest`, `ModalInfrastructureTest`, `RoleWorkspaceTest` |
| Public content, packages, or business hours | `BRAND_UI_GUIDE.md`, `MVP_SCOPE.md` | `config/casa.php`, landing view, service seeding and service views | `ExampleTest`, `AdminServiceManagementTest`, `DatabaseFoundationTest` |
| Docker, Hostinger, or handover | `TECH_STACK.md`, `DOCKER_WORKFLOW.md`, roadmap phase 11 | `compose.yaml`, Composer/npm manifests, `.env.example`, public entry point | Build/test commands and clean-checkout review |

## Verification and Database Safety

Read-only orientation:

```powershell
php artisan route:list --except-vendor
git status --short
```

Standard application checks:

```powershell
docker compose exec -T laravel.test npm run build
docker compose exec -T --user sail laravel.test php artisan test
```

Run dependency installation only when dependencies changed or the environment is new; follow `AGENTS.md` and `DOCKER_WORKFLOW.md`.

Run in-container Artisan commands with `docker compose exec -T --user sail laravel.test ...` so CLI-created logs and cache artifacts remain writable by the `sail` web process. The single and daily log channels create files with mode `0664`.

Do not reset, reseed, migrate, import, truncate, delete, update, insert, repair, or otherwise modify any database schema or data without explicit approval for that exact operation. Confirm the intended environment before any approved database command. Tests must remain isolated from non-test databases.

## Known Gaps

- Hostinger production deployment, phpMyAdmin handover instructions, and the full non-technical operations manual are not complete.
- The automated four-workspace smoke suite passes, but representative live browser checks still need to be repeated when the in-app browser runtime is available.
- Production-only security checklist items require validation on the final Hostinger domain and cannot be completed from the local environment.
- Record-level CRUD repair status belongs only in its dedicated repair documents and must be rechecked read-only before any separately approved action.

## Maintenance Contract

Update this document in the same change when any of these change:

- roles, access boundaries, route families, or authentication flows;
- modules, major controllers, models, relationships, migrations, or commands;
- domain services, critical workflows, business invariants, or status values;
- shared UI conventions, configuration sources, deployment constraints, or verification commands;
- completed milestones or durable known gaps; or
- the authoritative planning-document set.

Do not update this document for:

- cosmetic copy or isolated styling changes;
- temporary debugging information, active-task notes, or generated artifacts;
- record-level database findings or credentials; or
- internal refactors that do not change behavior or help future task routing.

For every memory update:

1. Verify the affected statements against current source and tests.
2. Update only the sections affected by the change.
3. Set `Last reviewed` to the actual review date.
4. Check all referenced paths and remove obsolete entry points.
5. Keep the document near 200–300 lines; if it exceeds 350 lines, move detail into the appropriate authoritative document and retain a concise pointer.
6. If authority and implementation differ, record the durable discrepancy rather than presenting either as reconciled.
7. Never use a commit hash as the freshness marker and never store secrets.
