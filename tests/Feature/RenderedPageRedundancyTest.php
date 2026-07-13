<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffWeeklySchedule;
use App\Models\Transaction;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class RenderedPageRedundancyTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = (new User)->forceFill([
            'id' => 9001,
            'name' => 'Rendered Page Admin',
            'email' => 'rendered-page-admin@example.test',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $this->admin->exists = true;

        Auth::setUser($this->admin);
        view()->share('errors', new ViewErrorBag);
        request()->query->replace([]);
    }

    public function test_representative_final_views_have_valid_form_relationships_and_contextual_payment_name(): void
    {
        $emptyServices = $this->renderServiceList([]);
        $populatedServices = $this->renderServiceList([$this->service()]);
        [$transactionHtml, $transactionNumber] = $this->renderTransactionDetail();

        foreach ([
            'empty services' => $emptyServices,
            'populated services' => $populatedServices,
            'transaction detail' => $transactionHtml,
        ] as $page => $html) {
            $this->assertUniqueIdsAndValidLabelTargets($html, $page);
        }

        $xpath = $this->xpath($transactionHtml);
        $this->assertGreaterThan(0, $xpath->query('//label[@for]')->length, 'The transaction view must exercise explicit label relationships.');
        $expectedName = "Record payment for {$transactionNumber}";
        $matchingDialogs = 0;

        foreach ($xpath->query('//*[@role="dialog"]') as $dialog) {
            if ($dialog instanceof DOMElement && $dialog->getAttribute('aria-label') === $expectedName) {
                $matchingDialogs++;
            }
        }

        $this->assertSame(1, $matchingDialogs, 'The payment modal must have one contextual accessible name.');
    }

    public function test_service_list_renders_one_page_primary_cta_in_empty_and_populated_states(): void
    {
        foreach ([
            'empty' => $this->renderServiceList([]),
            'populated' => $this->renderServiceList([$this->service()]),
        ] as $state => $html) {
            $xpath = $this->xpath($html);
            $primaryCtas = $xpath->query(
                '//a[contains(concat(" ", normalize-space(@class), " "), " casa-button-primary ") and not(ancestor::template)]'
                .' | //button[contains(concat(" ", normalize-space(@class), " "), " casa-button-primary ") and not(ancestor::template)]',
            );

            $this->assertSame(1, $primaryCtas->length, "The {$state} service list must render exactly one page primary CTA.");
            $this->assertSame('Add service', trim($primaryCtas->item(0)->textContent));
        }
    }

    public function test_prefixed_operational_forms_and_shared_components_have_valid_id_relationships(): void
    {
        $customer = $this->customerProfile();
        $service = $this->service();
        $staff = $this->staffProfile();
        $appointment = $this->appointment($customer, $service, $staff);
        $linkedTransaction = $this->transaction($customer, $service, $appointment, 92, 'TRX-LINKED-001');
        $appointment->setRelation('transaction', $linkedTransaction);
        $standaloneTransaction = $this->transaction($customer, $service, null, 93, 'TRX-STANDALONE-001');
        $feedbackAppointment = $this->appointment($customer, $service, $staff, Appointment::STATUS_COMPLETED, 84);

        $createAppointment = (new Appointment)->forceFill([
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
        $createAppointment->setRelation('transaction', null);

        $weeklySchedule = (new StaffWeeklySchedule)->forceFill([
            'id' => 85,
            'staff_profile_id' => $staff->id,
            'day_of_week' => StaffWeeklySchedule::MONDAY,
            'start_time' => '13:00:00',
            'end_time' => '00:00:00',
            'ends_next_day' => true,
            'is_available' => true,
        ]);
        $weeklySchedule->exists = true;

        $scheduleException = (new StaffScheduleException)->forceFill([
            'id' => 86,
            'staff_profile_id' => $staff->id,
            'exception_date' => '2026-07-20',
            'exception_type' => StaffScheduleException::TYPE_AVAILABLE,
            'start_time' => '15:00:00',
            'end_time' => '18:00:00',
            'ends_next_day' => false,
            'reason' => 'Extended availability',
        ]);
        $scheduleException->exists = true;

        $fixtures = [
            'appointment create' => [
                view('admin.appointments.partials.form', [
                    'appointment' => $createAppointment,
                    'customers' => collect([$customer]),
                    'services' => collect([$service]),
                    'staffProfiles' => collect([$staff]),
                    'action' => route('admin.appointments.store'),
                    'method' => 'POST',
                    'submitLabel' => 'Create appointment',
                    'modalName' => 'fixture-appointment-create',
                    'fixedStatus' => Appointment::STATUS_CONFIRMED,
                ])->render(),
                'fixture-appointment-create-',
            ],
            'appointment completion' => [
                view('admin.appointments.partials.completion-form', [
                    'appointment' => $appointment,
                    'modalName' => 'fixture-appointment-completion',
                ])->render(),
                'fixture-appointment-completion-',
            ],
            'weekly schedule' => [
                view('admin.staff.weekly-schedules.partials.form', [
                    'staffProfile' => $staff,
                    'weeklySchedule' => $weeklySchedule,
                    'action' => route('admin.staff.weekly-schedules.update', [$staff, $weeklySchedule]),
                    'method' => 'PATCH',
                    'submitLabel' => 'Update shift',
                    'modalName' => 'fixture-weekly-schedule',
                ])->render(),
                'fixture-weekly-schedule-',
            ],
            'schedule exception' => [
                view('admin.staff.schedule-exceptions.partials.form', [
                    'staffProfile' => $staff,
                    'scheduleException' => $scheduleException,
                    'action' => route('admin.staff.schedule-exceptions.update', [$staff, $scheduleException]),
                    'method' => 'PATCH',
                    'submitLabel' => 'Update exception',
                    'modalName' => 'fixture-schedule-exception',
                ])->render(),
                'fixture-schedule-exception-',
            ],
            'standalone transaction' => [
                $this->renderTransactionForm($standaloneTransaction, $customer, $service, $appointment, 'fixture-transaction-standalone'),
                'fixture-transaction-standalone-',
            ],
            'linked transaction' => [
                $this->renderTransactionForm($linkedTransaction, $customer, $service, $appointment, 'fixture-transaction-linked'),
                'fixture-transaction-linked-',
            ],
            'customer feedback' => [
                view('customer.feedback.partials.form', [
                    'appointments' => collect([$feedbackAppointment]),
                    'selectedAppointmentId' => $feedbackAppointment->id,
                    'modalName' => 'fixture-customer-feedback',
                ])->render(),
                'fixture-customer-feedback-',
            ],
            'customer select' => [
                Blade::render(
                    '<x-customer-select :customers="$customers" :selected-id="$selectedId" id-prefix="fixture-customer-select-" />',
                    ['customers' => collect([$customer]), 'selectedId' => $customer->id],
                ),
                'fixture-customer-select-',
            ],
            'schedule window' => [
                Blade::render(
                    '<x-schedule-window-fields :start-time="$startTime" :end-time="$endTime" :ends-next-day="true" id-prefix="fixture-schedule-window-" />',
                    ['startTime' => '13:00:00', 'endTime' => '00:00:00'],
                ),
                'fixture-schedule-window-',
            ],
        ];

        foreach ($fixtures as $fixture => [$html, $prefix]) {
            $this->assertPrefixedFormRelationships($html, $fixture, $prefix);
        }

        $this->assertUniqueIdsAndValidLabelTargets(
            collect($fixtures)->pluck(0)->implode("\n"),
            'combined prefixed form fixtures',
        );
    }

    /**
     * @param  array<int, Service>  $services
     */
    private function renderServiceList(array $services): string
    {
        $items = collect($services);
        $paginator = new LengthAwarePaginator(
            $items,
            $items->count(),
            15,
            1,
            ['path' => route('admin.services.index')],
        );

        return view('admin.services.index', [
            'services' => $paginator,
            'activeCount' => $items->where('is_active', true)->count(),
            'inactiveCount' => $items->where('is_active', false)->count(),
            'sort' => 'status',
            'direction' => 'desc',
            'search' => '',
            'status' => '',
        ])->render();
    }

    private function service(): Service
    {
        $service = (new Service)->forceFill([
            'id' => 71,
            'name' => 'Hilot Massage',
            'slug' => 'hilot-massage',
            'duration_minutes' => 60,
            'price' => '1200.00',
            'is_active' => true,
            'staff_profiles_count' => 2,
            'appointments_count' => 5,
        ]);
        $service->exists = true;

        return $service;
    }

    private function customerProfile(): CustomerProfile
    {
        $customerUser = (new User)->forceFill([
            'id' => 9002,
            'name' => 'Context Customer',
            'email' => 'context-customer@example.test',
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        $customerUser->exists = true;

        $customer = (new CustomerProfile)->forceFill([
            'id' => 81,
            'user_id' => $customerUser->id,
            'customer_code' => 'CP-RENDER-001',
        ]);
        $customer->exists = true;
        $customer->setRelation('user', $customerUser);

        return $customer;
    }

    private function staffProfile(): StaffProfile
    {
        $staffUser = (new User)->forceFill([
            'id' => 9003,
            'name' => 'Fixture Therapist',
            'email' => 'fixture-therapist@example.test',
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        $staffUser->exists = true;

        $staff = (new StaffProfile)->forceFill([
            'id' => 82,
            'user_id' => $staffUser->id,
            'position' => 'Massage therapist',
            'is_bookable' => true,
        ]);
        $staff->exists = true;
        $staff->setRelation('user', $staffUser);

        return $staff;
    }

    private function appointment(
        CustomerProfile $customer,
        Service $service,
        StaffProfile $staff,
        string $status = Appointment::STATUS_CONFIRMED,
        int $id = 83,
    ): Appointment {
        $appointment = (new Appointment)->forceFill([
            'id' => $id,
            'appointment_number' => 'APT-RENDER-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'staff_profile_id' => $staff->id,
            'preferred_staff_profile_id' => $staff->id,
            'requested_start_at' => '2026-07-20 13:00:00',
            'scheduled_start_at' => '2026-07-20 13:00:00',
            'scheduled_end_at' => '2026-07-20 14:00:00',
            'status' => $status,
            'quoted_amount' => '1200.00',
            'customer_notes' => 'Quiet room requested.',
            'internal_notes' => 'Rendered fixture.',
        ]);
        $appointment->exists = true;
        $appointment->setRelation('customerProfile', $customer);
        $appointment->setRelation('service', $service);
        $appointment->setRelation('staffProfile', $staff);
        $appointment->setRelation('preferredStaffProfile', $staff);
        $appointment->setRelation('transaction', null);

        return $appointment;
    }

    private function transaction(
        CustomerProfile $customer,
        Service $service,
        ?Appointment $appointment,
        int $id,
        string $number,
    ): Transaction {
        $transaction = (new Transaction)->forceFill([
            'id' => $id,
            'transaction_number' => $number,
            'appointment_id' => $appointment?->id,
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'amount' => '1200.00',
            'amount_paid' => '400.00',
            'payment_status' => Transaction::PAYMENT_PARTIAL,
            'payment_method' => Transaction::METHOD_CASH,
            'recorded_by' => $this->admin->id,
            'notes' => 'Rendered without a database.',
        ]);
        $transaction->exists = true;
        $transaction->setRelation('appointment', $appointment);
        $transaction->setRelation('customerProfile', $customer);
        $transaction->setRelation('service', $service);
        $transaction->setRelation('recorder', $this->admin);
        $transaction->setRelation('adjustments', collect());

        return $transaction;
    }

    private function renderTransactionForm(
        Transaction $transaction,
        CustomerProfile $customer,
        Service $service,
        Appointment $appointment,
        string $modalName,
    ): string {
        return view('admin.transactions.partials.form', [
            'transaction' => $transaction,
            'customers' => collect([$customer]),
            'services' => collect([$service]),
            'appointments' => collect([$appointment]),
            'action' => route('admin.transactions.update', $transaction),
            'method' => 'PATCH',
            'submitLabel' => 'Update transaction',
            'modalName' => $modalName,
        ])->render();
    }

    /**
     * @return array{string, string}
     */
    private function renderTransactionDetail(): array
    {
        $customer = $this->customerProfile();
        $service = $this->service();
        $transactionNumber = 'TRX-RENDER-001';
        $transaction = $this->transaction($customer, $service, null, 91, $transactionNumber);

        return [
            view('transactions.show', [
                'transaction' => $transaction,
                'indexRouteName' => 'admin.transactions.index',
                'editRouteName' => 'admin.transactions.edit',
                'showAccountingDetails' => true,
            ])->render(),
            $transactionNumber,
        ];
    }

    private function assertPrefixedFormRelationships(string $html, string $fixture, string $prefix): void
    {
        $this->assertUniqueIdsAndValidLabelTargets($html, $fixture);

        $xpath = $this->xpath($html);
        $labels = $xpath->query('//label[@for]');
        $this->assertGreaterThan(0, $labels->length, "The {$fixture} fixture must exercise explicit label relationships.");

        foreach ($xpath->query('//*[@id]') as $element) {
            if ($element instanceof DOMElement) {
                $this->assertStringStartsWith($prefix, $element->getAttribute('id'), "The {$fixture} control ID must use its fixture prefix.");
            }
        }

        foreach ($labels as $label) {
            if ($label instanceof DOMElement) {
                $this->assertStringStartsWith($prefix, $label->getAttribute('for'), "The {$fixture} label target must use its fixture prefix.");
            }
        }
    }

    private function assertUniqueIdsAndValidLabelTargets(string $html, string $page): void
    {
        $xpath = $this->xpath($html);
        $ids = [];

        foreach ($xpath->query('//*[@id]') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $id = trim($element->getAttribute('id'));
            $this->assertNotSame('', $id, "The {$page} view contains an empty ID.");
            $this->assertArrayNotHasKey($id, $ids, "The {$page} view contains duplicate ID '{$id}'.");
            $ids[$id] = true;
        }

        foreach ($xpath->query('//label[@for]') as $label) {
            if (! $label instanceof DOMElement) {
                continue;
            }

            $target = trim($label->getAttribute('for'));
            $this->assertArrayHasKey(
                $target,
                $ids,
                "The {$page} label target '{$target}' does not exist. Rendered IDs: ".implode(', ', array_keys($ids)),
            );
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
