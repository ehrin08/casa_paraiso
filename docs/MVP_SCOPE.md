# Casa Paraiso MVP Scope

## Purpose

Define the first build of the centralized Spa Appointment and Management System for Casa Paraiso - Body and Wellness Spa.

The MVP should support appointment scheduling, customer records, manual transaction records, RFM-based promotion suggestions, and simple customer feedback analytics while staying practical for Hostinger shared/web hosting and a business without 24/7 IT support.

## MVP Users

- Admin: manages services, staff, appointments, customer records, transactions, reports, promotion suggestions, and feedback insights.
- Receptionist: runs restricted front-desk appointment, customer-contact, and payment workflows without access to schedules, insights, reports, settings, user administration, or commissions.
- Therapist (`staff` role): reviews assigned appointments, related operational records, and personal commission history.
- Customer: books appointments, views booking status, cancels before the scheduled start, and submits feedback after service.

Users authenticate with either a verified Google account or a verified email and password. Customers may self-register; staff and admin emails must be pre-authorized by the protected super administrator. Authenticated Google-only users may reconfirm their linked Google identity in Account Settings to create a password, while passwordless accounts without Google linkage use the reset-password flow.

## MVP Features

### Appointment Scheduling

- Customers can book available appointments and receive immediate confirmation.
- Customers can open booking directly from My Appointments; the full-page booking route remains an accessible fallback.
- The system atomically assigns an eligible therapist, preferring the customer's selection when available and otherwise balancing future bookings.
- Admin can reschedule, cancel, mark no-show, or finish confirmed appointments.
- Receptionists can create, reschedule, cancel, mark no-show, or finish confirmed appointments, but cannot edit therapist availability.
- Appointments should include customer, service, preferred date and time, assigned therapist, status, and notes.
- Scheduling should consider service duration, therapist assignment, and therapist availability.
- Confirmed customer bookings immediately reserve therapist capacity and disappear from availability when no eligible therapist remains.

### Service And Therapist Management

- Admin can manage spa services, including service name, description, duration, and price.
- The initial active service catalog should use the Casa Paraiso package menu:
  - GAIA TOUCH: PHP 499.00, 1 hour.
  - TETHYS FLOW: PHP 649.00, 1 hour.
  - HESTIA WARMTH: PHP 749.00, 1 hour 30 minutes.
  - AURORA BREEZE: PHP 849.00, 2 hours.
- Add-ons such as Ventosa, Hot Compress, Hot Stone, 30-Minute Back Massage, and VIP Room are shown as customer-facing content only until selectable add-ons are added in a later phase.
- Business hours are shown as open every day from 1:00 PM to 12:00 MN.
- Admin can manage therapist profiles and therapist availability. Therapist profiles retain the internal `staff` role and use `staff_type = therapist`.
- Therapist availability should be simple enough for non-technical staff to maintain.

### Customer Records

- Admin and staff can view customer profiles, appointment history, transaction history, feedback history, and promotion-relevant behavior.
- Customer records should support accurate real-time booking, records, and transaction management.

### Manual Transactions

- Admin records service transactions manually, including through the atomic finish-service workflow.
- Transaction records should include customer, appointment or service reference, amount, payment status, payment method, transaction date, and staff/admin recorder.
- Online payment gateway integration is not part of the MVP.

### Application Settings

- Admin and Super Administrator can maintain the public business name, contact details, address, and the payment method used to prefill new Admin and Receptionist payment forms.
- Operating hours, booking intervals, timezone, commission rate, authorization, and scheduling invariants remain code-controlled safeguards rather than editable business fields.
- Only the protected Super Administrator can provision users, change roles, or activate and deactivate accounts.

### Therapist Commissions

- A completed appointment with an assigned therapist earns a commission only when its linked transaction is fully paid.
- The system-wide therapist commission rate is 22% of the actual transaction amount.
- Admin records external payouts; the system does not transfer money.
- Paid commission rows are immutable. Later transaction corrections create traceable signed adjustments.
- Therapists can view only their own commission totals and history. Receptionists have no commission access.

### RFM Promotion Suggestions

- The system should classify customers using RFM logic:
  - Recency: how recently the customer visited or completed a transaction.
  - Frequency: how often the customer books or completes services.
  - Monetary: how much the customer has spent.
- Promotion output should be admin-visible suggestions, not automatic customer discounts.
- Admin or staff should review promotion suggestions before applying or contacting customers.
- RFM logic should be rule-based and application-driven to avoid external service dependencies.

### Feedback And Sentiment Analytics

- Customers can submit a star rating and written comment after service.
- The system should classify feedback sentiment as positive, neutral, or negative using simple application logic.
- Admin should see feedback summaries that support management decisions.
- External AI sentiment services are not part of the MVP.

### Reports And Exports

- Admin should have access to useful summaries for appointments, transactions, customers, promotions, and feedback.
- Add export or download actions where they reduce dependency on technical staff.
- Reports should support timely management decisions without requiring direct database access.

## Out Of Scope For MVP

- Online payment gateway integration.
- VPS deployment or server administration.
- External AI services for sentiment analysis.
- Persistent background workers, custom daemons, or long-running Node.js services.
- 24/7 technical monitoring requirements.

## Operational Constraints

- Target Hostinger shared/web hosting by default.
- Keep the application compatible with Docker/Sail local development, with XAMPP / Apache as fallback.
- Use MariaDB/MySQL-compatible database design.
- Keep production credentials outside committed source files.
- Design recovery paths around Hostinger backups, database exports, and documented restore steps.

## Acceptance Criteria

- MVP scope clearly supports bookings, customer records, manual transactions, RFM promotion suggestions, feedback insights, and low-maintenance operation.
- Each MVP module can be implemented without VPS-only services or external AI dependencies.
- Admin remains in control of service outcomes, transaction recording, and promotion application.
- Customer-facing workflows stay limited to confirmed booking, pre-start cancellation, booking status, and feedback.
- Future implementation work should use this document as the first-build scope reference before database, screen, or API design.
