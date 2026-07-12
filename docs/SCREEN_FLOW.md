# Casa Paraiso Screen Flow

## Purpose

Define the MVP pages, role-based navigation, and main user journeys before Laravel routes, controllers, and Blade layouts are scaffolded.

The interface should use separate dashboards for admin, staff, and customer. Authenticated roles use a persistent sidebar on desktop. Admin and staff use a navigation drawer on smaller screens, while customers use a compact header plus bottom navigation for Appointments, Feedback, and Profile. Customers land on appointment-focused pages after login.

## Route Groups

- Public: unauthenticated pages for landing, service browsing, login, and registration.
- Auth: login, registration, password reset, logout, and authenticated redirects.
- Admin: full management access.
- Staff: daily operations access.
- Customer: appointment request, status, history, feedback, and profile access.

Planned route prefixes:

- `/`
- `/login`
- `/register`
- `/admin`
- `/staff`
- `/customer`

## Shared Layout Rules

- Use Laravel Blade server-rendered pages with Tailwind CSS.
- Use a shared authenticated layout shell with role-specific desktop sidebar navigation.
- Admin and staff collapse the sidebar into a mobile drawer.
- Customer mobile navigation uses a persistent three-item bottom bar for Appointments, Feedback, and Profile.
- Admin and staff pages should use the persistent sidebar with module navigation, page title, search/filter area where needed, and clear primary action buttons.
- Customer pages should stay simpler and appointment-first, with sidebar navigation focused on appointments, feedback, and profile.
- All role dashboards should show only actions the current role is allowed to perform.
- Avoid SPA-only navigation patterns; pages should work as standard Laravel routes.

## Public Screens

### Landing Page

Access: guest and authenticated users.

Purpose:

- Introduce Casa Paraiso - Body and Wellness Spa.
- Show the four massage packages, add-on price list, business hours, and call to action.
- Use the marketing line: "Reserve your spot. You deserve this."

Primary actions:

- Login.
- Register.
- Book an available appointment after authentication.

Main data:

- Active services.
- Add-ons as static customer-facing content only.
- Business hours: Open every day, 1:00 PM to 12:00 MN.
- Basic business information.

### Service Listing

Access: guest and authenticated users.

Purpose:

- Let visitors view available spa services before logging in.

Primary actions:

- View service details.
- Register or login to request an appointment.

Main data:

- Active service name, description, duration, and price.
- Initial active service names should be GAIA TOUCH, TETHYS FLOW, HESTIA WARMTH, and AURORA BREEZE.

## Auth Screens

### Login

Access: guest users.

Purpose:

- Authenticate with a verified email and password or Google OAuth. Unknown verified Google accounts and public registrations become customers; privileged emails must be pre-authorized.

Post-login redirect:

- Admin users go to `/admin/dashboard`.
- Staff users go to `/staff/dashboard`.
- Customer users go to `/customer/appointments`.

### Register

Access: guest users.

Purpose:

- Allow customers to register with name, email, optional phone, and password, then require email verification before workspace access. Google remains available as an alternative from the login screen.

Rules:

- Public registration creates customer accounts only.
- Admin and staff accounts should be created by admin users.

Post-registration redirect:

- Customer users go to `/customer/appointments`.

## Admin Screens

Admin navigation uses sidebar modules.

### Admin Dashboard

Route: `/admin/dashboard`

Purpose:

- Give admin a management summary.

Main data:

- Today appointments.
- Confirmed appointments waiting in the service queue.
- Recent transactions.
- Revenue summary.
- Feedback sentiment summary.
- Promotion suggestions needing review.

Primary actions:

- Run the service queue.
- Add appointment.
- Add transaction.
- View reports.

### Appointments

Route group: `/admin/appointments`

Purpose:

- Manage all confirmed customer bookings and historical appointment requests.

Screens:

- Calendar-only weekly schedule with Bookings and Availability modes.
- In-page confirmed appointment modal opened from Add appointment or an available therapist/time cell.
- Appointment detail.
- Create appointment.
- Service queue plus appointment detail, reschedule, and cancellation controls.
- Mark completed or no-show.

Main data:

- Appointment number, customer, service, staff, requested time, scheduled time, status, notes.

Primary actions:

- Finish a service and record its transaction atomically.
- Mark a confirmed visit no-show.
- Click an open therapist time to create a confirmed internal appointment.
- On mobile, use Add appointment on this day and select an available therapist in the same modal.
- Switch to Availability mode to add recurring shifts or date exceptions.
- Reschedule.
- Cancel.
- Complete and record transaction.

### Customers

Route group: `/admin/customers`

Purpose:

- Manage customer records and behavior history.

Screens:

- Customer list.
- Customer detail.
- Customer appointment history.
- Customer transaction history.
- Customer feedback history.
- Customer promotion suggestions.

Main data:

- Profile, contact details, appointments, transactions, feedback, RFM-related activity.

Primary actions:

- View customer history.
- Update customer notes.
- Review promotion suggestions.

### Staff

Route group: `/admin/staff`

Purpose:

- Manage staff profiles, service eligibility, and schedules.

Screens:

- Staff list.
- Staff detail.
- Create/edit staff account.
- Weekly schedule editor.
- Schedule exceptions.
- Staff-service assignments.

Main data:

- Staff user, profile, bookable status, assigned services, weekly availability, exceptions.

Primary actions:

- Create staff.
- Assign services.
- Edit schedule.
- Add schedule exception.

### Services

Route group: `/admin/services`

Purpose:

- Manage spa service catalog.

Screens:

- Service list.
- Create/edit service.
- Service detail.

Main data:

- Service name, description, duration, price, active status.

Primary actions:

- Add service.
- Update service.
- Activate/deactivate service.

### Transactions

Route group: `/admin/transactions`

Purpose:

- Manage manual payment and service transaction records.

Screens:

- Transaction list.
- Create transaction.
- Transaction detail.

Main data:

- Transaction number, customer, appointment, service, amount, payment status, payment method, recorded by, date.

Primary actions:

- Record payment.
- Update payment status.
- View customer transaction history.

### Promotions

Route group: `/admin/promotions`

Purpose:

- Review RFM segments, promotion rules, and stored promotion suggestions.

Screens:

- RFM segment list.
- Promotion rule list.
- Promotion suggestion queue.
- Promotion suggestion detail.

Main data:

- Customer, RFM segment, recency, frequency, monetary total, suggested offer, status.

Primary actions:

- Review suggestion.
- Mark applied.
- Dismiss suggestion.
- Update promotion rule.

### Feedback

Route group: `/admin/feedback`

Purpose:

- Review customer ratings, comments, and sentiment summaries.

Screens:

- Feedback list.
- Feedback detail.
- Sentiment summary.

Main data:

- Customer, appointment, service, rating, comment, sentiment label, submitted date.

Primary actions:

- Filter feedback.
- View related appointment/customer.

### Reports

Route group: `/admin/reports`

Purpose:

- Support management decisions without direct database access.

Screens:

- Appointment report.
- Transaction/revenue report.
- Customer activity report.
- Promotion suggestion report.
- Feedback sentiment report.

Main data:

- Filtered summaries and tabular records by date range, status, service, staff, customer, and sentiment.

Primary actions:

- Filter report.
- Export CSV.

### Settings

Route group: `/admin/settings`

Purpose:

- Manage basic application settings.

Screens:

- Business profile.
- User account management.
- System defaults.

Main data:

- Business contact information, operating assumptions, default payment methods, user status.

Primary actions:

- Update business details.
- Activate/deactivate user accounts.

## Staff Screens

Staff navigation uses sidebar modules focused on daily operations.

### Staff Dashboard

Route: `/staff/dashboard`

Purpose:

- Give staff a daily work view.

Main data:

- Today assigned appointments.
- Upcoming confirmed appointments.
- Recently completed appointments.

Primary actions:

- Open assigned appointment.

### Staff Appointments

Route group: `/staff/appointments`

Purpose:

- Let staff view their assigned appointments.

Screens:

- Personal weekly calendar with assigned appointments.
- Appointment detail.

Rules:

- Staff can view assigned appointments.
- Staff appointment and transaction workspaces are read-only.
- Staff availability is read-only and maintained by admin.
- Staff cannot access admin-only settings.

Primary actions:

- Confirm appointment.
- Reschedule appointment.
- Mark completed.
- Mark no-show.
- Record transaction.

### Staff Customers

Route group: `/staff/customers`

Purpose:

- Let staff view customer details needed for service delivery.

Screens:

- Customer lookup.
- Customer detail.

Main data:

- Customer contact details, appointment history, relevant notes, feedback history.

Rules:

- Staff can view operational customer information.
- Staff should not manage system-level user settings.

### Staff Transactions

Route group: `/staff/transactions`

Purpose:

- Let staff review transactions linked to assigned appointments.

Screens:

- Create transaction from appointment.
- Read-only transaction list and detail for assigned appointments.
- Transaction detail.

Primary actions:

- Record payment.
- Update payment status where allowed.

### Staff Feedback

Route group: `/staff/feedback`

Purpose:

- Let staff view feedback related to services and appointments.

Screens:

- Feedback list.
- Feedback detail.

Rules:

- Staff can view feedback but should not edit submitted customer feedback.

## Customer Screens

Customer navigation is appointment-first.

### My Appointments

Route: `/customer/appointments`

Purpose:

- Primary customer landing page after login.

Main data:

- Monthly calendar of confirmed upcoming appointments and appointment history.
- Selected-day visit details and booking status.

Primary actions:

- Book an appointment.
- View appointment details.
- Cancel a confirmed booking before its start.
- Submit feedback for completed appointment.

### Book Appointment

Route: `/customer/appointments/create`

Purpose:

- Let customers reserve an available appointment immediately.
- Use the dedicated page as the primary booking experience rather than embedding the full calendar form in the appointment list.

Main data:

- Active services.
- Available staff or preferred staff where supported.
- Preferred date/time.
- Customer notes.

Rules:

- Successful submissions start as `confirmed` and reserve therapist capacity immediately.
- The server locks eligible therapist rows and rechecks availability before saving.
- An available preferred therapist is assigned first; otherwise the least-booked eligible therapist is selected.
- A lost concurrency race returns a slot-unavailable error without creating an appointment.

Primary actions:

- Confirm booking.

### Appointment Detail

Route: `/customer/appointments/{appointment}`

Purpose:

- Let customers view status and details for one appointment.

Main data:

- Appointment number, service, requested time, scheduled time, assigned staff, status, notes.

Primary actions:

- Submit feedback for completed appointment.
- Cancel a confirmed appointment before its scheduled start.

### Feedback

Route group: `/customer/feedback`

Purpose:

- Let customers submit and view their own service feedback.

Screens:

- Feedback form.
- My feedback history.

Rules:

- One feedback record per completed appointment in the MVP.
- Customers can view their own feedback only.

### Profile

Route: `/customer/profile`

Purpose:

- Let customers manage their own profile.

Main data:

- Name, email, phone, address, contact preference.

Primary actions:

- Update profile.
- Change an existing password through the auth flow.
- Reconfirm a linked Google identity to create a first password and enable conventional email/password login.

## Primary User Journeys

### Customer Appointment Booking

1. Customer registers or logs in.
2. Customer lands on My Appointments.
3. Customer opens Book Appointment.
4. Customer selects service and preferred date/time.
5. System atomically assigns an available therapist and creates a `confirmed` appointment.
6. Customer sees the reserved time and therapist in My Appointments.

### Service Completion And Payment

1. Admin opens a ready confirmed appointment from the chronological service queue.
2. Admin enters payment details and finishes the service.
3. The completed status, audit log, and linked transaction are saved atomically.
4. The transaction becomes part of customer history and RFM calculation input.

### Feedback Submission

1. Customer opens a completed appointment.
2. Customer submits rating and comment.
3. System stores sentiment label.
4. Admin sees feedback in feedback reports and dashboard summaries.

### Promotion Review

1. Admin opens promotion suggestions.
2. Admin reviews customer RFM segment and suggested offer.
3. Admin marks suggestion reviewed, applied, or dismissed.
4. Suggestion remains stored for audit and reporting.

## Access Matrix

| Area | Admin | Staff | Customer | Guest |
| --- | --- | --- | --- | --- |
| Public landing/services | Yes | Yes | Yes | Yes |
| Admin dashboard | Yes | No | No | No |
| Staff dashboard | No | Yes | No | No |
| Customer appointments | No | No | Own only | No |
| Appointments management | All | Assigned read-only | Own booking/cancellation | No |
| Services management | Yes | View only if needed | View active services | View active services |
| Staff management | Yes | No | No | No |
| Customer records | Yes | Operational access | Own profile only | No |
| Transactions | All | Allowed operational records | Own history if exposed | No |
| Promotions | Yes | Review/apply if allowed | No | No |
| Feedback | All | Related view only | Own feedback only | No |
| Reports | Yes | Limited operational reports | No | No |
| Settings | Yes | No | Own profile only | No |

## MVP Coverage Check

- Appointment scheduling is covered by automated customer booking, admin appointment management, and staff read-only schedules.
- Service and staff management are covered by admin modules.
- Customer records are covered by admin customer screens and staff customer lookup.
- Manual transactions are covered by admin and staff transaction screens.
- RFM promotion suggestions are covered by admin promotions.
- Feedback and sentiment analytics are covered by customer feedback and admin feedback screens.
- Reports and exports are covered by admin reports.
