<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CustomerDuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkflowAccessRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_workflows_reject_pending_and_admin_creation_uses_one_schedule_value(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create([
            'duration_minutes' => 60,
            'price' => 950,
        ]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);

        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);

        $this->actingAs($admin)->post(route('admin.appointments.store', absolute: false), [
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'staff_profile_id' => $staff->id,
            'scheduled_start_at' => $start->toDateTimeString(),
        ])->assertRedirect(route('admin.appointments.index', absolute: false));

        $appointment = Appointment::query()->sole();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertTrue($appointment->requested_start_at->equalTo($start));
        $this->assertTrue($appointment->scheduled_start_at->equalTo($start));
        $this->assertSame('950.00', $appointment->quoted_amount);

        $this->actingAs($admin)
            ->from(route('admin.appointments.index', absolute: false))
            ->post(route('admin.appointments.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'scheduled_start_at' => $start->copy()->addHours(2)->toDateTimeString(),
                'status' => Appointment::STATUS_PENDING,
            ])
            ->assertSessionHasErrors(['staff_profile_id', 'status']);

        $this->assertDatabaseCount('appointments', 1);
        $this->assertSame([Appointment::STATUS_CONFIRMED], Appointment::CREATION_STATUSES);
        $this->assertNotContains(Appointment::STATUS_PENDING, Appointment::ACTIVE_STATUSES);
    }

    public function test_pending_records_remain_readable_but_are_excluded_from_active_calendar_feeds(): void
    {
        $admin = User::factory()->admin()->create();
        $customerUser = User::factory()->customer()->create();
        $customer = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create();
        $start = now()->addDays(2)->setTime(14, 0, 0);

        $pending = Appointment::factory()->for($customer)->for($service)->create([
            'appointment_number' => 'APT-HISTORICAL-PENDING',
            'status' => Appointment::STATUS_PENDING,
            'requested_start_at' => $start,
        ]);

        $this->assertNotContains(Appointment::STATUS_PENDING, $pending->allowedTargetStatuses());

        $this->actingAs($admin)
            ->get(route('admin.appointments.show', $pending, false))
            ->assertOk()
            ->assertSee('APT-HISTORICAL-PENDING');

        $this->actingAs($admin)->getJson(route('admin.appointments.calendar', [
            'start' => $start->copy()->startOfDay()->toDateString(),
            'end' => $start->copy()->addDay()->startOfDay()->toDateString(),
            'status' => Appointment::STATUS_PENDING,
        ], false))->assertUnprocessable()->assertJsonValidationErrors('status');

        $response = $this->actingAs($customerUser)->getJson(route('customer.appointments.calendar', [
            'month' => $start->format('Y-m'),
        ], false));

        $response->assertOk();
        $this->assertFalse(collect($response->json('events'))->pluck('appointment_number')->contains('APT-HISTORICAL-PENDING'));
    }

    public function test_staff_workspaces_only_expose_assigned_active_history_feedback_and_transactions(): void
    {
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $otherStaff = StaffProfile::factory()->create();
        $visibleCustomer = CustomerProfile::factory()->for(User::factory()->customer()->create(['name' => 'Visible Customer']))->create();
        $hiddenCustomer = CustomerProfile::factory()->for(User::factory()->customer()->create(['name' => 'Hidden Customer']))->create();
        $pendingOnlyCustomer = CustomerProfile::factory()->for(User::factory()->customer()->create(['name' => 'Pending Only Customer']))->create();
        $service = Service::factory()->create();
        $start = now()->addDay()->setTime(14, 0);

        $visibleAppointment = $this->confirmedAppointment($visibleCustomer, $service, $staff, $start, 'APT-VISIBLE');
        $sameCustomerOtherAppointment = $this->confirmedAppointment($visibleCustomer, $service, $otherStaff, $start->copy()->addDay(), 'APT-OTHER-STAFF');
        $hiddenAppointment = $this->confirmedAppointment($hiddenCustomer, $service, $otherStaff, $start, 'APT-HIDDEN');
        $pendingAppointment = Appointment::factory()->for($pendingOnlyCustomer)->for($service)->for($staff, 'staffProfile')->create([
            'appointment_number' => 'APT-PENDING-ONLY',
            'status' => Appointment::STATUS_PENDING,
            'requested_start_at' => $start,
            'scheduled_start_at' => null,
            'scheduled_end_at' => null,
        ]);

        $assignedTransaction = Transaction::factory()
            ->for($visibleCustomer)
            ->for($service)
            ->for($visibleAppointment)
            ->create(['transaction_number' => 'TRX-ASSIGNED']);
        $unassignedTransaction = Transaction::factory()
            ->for($hiddenCustomer)
            ->for($service)
            ->for($hiddenAppointment)
            ->create([
                'transaction_number' => 'TRX-RECORDED-BY-STAFF',
                'recorded_by' => $staffUser->id,
            ]);
        $pendingTransaction = Transaction::factory()
            ->for($pendingOnlyCustomer)
            ->for($service)
            ->for($pendingAppointment)
            ->create([
                'transaction_number' => 'TRX-PENDING',
                'recorded_by' => $staffUser->id,
            ]);

        $assignedFeedback = Feedback::factory()->for($visibleCustomer)->for($service)->for($visibleAppointment)->create(['comment' => 'Assigned feedback']);
        Feedback::factory()->for($visibleCustomer)->for($service)->for($sameCustomerOtherAppointment)->create(['comment' => 'Other therapist feedback']);
        $hiddenFeedback = Feedback::factory()->for($hiddenCustomer)->for($service)->for($hiddenAppointment)->create(['comment' => 'Hidden feedback']);

        $this->actingAs($staffUser)
            ->get(route('staff.customers.index', absolute: false))
            ->assertOk()
            ->assertSee('Visible Customer')
            ->assertDontSee('Hidden Customer')
            ->assertDontSee('Pending Only Customer');

        $this->actingAs($staffUser)
            ->get(route('staff.customers.show', $visibleCustomer, false))
            ->assertOk()
            ->assertSee('APT-VISIBLE')
            ->assertSee('Assigned feedback')
            ->assertDontSee('APT-OTHER-STAFF')
            ->assertDontSee('Other therapist feedback');

        $this->actingAs($staffUser)
            ->get(route('staff.customers.show', $hiddenCustomer, false))
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->get(route('staff.customers.show', $pendingOnlyCustomer, false))
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->get(route('staff.feedback.index', absolute: false))
            ->assertOk()
            ->assertSee('Visible Customer')
            ->assertDontSee('Hidden Customer');

        $this->actingAs($staffUser)
            ->get(route('staff.feedback.show', $assignedFeedback, false))
            ->assertOk()
            ->assertSee('Assigned feedback');

        $this->actingAs($staffUser)
            ->get(route('staff.feedback.show', $hiddenFeedback, false))
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->get(route('staff.transactions.index', absolute: false))
            ->assertOk()
            ->assertSee('TRX-ASSIGNED')
            ->assertDontSee('TRX-RECORDED-BY-STAFF')
            ->assertDontSee('TRX-PENDING');

        $this->actingAs($staffUser)
            ->get(route('staff.transactions.show', $assignedTransaction, false))
            ->assertOk();

        $this->actingAs($staffUser)
            ->get(route('staff.transactions.show', $unassignedTransaction, false))
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->get(route('staff.transactions.show', $pendingTransaction, false))
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->get(route('staff.appointments.show', $pendingAppointment, false))
            ->assertForbidden();
    }

    public function test_customer_creation_reuses_exact_email_and_requires_review_for_strong_name_matches(): void
    {
        $superAdmin = User::factory()->create([
            'email' => config('auth.super_admin_email'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $existingUser = User::factory()->customer()->create([
            'name' => 'Jane Dela Cruz',
            'email' => 'jane@example.com',
            'phone' => '0917 123 4567',
        ]);
        $existingCustomer = CustomerProfile::factory()->for($existingUser)->create();

        $this->actingAs($superAdmin)->post(route('admin.users.store', absolute: false), [
            'name' => 'Different Name',
            'email' => ' JANE@EXAMPLE.COM ',
            'role' => User::ROLE_CUSTOMER,
            'is_active' => '1',
        ])->assertRedirect(route('admin.customers.show', $existingCustomer, false));

        $this->assertSame(2, User::query()->count());

        $payload = [
            'name' => 'JANE--DELA CRUZ',
            'email' => 'jane.separate@example.com',
            'phone' => '0917-123-4567',
            'role' => User::ROLE_CUSTOMER,
            'is_active' => '1',
        ];

        $this->actingAs($superAdmin)
            ->from(route('admin.users.index', absolute: false))
            ->post(route('admin.users.store', absolute: false), $payload)
            ->assertSessionHasErrors('duplicate_reviewed')
            ->assertSessionHas('duplicateCustomerWarnings', fn (array $warnings) => collect($warnings)->contains(
                fn (array $warning) => in_array('phone', $warning['match_types'], true),
            ));

        $this->assertDatabaseMissing('users', ['email' => 'jane.separate@example.com']);

        $this->actingAs($superAdmin)->post(route('admin.users.store', absolute: false), [
            ...$payload,
            'duplicate_reviewed' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'jane.separate@example.com',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $separateCustomer = User::query()->where('email', 'jane.separate@example.com')->firstOrFail();
        $separateCustomer->forceFill(['email_verified_at' => now()])->save();
        $profilePayload = [
            'name' => 'Jane Dela Cruz',
            'phone' => '0917-123-4567',
        ];

        $this->actingAs($separateCustomer)
            ->from(route('profile.edit', absolute: false))
            ->patch(route('profile.update', absolute: false), $profilePayload)
            ->assertSessionHasErrors('duplicate_reviewed')
            ->assertSessionHas('duplicateCustomerWarnings');

        $this->actingAs($separateCustomer)->patch(route('profile.update', absolute: false), [
            ...$profilePayload,
            'duplicate_reviewed' => '1',
        ])->assertRedirect(route('profile.edit', absolute: false));

        $this->assertSame('0917-123-4567', $separateCustomer->fresh()->phone);

        $groups = app(CustomerDuplicateDetector::class)->reviewGroups();
        $this->assertTrue(collect($groups)->contains(fn (array $group) => $group['match_type'] === 'name'
            && $group['normalized_value'] === 'jane dela cruz'
            && count($group['customers']) === 2));
        $this->assertTrue(collect($groups)->contains(fn (array $group) => $group['match_type'] === 'phone'
            && $group['normalized_value'] === '09171234567'
            && count($group['customers']) === 2));
    }

    public function test_team_and_user_access_updates_ignore_fields_owned_by_the_other_workspace(): void
    {
        $superAdmin = User::factory()->create([
            'email' => config('auth.super_admin_email'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $staff = StaffProfile::factory()->create();
        $originalEmail = $staff->user->email;

        $this->actingAs($superAdmin)->patch(route('admin.staff.update', $staff, false), [
            'name' => 'Team Managed Name',
            'email' => 'ignored-by-team@example.com',
            'phone' => '09171234567',
            'is_active' => '0',
            'position' => 'Senior Therapist',
            'is_bookable' => '1',
            'service_ids' => [],
        ])->assertRedirect(route('admin.staff.show', $staff, false));

        $staffUser = $staff->user->fresh();
        $this->assertSame('Team Managed Name', $staffUser->name);
        $this->assertSame($originalEmail, $staffUser->email);
        $this->assertTrue($staffUser->is_active);

        $this->actingAs($superAdmin)->put(route('admin.users.update', $staffUser, false), [
            'name' => 'Ignored By User Access',
            'email' => 'access-owned@example.com',
            'role' => User::ROLE_STAFF,
            'is_active' => '1',
        ])->assertRedirect();

        $staffUser->refresh();
        $this->assertSame('Team Managed Name', $staffUser->name);
        $this->assertSame('access-owned@example.com', $staffUser->email);
    }

    private function confirmedAppointment(
        CustomerProfile $customer,
        Service $service,
        StaffProfile $staff,
        Carbon $start,
        string $number,
    ): Appointment {
        return Appointment::factory()
            ->for($customer)
            ->for($service)
            ->for($staff, 'staffProfile')
            ->create([
                'appointment_number' => $number,
                'status' => Appointment::STATUS_CONFIRMED,
                'requested_start_at' => $start,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start->copy()->addHour(),
                'confirmed_at' => now(),
            ]);
    }
}
