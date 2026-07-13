<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionRule;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveToTenWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_availability_endpoint_returns_real_calendar_slots(): void
    {
        $customer = User::factory()->customer()->create();
        StaffProfile::factory()->create();
        $staffUser = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->for($staffUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staffProfile->services()->attach($service);

        $slotDate = now()->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => $slotDate->dayOfWeek,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
        ]);

        $response = $this->actingAs($customer)
            ->getJson(route('customer.appointments.availability', [
                'service_id' => $service->id,
                'month' => $slotDate->format('Y-m'),
            ], false));

        $response->assertOk();

        $slots = collect($response->json('dates.'.$slotDate->toDateString()));

        $this->assertTrue($slots->contains('time', '14:00'));
        $this->assertTrue($slots->contains('time', '15:00'));
        $this->assertFalse($slots->contains('time', '16:00'));
        $this->assertNotNull($slots->firstWhere('time', '14:00')['ends_at'] ?? null);
    }

    public function test_customer_request_calendar_includes_time_preview_hooks(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get(route('customer.appointments.create', absolute: false))
            ->assertOk()
            ->assertSee('slotPreviewLimit: 2', false)
            ->assertSee('previewSlots', false)
            ->assertSee('moreSlotsLabel(day)', false)
            ->assertSee('slot.label', false)
            ->assertSee('eligibleStaff', false);
    }

    public function test_customer_availability_excludes_confirmed_overlap_and_honors_preferred_staff(): void
    {
        $customer = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customer)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $firstStaff = StaffProfile::factory()->create();
        $secondStaff = StaffProfile::factory()->create();
        $firstStaff->services()->attach($service);
        $secondStaff->services()->attach($service);

        $slotDate = now()->addWeek()->setTime(14, 0, 0);

        foreach ([$firstStaff, $secondStaff] as $staffProfile) {
            StaffWeeklySchedule::factory()->for($staffProfile)->create([
                'day_of_week' => $slotDate->dayOfWeek,
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
            ]);
        }

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->for($firstStaff, 'staffProfile')
            ->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'requested_start_at' => $slotDate,
                'scheduled_start_at' => $slotDate,
                'scheduled_end_at' => $slotDate->copy()->addHour(),
            ]);

        $generalResponse = $this->actingAs($customer)
            ->getJson(route('customer.appointments.availability', [
                'service_id' => $service->id,
                'month' => $slotDate->format('Y-m'),
            ], false));

        $preferredResponse = $this->actingAs($customer)
            ->getJson(route('customer.appointments.availability', [
                'service_id' => $service->id,
                'preferred_staff_profile_id' => $firstStaff->id,
                'month' => $slotDate->format('Y-m'),
            ], false));

        $this->assertTrue(collect($generalResponse->json('dates.'.$slotDate->toDateString()))->contains('time', '14:00'));
        $this->assertFalse(collect($preferredResponse->json('dates.'.$slotDate->toDateString()))->contains('time', '14:00'));
    }

    public function test_customer_booking_is_confirmed_automatically_and_overlap_is_blocked(): void
    {
        $staffUser = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->for($staffUser)->create();
        $customerUser = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staffProfile->services()->attach($service);

        $start = now()->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->actingAs($customerUser)
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'preferred_staff_profile_id' => $staffProfile->id,
                'scheduled_start_at' => $start->format('Y-m-d H:i:s'),
                'customer_notes' => 'Quiet room please.',
            ])
            ->assertRedirect();

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($staffProfile->id, $appointment->staff_profile_id);
        $this->assertSame($staffProfile->id, $appointment->preferred_staff_profile_id);
        $this->assertSame('Quiet room please.', $appointment->customer_notes);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_profile_id' => $staffProfile->id,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $secondCustomer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($secondCustomer)->create();
        $this->actingAs($secondCustomer)
            ->from(route('customer.appointments.create', absolute: false))
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'scheduled_start_at' => $start->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('customer.appointments.create', absolute: false))
            ->assertSessionHasErrors('scheduled_start_at');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_customer_cannot_request_unavailable_calendar_slot(): void
    {
        $customerUser = User::factory()->customer()->create();
        CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staffProfile = StaffProfile::factory()->create();
        $staffProfile->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);

        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '15:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->actingAs($customerUser)
            ->from(route('customer.appointments.create', absolute: false))
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'scheduled_start_at' => $start->format('Y-m-d H:i:s'),
                'customer_notes' => 'Trying an unavailable slot.',
            ])
            ->assertRedirect(route('customer.appointments.create', absolute: false))
            ->assertSessionHasErrors('scheduled_start_at');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_transaction_feedback_and_csv_report_workflow(): void
    {
        $admin = User::factory()->admin()->create();
        $customerUser = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['price' => 1250, 'is_active' => true]);
        $appointment = Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'quoted_amount' => 1250,
                'status' => Appointment::STATUS_COMPLETED,
                'requested_start_at' => now()->subDay()->setTime(10, 0),
                'scheduled_start_at' => now()->subDay()->setTime(10, 0),
                'scheduled_end_at' => now()->subDay()->setTime(11, 0),
                'completed_at' => now()->subDay()->setTime(11, 0),
            ]);

        $this->actingAs($admin)
            ->post(route('admin.transactions.store', absolute: false), [
                'customer_profile_id' => $customerProfile->id,
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'amount' => 1250,
                'payment_amount' => 1250,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
                'idempotency_key' => 'phase-ten-payment-1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'customer_profile_id' => $customerProfile->id,
            'appointment_id' => $appointment->id,
            'payment_status' => Transaction::PAYMENT_PAID,
        ]);

        $this->actingAs($customerUser)
            ->post(route('customer.feedback.store', absolute: false), [
                'appointment_id' => $appointment->id,
                'rating' => 5,
                'comment' => 'Excellent and relaxing visit.',
            ])
            ->assertRedirect(route('customer.feedback.index', absolute: false));

        $this->assertDatabaseHas('feedback', [
            'appointment_id' => $appointment->id,
            'sentiment_label' => Feedback::SENTIMENT_POSITIVE,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => 'transactions'], false))
            ->assertOk()
            ->assertDownload();
    }

    public function test_admin_can_generate_and_apply_rfm_promotion_suggestion(): void
    {
        $admin = User::factory()->admin()->create();
        $customerProfile = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['price' => 1500]);
        $segment = RfmSegment::factory()->create([
            'name' => 'Recent paid visitor',
            'recency_min_days' => null,
            'recency_max_days' => 30,
            'frequency_min' => 1,
            'frequency_max' => null,
            'monetary_min' => null,
            'monetary_max' => null,
            'is_active' => true,
        ]);
        PromotionRule::factory()->for($segment, 'rfmSegment')->create([
            'suggested_offer' => 'Free aromatherapy add-on',
            'is_active' => true,
        ]);

        $appointment = Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'quoted_amount' => 1500,
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => now()->subDays(5),
            ]);

        Transaction::factory()
            ->for($customerProfile)
            ->for($appointment)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'amount' => 1500,
                'amount_paid' => 1500,
                'payment_status' => Transaction::PAYMENT_PAID,
                'paid_at' => now()->subDays(5),
            ]);

        $this->actingAs($admin)
            ->post(route('admin.promotions.generate', absolute: false))
            ->assertRedirect(route('admin.promotions.index', absolute: false));

        $suggestion = PromotionSuggestion::query()->firstOrFail();

        $this->assertSame('Free aromatherapy add-on', $suggestion->suggested_offer);

        $this->actingAs($admin)
            ->patch(route('admin.promotions.update', $suggestion, false), [
                'status' => PromotionSuggestion::STATUS_APPLIED,
                'notes' => 'Customer contacted.',
            ])
            ->assertRedirect(route('admin.promotions.show', $suggestion, false));

        $this->assertDatabaseHas('promotion_suggestions', [
            'id' => $suggestion->id,
            'status' => PromotionSuggestion::STATUS_APPLIED,
            'reviewed_by' => $admin->id,
        ]);
    }
}
