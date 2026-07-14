<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_appointments_embeds_booking_modal_and_keeps_full_page_fallback(): void
    {
        $customer = CustomerProfile::factory()->create();

        $this->actingAs($customer->user)
            ->get(route('customer.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee('customer-book-appointment', false)
            ->assertSee('_booking_context', false)
            ->assertSee('Book appointment');

        $this->actingAs($customer->user)
            ->get(route('customer.appointments.create', absolute: false))
            ->assertOk()
            ->assertSee('Book an appointment');
    }

    public function test_calendar_validation_failure_reopens_modal_with_old_input_and_inline_errors(): void
    {
        $customer = CustomerProfile::factory()->create();

        $this->actingAs($customer->user)
            ->from(route('customer.appointments.index', absolute: false))
            ->post(route('customer.appointments.store', absolute: false), [
                '_booking_context' => 'calendar',
                'service_id' => '',
                'requested_start_at' => '',
                'customer_notes' => 'Keep this note.',
            ])
            ->assertRedirect(route('customer.appointments.index', absolute: false))
            ->assertSessionHasErrors(['service_id', 'requested_start_at']);

        $this->get(route('customer.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee('initialShow: true', false)
            ->assertSee('Keep this note.')
            ->assertSee('booking-date-selected', false)
            ->assertSee('preselectDate', false);
    }

    public function test_calendar_submission_still_creates_an_immediately_confirmed_booking(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $therapist = StaffProfile::factory()->create();
        $therapist->services()->attach($service);
        $start = now()->addDays(8)->setTime(14, 0);
        StaffWeeklySchedule::factory()->for($therapist)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
            'ends_next_day' => false,
            'is_available' => true,
        ]);

        $response = $this->actingAs($customer->user)->post(route('customer.appointments.store', absolute: false), [
            '_booking_context' => 'calendar',
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $therapist->id,
            'requested_start_at' => $start->format('Y-m-d H:i:s'),
            'customer_notes' => 'Calendar booking.',
        ]);

        $appointment = Appointment::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('customer.appointments.show', $appointment, false));
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($therapist->id, $appointment->staff_profile_id);
    }
}
