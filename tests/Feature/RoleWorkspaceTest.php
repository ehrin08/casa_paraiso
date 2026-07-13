<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\TransactionAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_module_routes_are_available_only_to_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        StaffProfile::factory()->for($staff)->create();
        $customer = User::factory()->customer()->create();

        foreach ([
            '/admin/dashboard',
            '/admin/appointments',
            '/admin/customers',
            '/admin/staff',
            '/admin/services',
            '/admin/transactions',
            '/admin/promotions',
            '/admin/feedback',
            '/admin/reports',
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
            $this->actingAs($staff)->get($path)->assertForbidden();
            $this->actingAs($customer)->get($path)->assertForbidden();
        }
    }

    public function test_staff_module_routes_are_available_only_to_staff(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        StaffProfile::factory()->for($staff)->create();
        $customer = User::factory()->customer()->create();

        foreach ([
            '/staff/dashboard',
            '/staff/appointments',
            '/staff/customers',
            '/staff/transactions',
            '/staff/feedback',
        ] as $path) {
            $this->actingAs($staff)->get($path)->assertOk();
            $this->actingAs($admin)->get($path)->assertForbidden();
            $this->actingAs($customer)->get($path)->assertForbidden();
        }
    }

    public function test_customer_module_routes_are_available_only_to_customers(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        foreach ([
            '/customer/appointments',
            '/customer/appointments/create',
            '/customer/feedback',
        ] as $path) {
            $this->actingAs($customer)->get($path)->assertOk();
            $this->actingAs($admin)->get($path)->assertForbidden();
            $this->actingAs($staff)->get($path)->assertForbidden();
        }

    }

    public function test_customer_workspace_uses_sidebar_navigation(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get('/customer/appointments')
            ->assertOk()
            ->assertSee('data-page-loading', false)
            ->assertSee('data-turbo-track="reload"', false)
            ->assertSee('data-panel-host data-turbo="false"', false)
            ->assertDontSee('data-prefetch', false)
            ->assertSee('data-workspace-role="customer"', false)
            ->assertSee('data-role-navigation="customer"', false)
            ->assertSee('data-desktop-sidebar', false)
            ->assertSee('data-mobile-customer-navigation', false)
            ->assertSee('Customer lounge')
            ->assertSeeInOrder(['Appointments', 'Feedback', 'Profile'])
            ->assertSee(route('customer.appointments.index'), false)
            ->assertSee(route('customer.appointments.create'), false)
            ->assertSee(route('customer.feedback.index'), false)
            ->assertSee(route('profile.edit'), false)
            ->assertSee(route('logout'), false);
    }

    public function test_turbo_exclusions_are_explicit_for_panels_logout_and_exports(): void
    {
        $admin = User::factory()->admin()->create();
        $customerProfile = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->addDay(),
                'scheduled_end_at' => now()->addDay()->addHour(),
            ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('data-panel-link data-turbo="false"', false)
            ->assertSee('action="'.route('logout').'" class="mt-auto pt-6" data-turbo="false"', false);

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('href="'.route('admin.reports.export').'" class="casa-button-secondary" data-turbo="false"', false);
    }

    public function test_authenticated_landing_page_links_directly_to_role_home(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        foreach ([
            [$admin, route('admin.dashboard')],
            [$staff, route('staff.dashboard')],
            [$customer, route('customer.appointments.index')],
        ] as [$user, $homeUrl]) {
            $this->actingAs($user)
                ->get('/')
                ->assertOk()
                ->assertSee('href="'.$homeUrl.'"', false)
                ->assertDontSee('href="'.url('/dashboard').'"', false);
        }
    }

    public function test_admin_dashboard_shows_operational_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customerProfile = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'Hilot Massage']);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'appointment_number' => 'APT-HISTORICAL-PENDING',
                'status' => Appointment::STATUS_PENDING,
                'requested_start_at' => now()->addDay()->setTime(14, 0),
            ]);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->for(StaffProfile::factory()->for($staff), 'staffProfile')
            ->create([
                'appointment_number' => 'APT-UPCOMING',
                'status' => Appointment::STATUS_CONFIRMED,
                'requested_start_at' => now()->addDay()->setTime(15, 0),
                'scheduled_start_at' => now()->addDay()->setTime(15, 0),
                'scheduled_end_at' => now()->addDay()->setTime(16, 0),
            ]);

        $transaction = Transaction::factory()
            ->for($customerProfile)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'amount' => 1200,
                'amount_paid' => 1200,
                'payment_status' => Transaction::PAYMENT_PAID,
                'paid_at' => now(),
            ]);

        TransactionAdjustment::query()->create([
            'transaction_id' => $transaction->id,
            'action' => TransactionAdjustment::ACTION_PAYMENT,
            'previous_amount' => 1200,
            'new_amount' => 1200,
            'previous_amount_paid' => 0,
            'new_amount_paid' => 1200,
            'payment_delta' => 1200,
            'payment_method' => Transaction::METHOD_CASH,
            'occurred_at' => now(),
            'recorded_by' => $admin->id,
            'reason' => 'Dashboard revenue test payment.',
            'idempotency_key' => 'test:dashboard-revenue',
        ]);

        Feedback::factory()
            ->for($customerProfile)
            ->for($service)
            ->create(['submitted_at' => now()]);

        PromotionSuggestion::factory()
            ->for($customerProfile)
            ->create(['status' => PromotionSuggestion::STATUS_SUGGESTED]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('APT-UPCOMING')
            ->assertDontSee('APT-HISTORICAL-PENDING')
            ->assertSee('Hilot Massage')
            ->assertSee('PHP 1,200.00')
            ->assertSee('1 promotion review waiting');
    }

    public function test_customer_appointment_lounge_shows_own_appointment_summary(): void
    {
        $customer = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customer)->create();
        $service = Service::factory()->create(['name' => 'Tropical Wellness Massage']);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'appointment_number' => 'APT-CUSTOMER-PENDING',
                'status' => Appointment::STATUS_PENDING,
                'requested_start_at' => now()->addDays(2)->setTime(14, 0),
            ]);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->confirmed()
            ->create([
                'appointment_number' => 'APT-CUSTOMER-CONFIRMED',
                'scheduled_start_at' => now()->addDay()->setTime(15, 0),
                'scheduled_end_at' => now()->addDay()->setTime(16, 0),
            ]);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'appointment_number' => 'APT-CUSTOMER-COMPLETE',
                'status' => Appointment::STATUS_COMPLETED,
                'requested_start_at' => now()->subDay()->setTime(14, 0),
                'completed_at' => now()->subDay(),
            ]);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'appointment_number' => 'APT-CUSTOMER-CANCELLED',
                'status' => Appointment::STATUS_CANCELLED,
                'requested_start_at' => now()->subDays(2)->setTime(14, 0),
                'cancelled_at' => now()->subDays(2),
            ]);

        $this->actingAs($customer)
            ->get('/customer/appointments')
            ->assertOk()
            ->assertSee('data-customer-appointment-calendar', false)
            ->assertSeeInOrder(['Upcoming', '1'])
            ->assertSeeInOrder(['Completed', '1'])
            ->assertSeeInOrder(['Cancelled', '1']);

        $response = $this->actingAs($customer)->getJson(route('customer.appointments.calendar', [
            'month' => now()->format('Y-m'),
        ], false));

        $response->assertOk();
        $numbers = collect($response->json('events'))->pluck('appointment_number');
        $this->assertFalse($numbers->contains('APT-CUSTOMER-PENDING'));
        $this->assertTrue($numbers->contains('APT-CUSTOMER-CONFIRMED'));
        $this->assertTrue($numbers->contains('APT-CUSTOMER-COMPLETE'));
        $this->assertTrue($numbers->contains('APT-CUSTOMER-CANCELLED'));
        $this->assertTrue(collect($response->json('events'))->pluck('service_name')->contains('Tropical Wellness Massage'));
    }
}
