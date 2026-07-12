<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_feeds_enforce_roles_and_customer_ownership(): void
    {
        $admin = User::factory()->admin()->create();
        $staffUser = User::factory()->staff()->create();
        StaffProfile::factory()->for($staffUser)->create();
        $firstCustomer = User::factory()->customer()->create();
        $firstProfile = CustomerProfile::factory()->for($firstCustomer)->create();
        $secondCustomer = User::factory()->customer()->create();
        $secondProfile = CustomerProfile::factory()->for($secondCustomer)->create();
        $service = Service::factory()->create();
        $start = now()->addDays(2)->setTime(14, 0);

        Appointment::factory()->for($firstProfile)->for($service)->create([
            'appointment_number' => 'APT-OWN-CALENDAR',
            'requested_start_at' => $start,
        ]);
        Appointment::factory()->for($secondProfile)->for($service)->create([
            'appointment_number' => 'APT-OTHER-CALENDAR',
            'requested_start_at' => $start,
        ]);

        $customerResponse = $this->actingAs($firstCustomer)->getJson(route('customer.appointments.calendar', [
            'month' => $start->format('Y-m'),
        ], false));
        $numbers = collect($customerResponse->json('events'))->pluck('appointment_number');

        $this->assertTrue($numbers->contains('APT-OWN-CALENDAR'));
        $this->assertFalse($numbers->contains('APT-OTHER-CALENDAR'));
        $this->actingAs($firstCustomer)->getJson(route('admin.appointments.calendar', [
            'start' => $start->toDateString(),
            'end' => $start->copy()->addDay()->toDateString(),
        ], false))->assertForbidden();
        $this->actingAs($staffUser)->getJson(route('customer.appointments.calendar', [
            'month' => $start->format('Y-m'),
        ], false))->assertForbidden();
        $this->actingAs($admin)->getJson(route('staff.appointments.calendar', [
            'start' => $start->toDateString(),
            'end' => $start->copy()->addDay()->toDateString(),
        ], false))->assertForbidden();
    }

    public function test_customer_slots_can_end_exactly_at_midnight(): void
    {
        $customer = User::factory()->customer()->create();
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(23, 0, 0);

        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '00:00:00',
            'ends_next_day' => true,
        ]);

        $response = $this->actingAs($customer)->getJson(route('customer.appointments.availability', [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $staff->id,
            'month' => $start->format('Y-m'),
        ], false));

        $response->assertOk();
        $slot = collect($response->json('dates.'.$start->toDateString()))->firstWhere('time', '23:00');

        $this->assertNotNull($slot);
        $this->assertSame($start->copy()->addHour()->toDateTimeString(), $slot['ends_at']);
    }

    public function test_operational_calendar_rejects_unbounded_ranges(): void
    {
        $admin = User::factory()->admin()->create();
        $start = now()->startOfDay();

        $this->actingAs($admin)->getJson(route('admin.appointments.calendar', [
            'start' => $start->toDateString(),
            'end' => $start->copy()->addDays(9)->toDateString(),
        ], false))->assertUnprocessable()->assertJsonValidationErrors('end');
    }

    public function test_preference_is_assigned_and_confirmed_booking_holds_capacity(): void
    {
        $firstCustomer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($firstCustomer)->create();
        $secondCustomer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($secondCustomer)->create();
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);

        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);

        $this->actingAs($firstCustomer)->post(route('customer.appointments.store', absolute: false), [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $staff->id,
            'requested_start_at' => $start->toDateTimeString(),
            'customer_notes' => 'Quiet room please.',
        ])->assertRedirect();

        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($staff->id, $appointment->staff_profile_id);
        $this->assertSame($staff->id, $appointment->preferred_staff_profile_id);
        $this->assertSame('Quiet room please.', $appointment->customer_notes);

        $response = $this->actingAs($secondCustomer)->getJson(route('customer.appointments.availability', [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $staff->id,
            'month' => $start->format('Y-m'),
        ], false));

        $this->assertFalse(collect($response->json('dates.'.$start->toDateString()))->contains('time', '14:00'));
    }

    public function test_staff_calendar_hides_unassigned_pending_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $firstUser = User::factory()->staff()->create();
        $firstStaff = StaffProfile::factory()->for($firstUser)->create();
        $secondUser = User::factory()->staff()->create();
        $secondStaff = StaffProfile::factory()->for($secondUser)->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['is_active' => true]);
        $firstStaff->services()->attach($service);
        $secondStaff->services()->attach($service);
        $start = now()->addDays(2)->setTime(14, 0, 0);

        foreach ([
            ['number' => 'APT-NO-PREFERENCE', 'preferred' => null],
            ['number' => 'APT-PREFERS-FIRST', 'preferred' => $firstStaff->id],
            ['number' => 'APT-PREFERS-SECOND', 'preferred' => $secondStaff->id],
        ] as $requestData) {
            Appointment::factory()->for($customer)->for($service)->create([
                'appointment_number' => $requestData['number'],
                'preferred_staff_profile_id' => $requestData['preferred'],
                'requested_start_at' => $start,
                'status' => Appointment::STATUS_PENDING,
            ]);
        }

        $range = [
            'start' => $start->copy()->startOfDay()->toDateString(),
            'end' => $start->copy()->addDay()->startOfDay()->toDateString(),
        ];
        $staffResponse = $this->actingAs($firstUser)->getJson(route('staff.appointments.calendar', $range, false));
        $staffNumbers = collect($staffResponse->json('events'))->pluck('appointment_number')->filter();

        $this->assertFalse($staffNumbers->contains('APT-NO-PREFERENCE'));
        $this->assertFalse($staffNumbers->contains('APT-PREFERS-FIRST'));
        $this->assertFalse($staffNumbers->contains('APT-PREFERS-SECOND'));

        $adminResponse = $this->actingAs($admin)->getJson(route('admin.appointments.calendar', [
            ...$range,
            'mode' => 'bookings',
        ], false));
        $adminNumbers = collect($adminResponse->json('events'))->pluck('appointment_number')->filter();

        $this->assertTrue($adminNumbers->contains('APT-PREFERS-SECOND'));
    }

    public function test_availability_change_is_rolled_back_when_it_breaks_a_confirmed_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(15, 0, 0);

        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '00:00:00',
            'ends_next_day' => true,
        ]);
        Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
            'appointment_number' => 'APT-CONFLICT-GUARD',
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.staff.show', $staff, false))
            ->post(route('admin.staff.schedule-exceptions.store', $staff, false), [
                'exception_date' => $start->toDateString(),
                'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Conflicting leave',
            ])
            ->assertRedirect(route('admin.staff.show', $staff, false))
            ->assertSessionHasErrors('schedule')
            ->assertSessionHas('schedule_conflicts');

        $this->assertDatabaseMissing('staff_schedule_exceptions', ['reason' => 'Conflicting leave']);

        $this->actingAs($admin)->post(route('admin.staff.schedule-exceptions.store', $staff, false), [
            'exception_date' => $start->toDateString(),
            'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
            'start_time' => '20:00',
            'end_time' => '21:00',
            'reason' => 'Harmless leave',
        ])->assertRedirect(route('admin.staff.show', $staff, false));

        $this->assertDatabaseHas('staff_schedule_exceptions', ['reason' => 'Harmless leave']);
    }

    public function test_admin_available_therapists_lookup_excludes_confirmed_overlap(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $first = StaffProfile::factory()->create();
        $second = StaffProfile::factory()->create();
        $start = now()->addWeek()->setTime(14, 0, 0);

        foreach ([$first, $second] as $staff) {
            $staff->services()->attach($service);
            StaffWeeklySchedule::factory()->for($staff)->create([
                'day_of_week' => $start->dayOfWeek,
                'start_time' => '13:00:00',
                'end_time' => '18:00:00',
            ]);
        }

        Appointment::factory()->for($customer)->for($service)->for($first, 'staffProfile')->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.appointments.available-therapists', [
            'service_id' => $service->id,
            'starts_at' => $start->toDateTimeString(),
        ], false));

        $response->assertOk();
        $ids = collect($response->json('therapists'))->pluck('id');
        $this->assertFalse($ids->contains($first->id));
        $this->assertTrue($ids->contains($second->id));
    }

    public function test_admin_calendar_page_exposes_confirmed_creation_modal_and_selection_hooks(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee('calendar-appointment-create', false)
            ->assertSee(route('admin.appointments.calendar.store', absolute: false), false)
            ->assertSee('calendar-booking-selected', false)
            ->assertSee('Confirmed reservation')
            ->assertSee('Add appointment on this day');
    }

    public function test_admin_calendar_creation_saves_confirmed_reservation_and_returns_to_calendar(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 90, 'is_active' => true]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.appointments.calendar.store', absolute: false), [
                '_modal' => 'calendar-appointment-create',
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'staff_profile_id' => $staff->id,
                'requested_start_at' => $start->toDateTimeString(),
                'scheduled_start_at' => $start->toDateTimeString(),
                'status' => Appointment::STATUS_CONFIRMED,
            ])
            ->assertRedirect(route('admin.appointments.index', absolute: false));

        $appointment = Appointment::query()->sole();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($staff->id, $appointment->staff_profile_id);
        $this->assertTrue($appointment->scheduled_end_at->equalTo($start->copy()->addMinutes(90)));
        $this->assertDatabaseHas('appointment_status_logs', [
            'appointment_id' => $appointment->id,
            'from_status' => null,
            'to_status' => Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_admin_calendar_creation_rejects_pending_missing_staff_and_overlap(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);
        Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        $base = [
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'requested_start_at' => $start->toDateTimeString(),
            'scheduled_start_at' => $start->toDateTimeString(),
        ];

        $this->actingAs($admin)
            ->from(route('admin.appointments.index', absolute: false))
            ->post(route('admin.appointments.calendar.store', absolute: false), [
                ...$base,
                'status' => Appointment::STATUS_PENDING,
            ])
            ->assertSessionHasErrors(['status', 'staff_profile_id']);

        $this->actingAs($admin)
            ->from(route('admin.appointments.index', absolute: false))
            ->post(route('admin.appointments.calendar.store', absolute: false), [
                ...$base,
                'staff_profile_id' => $staff->id,
                'status' => Appointment::STATUS_CONFIRMED,
                '_modal' => 'calendar-appointment-create',
            ])
            ->assertSessionHasErrors('scheduled_start_at')
            ->assertSessionHasInput('_modal', 'calendar-appointment-create');

        $this->assertDatabaseCount('appointments', 1);
    }
}
