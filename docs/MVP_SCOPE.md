# Casa Paraiso MVP Scope

## Purpose

Define the first build of the centralized Spa Appointment and Management System for Casa Paraiso - Body and Wellness Spa.

The MVP should support appointment scheduling, customer records, manual transaction records, RFM-based promotion suggestions, and simple customer feedback analytics while staying practical for Hostinger shared/web hosting and a business without 24/7 IT support.

## MVP Users

- Admin: manages services, staff, appointments, customer records, transactions, reports, promotion suggestions, and feedback insights.
- Staff: manages assigned appointments, confirms or updates booking requests, records service transactions, and reviews customer details needed for daily operations.
- Customer: requests appointments, views booking status, and submits feedback after service.

## MVP Features

### Appointment Scheduling

- Customers can request appointments.
- Staff or admin can confirm, reschedule, or cancel appointment requests.
- Appointments should include customer, service, preferred date and time, assigned staff member, status, and notes.
- Scheduling should consider service duration, staff assignment, and staff availability.
- Instant customer booking without staff confirmation is not part of the MVP.

### Service And Staff Management

- Admin can manage spa services, including service name, description, duration, and price.
- Admin can manage staff profiles and staff availability.
- Staff availability should be simple enough for non-technical staff to maintain.

### Customer Records

- Admin and staff can view customer profiles, appointment history, transaction history, feedback history, and promotion-relevant behavior.
- Customer records should support accurate real-time booking, records, and transaction management.

### Manual Transactions

- Staff or admin can record service transactions manually.
- Transaction records should include customer, appointment or service reference, amount, payment status, payment method, transaction date, and staff/admin recorder.
- Online payment gateway integration is not part of the MVP.

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
- Instant customer booking without staff confirmation.
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
- Staff/admin remain in control of appointment confirmation and promotion application.
- Customer-facing workflows stay limited to appointment requests, booking status, and feedback.
- Future implementation work should use this document as the first-build scope reference before database, screen, or API design.
