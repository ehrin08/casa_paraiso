# CRUD Remediation And Data Repair Record

This is the historical record of the CRUD audit, remediation, and approved local data repair completed on 2026-07-12. It is evidence, not an open checklist or authorization for another database operation.

The database safety gate remains in force: every future schema or data change requires explicit approval for the specific operation and records. Read-only inspection is allowed.

## Remediation Summary

The audit covered authenticated admin CRUD screens plus related routes, controllers, requests, models, services, views, migrations, and tests. The initial isolated Feature suite passed 70 tests with 470 assertions before remediation.

Completed work included:

- Correct appointment status persistence, scheduling fields, lifecycle transitions, transactional rescheduling, collision-safe numbering, and concurrency checks.
- Preserve historical identities through soft-deleted relationships while blocking account, role, bookable, service, or schedule changes that would invalidate future confirmed visits.
- Replace customer hard deletion with sign-in removal and profile anonymization while retaining de-identified business history.
- Make promotion generation idempotent, correct RFM edge cases, and preserve review timestamps and notes through valid status changes.
- Align report dates and filters with displayed business dates, stream exports beyond 5,000 rows, and neutralize spreadsheet formulas in CSV output.
- Enforce valid transaction state combinations and collision-safe transaction numbering.
- Complete profile fields, Google-linked account safeguards, feedback sentiment handling, duplicate protection, and RFM configuration screens.
- Resolve all remediation-related Pint findings without broad refactoring.

The remediation added the nullable unique `generation_key` migration for idempotent promotion suggestions. Applying that migration outside tests remains approval-gated.

## Approved Local Data Repair

### Read-only findings and recheck

At 11:04 AM Asia/Manila on 2026-07-12, the read-only recheck found:

| Appointment | Finding | Approved treatment |
| --- | --- | --- |
| `APT-DEMO-CONFIRMED` | Confirmed TETHYS FLOW visit assigned to soft-deleted staff profile `1` | Reassign to active Demo Therapist profile `2` after eligibility, schedule, and overlap checks |
| `APT-DEMO-PENDING` | Historical pending GAIA TOUCH request preferred soft-deleted profile `1` | Clear the unavailable preference; keep the request pending without reserving capacity |
| `APT-DEMO-COMPLETED` | Completed GAIA TOUCH history references soft-deleted profile `1` | Preserve the original therapist reference and load it for historical display |

The confirmed visit was scheduled for 3:00 PM–4:00 PM. Profile `2` was active and bookable, worked 1:00 PM–12:00 midnight, performed TETHYS FLOW, and had no confirmed overlap. No active therapist performed GAIA TOUCH.

`APT-DEMO-PENDING` was a retained historical/demo request. It does not describe the normal customer flow: new customer bookings auto-confirm, receive an eligible therapist, and reserve capacity atomically.

### Approval and backup

The user explicitly approved the two operational changes on 2026-07-12. Before execution, a consistent single-transaction MariaDB dump was created:

- File: `storage/app/backups/casa_paraiso_pre_crud_repair_20260712_1110.sql`
- Size: 43,567 bytes
- SHA-256: `096BD133FAAD55A6D54EB57B776EB0A11DFF4F6587B141D5BF80CF268343B015`

### Execution and result

The repair ran at approximately 11:10 AM Asia/Manila. The first command was a read-only dry run; the second applied both approved changes atomically through the appointment workflow:

```powershell
docker compose exec -T laravel.test php artisan casa:repair-approved-appointment-references
docker compose exec -T laravel.test php artisan casa:repair-approved-appointment-references --execute
```

| Appointment | Before | After |
| --- | --- | --- |
| `APT-DEMO-CONFIRMED` | Confirmed, `staff_profile_id = 1` (deleted) | Confirmed, `staff_profile_id = 2`; retained 3:00 PM–4:00 PM schedule |
| `APT-DEMO-PENDING` | Pending, `preferred_staff_profile_id = 1` (deleted) | Pending, preference cleared; no staff or reservation added |
| `APT-DEMO-COMPLETED` | Completed, `staff_profile_id = 1` (deleted) | Unchanged as historical evidence |

The changed records received internal audit notes and `updated_by = 2`. The post-repair `casa:audit-crud-integrity --verbose` command reported zero issues.

## Verification Evidence

- Final isolated Feature suite: 104 tests and 828 assertions passed.
- PHP syntax checks passed for 78 changed or new PHP files.
- Blade compilation, Composer validation, frontend build, and Laravel Pint passed; Pint covered 161 files.
- Admin, staff, and customer workspaces rendered in authenticated browser sessions with clean consoles; cross-role routes returned `403` as expected.
- Laravel logs contained no new CRUD errors.
- Reports and CSV exports were checked against known requested, scheduled, created, and paid dates, including output beyond 5,000 rows.
- The final read-only integrity audit reported zero issues after the approved repair.

## Recovery Rules

For any future approved repair:

1. Export the target database and capture original values before writing.
2. Recheck eligibility, schedule coverage, overlaps, and status while holding the relevant transaction locks.
3. Use the application workflow or a reviewed one-time command; do not bypass safeguards with ad hoc SQL.
4. Record audit evidence and verify admin, staff, customer, calendar, report, and export views.
5. If verification fails, restore the original values transactionally or restore the matching pre-change export, clear caches, and repeat the read-only audit.

On Hostinger shared hosting, use hPanel/phpMyAdmin for exports and restores. Never store production credentials in this repository or command history.
