<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractiveListControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_appointment_calendar_filters_the_event_feed(): void
    {
        $admin = User::factory()->admin()->create();
        $customerA = CustomerProfile::factory()->create();
        $customerB = CustomerProfile::factory()->create();
        $serviceA = Service::factory()->create(['name' => 'Hilot Therapy']);
        $serviceB = Service::factory()->create(['name' => 'Facial Care']);

        Appointment::factory()
            ->for($customerA)
            ->for($serviceA)
            ->create([
                'appointment_number' => 'APT-200',
                'status' => Appointment::STATUS_CONFIRMED,
                'requested_start_at' => now()->addDays(2)->setTime(10, 0),
                'scheduled_start_at' => now()->addDays(2)->setTime(10, 0),
                'scheduled_end_at' => now()->addDays(2)->setTime(11, 0),
            ]);

        Appointment::factory()
            ->for($customerB)
            ->for($serviceB)
            ->create([
                'appointment_number' => 'APT-100',
                'status' => Appointment::STATUS_CONFIRMED,
                'requested_start_at' => now()->addDays(3)->setTime(11, 0),
                'scheduled_start_at' => now()->addDays(3)->setTime(11, 0),
                'scheduled_end_at' => now()->addDays(3)->setTime(12, 0),
            ]);

        $this->actingAs($admin)
            ->get(route('admin.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee('data-operational-calendar', false)
            ->assertDontSee('casa-table-wrap', false);

        $weekStart = now()->startOfDay();
        $response = $this->actingAs($admin)
            ->getJson(route('admin.appointments.calendar', [
                'mode' => 'bookings',
                'start' => $weekStart->toDateString(),
                'end' => $weekStart->copy()->addDays(7)->toDateString(),
                'status' => Appointment::STATUS_CONFIRMED,
                'service_id' => $serviceA->id,
            ], false));

        $response->assertOk()->assertJsonPath('mode', 'bookings');
        $numbers = collect($response->json('events'))->pluck('appointment_number');
        $this->assertTrue($numbers->contains('APT-200'));
        $this->assertFalse($numbers->contains('APT-100'));
    }

    public function test_admin_transaction_list_filters_searches_and_sorts(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'Stone Massage']);

        Transaction::factory()
            ->for($customer)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'transaction_number' => 'TRX-PAID',
                'payment_status' => Transaction::PAYMENT_PAID,
                'amount' => 2000,
                'amount_paid' => 2000,
            ]);

        Transaction::factory()
            ->for($customer)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'transaction_number' => 'TRX-UNPAID',
                'payment_status' => Transaction::PAYMENT_UNPAID,
                'amount' => 500,
                'amount_paid' => 0,
            ]);

        $this->actingAs($admin)
            ->get(route('admin.transactions.index', [
                'q' => 'TRX-PAID',
                'payment_status' => Transaction::PAYMENT_PAID,
                'sort' => 'amount',
                'direction' => 'desc',
            ], false))
            ->assertOk()
            ->assertSee('TRX-PAID')
            ->assertDontSee('TRX-UNPAID')
            ->assertSee('sort=amount', false);
    }

    public function test_admin_service_list_has_search_sort_and_confirmation_modal(): void
    {
        $admin = User::factory()->admin()->create();

        Service::factory()->create([
            'name' => 'Inactive Spa Package',
            'slug' => 'inactive-spa-package',
            'price' => 2500,
            'is_active' => false,
        ]);

        Service::factory()->create([
            'name' => 'Active Massage',
            'slug' => 'active-massage',
            'price' => 1000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.services.index', [
                'q' => 'Inactive',
                'status' => 'inactive',
                'sort' => 'price',
                'direction' => 'desc',
            ], false))
            ->assertOk()
            ->assertSee('Inactive Spa Package')
            ->assertDontSee('Active Massage')
            ->assertSee('Activate service?')
            ->assertSee('sort=price', false);
    }

    public function test_staff_appointment_workspace_uses_the_operational_calendar(): void
    {
        $staffUser = User::factory()->staff()->create();
        StaffProfile::factory()->for($staffUser)->create();

        $this->actingAs($staffUser)
            ->get(route('staff.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee('data-operational-calendar', false)
            ->assertDontSee('casa-table-wrap', false);
    }

    public function test_customer_appointment_calendar_replaces_pagination_controls(): void
    {
        $customerUser = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['name' => 'Wellness Massage']);

        Appointment::factory()
            ->count(3)
            ->for($customerProfile)
            ->for($service)
            ->sequence(fn ($sequence) => [
                'appointment_number' => 'APT-CUSTOMER-'.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
                'status' => Appointment::STATUS_COMPLETED,
                'requested_start_at' => now()->startOfMonth()->addDays($sequence->index + 1)->setTime(14, 0),
            ])
            ->create();

        $this->actingAs($customerUser)
            ->get(route('customer.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee('data-customer-appointment-calendar', false)
            ->assertDontSee('page=2', false);

        $response = $this->actingAs($customerUser)
            ->getJson(route('customer.appointments.calendar', [
                'month' => now()->format('Y-m'),
                'status' => Appointment::STATUS_COMPLETED,
            ], false));

        $response->assertOk()->assertJsonCount(3, 'events');
    }

    public function test_global_toast_renders_session_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withSession(['status' => 'service-activated'])
            ->get(route('admin.services.index', absolute: false))
            ->assertOk()
            ->assertSee('Service activated.');
    }
}
