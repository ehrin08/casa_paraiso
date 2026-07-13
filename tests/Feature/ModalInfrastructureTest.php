<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ModalInfrastructureTest extends TestCase
{
    public function test_form_shell_centralizes_method_and_modal_metadata(): void
    {
        $html = Blade::render('<x-form-shell action="/records/1" method="PATCH" modal-name="record-edit"><button type="submit">Save</button></x-form-shell>');

        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('action="/records/1"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="_method" value="PATCH"', $html);
        $this->assertStringContainsString('name="_modal" value="record-edit"', $html);
    }

    public function test_filter_form_centralizes_list_query_state(): void
    {
        $html = Blade::render('<x-filter-form action="/records" sort="name" direction="desc" search="sample" search-placeholder="Search records" search-label="Search records"><select name="status"></select></x-filter-form>');

        $this->assertStringContainsString('method="GET"', $html);
        $this->assertStringContainsString('action="/records"', $html);
        $this->assertStringContainsString('name="sort" value="name"', $html);
        $this->assertStringContainsString('name="direction" value="desc"', $html);
        $this->assertStringContainsString('name="q" value="sample"', $html);
        $this->assertStringContainsString('name="status"', $html);
    }

    public function test_list_toolbar_only_clears_meaningful_query_changes_and_preserves_context(): void
    {
        request()->query->replace(['sort' => 'name', 'direction' => 'asc', 'q' => '']);
        $defaultHtml = Blade::render('<x-list-toolbar reset-url="/records" default-sort="name" default-direction="asc" />');

        $this->assertStringNotContainsString('Clear filters', $defaultHtml);

        request()->query->replace(['sort' => 'appointments', 'direction' => 'asc']);
        $sortedHtml = Blade::render('<x-list-toolbar reset-url="/records" default-sort="name" default-direction="asc" />');

        $this->assertStringContainsString('Clear filters', $sortedHtml);

        request()->query->replace(['type' => 'transactions', 'sort' => 'paid_at', 'direction' => 'desc']);
        $contextHtml = Blade::render("<x-list-toolbar reset-url=\"/reports?type=transactions\" default-sort=\"paid_at\" default-direction=\"desc\" :context-query-keys=\"['type']\" />");

        $this->assertStringNotContainsString('Clear filters', $contextHtml);

        request()->query->replace(['type' => 'transactions', 'sort' => 'paid_at', 'direction' => 'desc', 'payment_status' => 'partial']);
        $filteredReportHtml = Blade::render("<x-list-toolbar reset-url=\"/reports?type=transactions\" default-sort=\"paid_at\" default-direction=\"desc\" :context-query-keys=\"['type']\" />");

        $this->assertStringContainsString('Clear filters', $filteredReportHtml);
        $this->assertStringContainsString('href="/reports?type=transactions"', $filteredReportHtml);
    }

    public function test_modal_component_uses_the_shared_root_layer(): void
    {
        $html = Blade::render('<x-modal name="example-modal" focusable><button type="button">Example</button></x-modal>');

        $this->assertStringContainsString('x-teleport="body"', $html);
        $this->assertStringContainsString('data-modal-name="example-modal"', $html);
        $this->assertStringContainsString('x-data="casaModal({', $html);
        $this->assertStringContainsString('x-on:keydown.tab="handleTab($event)"', $html);
        $this->assertStringContainsString('x-cloak', $html);
        $this->assertStringContainsString('z-[100] isolate', $html);
        $this->assertStringContainsString('fixed inset-0 z-0 transform transition-all', $html);
        $this->assertStringContainsString('casa-card relative z-10', $html);
        $this->assertStringContainsString('backdrop-blur-sm', $html);
    }

    public function test_modal_asset_registers_central_state_and_scroll_locking(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("const modalStoreName = 'casaModal'", $script);
        $this->assertStringContainsString('Alpine.store(modalStoreName', $script);
        $this->assertStringContainsString('const modalStore = () => Alpine.store(modalStoreName);', $script);
        $this->assertStringNotContainsString('const modalStore = Alpine.store(', $script);
        $this->assertStringContainsString('window.casaModal', $script);
        $this->assertStringContainsString('syncBodyScrollLock', $script);
        $this->assertStringContainsString("window.addEventListener('open-modal'", $script);
        $this->assertStringContainsString("window.addEventListener('close-modal'", $script);
    }

    public function test_transaction_form_only_offers_appointment_linking_during_creation(): void
    {
        view()->share('errors', new ViewErrorBag);

        $render = fn (Transaction $transaction, string $method): string => view('admin.transactions.partials.form', [
            'transaction' => $transaction,
            'customers' => collect(),
            'services' => collect(),
            'appointments' => collect(),
            'action' => '/transactions',
            'method' => $method,
            'submitLabel' => 'Save transaction',
        ])->render();

        $createTransaction = (new Transaction)->forceFill([
            'amount' => null,
            'notes' => null,
        ]);
        $createTransaction->setRelation('appointment', null);

        $createHtml = $render($createTransaction, 'POST');

        $this->assertStringContainsString('name="appointment_id"', $createHtml);

        $standaloneTransaction = (new Transaction)->forceFill([
            'customer_profile_id' => 7,
            'service_id' => 3,
            'amount' => '1200.00',
            'notes' => null,
        ]);
        $standaloneTransaction->setRelation('appointment', null);

        $standaloneHtml = $render($standaloneTransaction, 'PATCH');

        $this->assertStringNotContainsString('name="appointment_id"', $standaloneHtml);
        $this->assertStringContainsString('name="customer_profile_id"', $standaloneHtml);
        $this->assertStringContainsString('name="service_id"', $standaloneHtml);

        $customer = (new CustomerProfile)->forceFill(['id' => 7]);
        $customer->setRelation('user', (new User)->forceFill(['name' => 'Linked Customer']));
        $service = (new Service)->forceFill(['id' => 3, 'name' => 'Linked Service']);
        $appointment = (new Appointment)->forceFill([
            'id' => 11,
            'appointment_number' => 'APT-011',
            'customer_profile_id' => 7,
            'service_id' => 3,
        ]);
        $appointment->setRelation('customerProfile', $customer);
        $appointment->setRelation('service', $service);

        $linkedTransaction = (new Transaction)->forceFill([
            'appointment_id' => 11,
            'customer_profile_id' => 7,
            'service_id' => 3,
            'amount' => '1200.00',
            'notes' => null,
        ]);
        $linkedTransaction->setRelation('appointment', $appointment);

        $linkedHtml = $render($linkedTransaction, 'PATCH');

        $this->assertSame(1, substr_count($linkedHtml, 'name="appointment_id"'));
        $this->assertMatchesRegularExpression('/<input[^>]*name="amount"[^>]*>/', $linkedHtml);
        preg_match('/<input[^>]*name="amount"[^>]*>/', $linkedHtml, $amountInput);
        $this->assertStringNotContainsString('readonly', $amountInput[0]);
    }
}
