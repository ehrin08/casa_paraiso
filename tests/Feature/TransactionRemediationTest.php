<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\TransactionAdjustment;
use App\Models\User;
use App\Services\TransactionNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_payment_is_derived_from_the_quote_and_replayed_idempotently(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['price' => 1000]);
        $appointment = Appointment::factory()
            ->confirmed()
            ->for($customer)
            ->for($service)
            ->create(['quoted_amount' => 1000]);
        $payload = [
            'appointment_id' => $appointment->id,
            'amount' => 25,
            'payment_amount' => 300,
            'payment_method' => Transaction::METHOD_GCASH,
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'idempotency_key' => 'appointment-prepayment-1',
        ];

        $this->actingAs($admin)
            ->post(route('admin.transactions.store', absolute: false), $payload)
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.transactions.store', absolute: false), $payload)
            ->assertRedirect();

        $transaction = Transaction::query()->sole();
        $this->assertSame($appointment->id, $transaction->appointment_id);
        $this->assertSame('1000.00', $transaction->amount);
        $this->assertSame('300.00', $transaction->amount_paid);
        $this->assertSame(Transaction::PAYMENT_PARTIAL, $transaction->payment_status);
        $this->assertDatabaseCount('transaction_adjustments', 1);
    }

    public function test_payments_are_cumulative_and_overpayment_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->for($admin, 'recorder')->create([
            'appointment_id' => null,
            'amount' => 1000,
            'amount_paid' => 300,
            'payment_status' => Transaction::PAYMENT_PARTIAL,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.transactions.show', $transaction, false))
            ->post(route('admin.transactions.payments.store', $transaction, false), [
                'payment_amount' => 701,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
                'idempotency_key' => 'overpayment-attempt-1',
            ])
            ->assertRedirect(route('admin.transactions.show', $transaction, false))
            ->assertSessionHasErrors('payment_amount');

        $this->assertSame('300.00', $transaction->fresh()->amount_paid);

        $this->actingAs($admin)
            ->post(route('admin.transactions.payments.store', $transaction, false), [
                'payment_amount' => 700,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
                'idempotency_key' => 'final-payment-1',
            ])
            ->assertRedirect(route('admin.transactions.show', $transaction, false));

        $this->actingAs($admin)
            ->post(route('admin.transactions.payments.store', $transaction, false), [
                'payment_amount' => 700,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
                'idempotency_key' => 'final-payment-1',
            ])
            ->assertRedirect(route('admin.transactions.show', $transaction, false));

        $transaction->refresh();
        $this->assertSame('1000.00', $transaction->amount_paid);
        $this->assertSame('0.00', $transaction->open_balance);
        $this->assertSame(Transaction::PAYMENT_PAID, $transaction->payment_status);
        $this->assertSame(1, $transaction->adjustments()->count());
    }

    public function test_completion_reuses_an_existing_prepayment_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['price' => 1000]);
        $appointment = Appointment::factory()
            ->confirmed()
            ->for($customer)
            ->for($service)
            ->create([
                'quoted_amount' => 1000,
                'scheduled_start_at' => now()->subHour(),
                'scheduled_end_at' => now(),
            ]);

        $this->actingAs($admin)->post(route('admin.transactions.store', absolute: false), [
            'appointment_id' => $appointment->id,
            'amount' => 1000,
            'payment_amount' => 400,
            'payment_method' => Transaction::METHOD_GCASH,
            'paid_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'idempotency_key' => 'prepayment-before-completion-1',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.appointments.complete', $appointment, false), [
            'payment_amount' => 600,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'idempotency_key' => 'completion-payment-1',
        ])->assertRedirect();

        $transaction = Transaction::query()->sole();
        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);
        $this->assertSame('1000.00', $transaction->amount_paid);
        $this->assertSame(Transaction::PAYMENT_PAID, $transaction->payment_status);
        $this->assertSame(2, $transaction->adjustments()->count());
    }

    public function test_normal_payment_status_is_derived_and_cannot_be_selected_manually(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.transactions.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'amount' => 1200,
                'payment_status' => Transaction::PAYMENT_PAID,
                'idempotency_key' => 'unpaid-charge-1',
            ])
            ->assertRedirect();

        $transaction = Transaction::query()->sole();
        $this->assertSame('0.00', $transaction->amount_paid);
        $this->assertSame(Transaction::PAYMENT_UNPAID, $transaction->payment_status);
        $this->assertNull($transaction->payment_method);
        $this->assertNull($transaction->paid_at);
    }

    public function test_full_refund_and_void_zero_net_collected_and_append_audit_entries(): void
    {
        $admin = User::factory()->admin()->create();
        $paid = Transaction::factory()->for($admin, 'recorder')->create([
            'appointment_id' => null,
            'amount' => 900,
            'amount_paid' => 900,
            'payment_status' => Transaction::PAYMENT_PAID,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.transactions.refund', $paid, false), [
                'reason' => 'Customer cancellation refund',
                'idempotency_key' => 'refund-1',
            ])
            ->assertRedirect(route('admin.transactions.show', $paid, false));

        $paid->refresh();
        $this->assertSame(Transaction::PAYMENT_REFUNDED, $paid->payment_status);
        $this->assertSame('0.00', $paid->amount_paid);
        $this->assertDatabaseHas('transaction_adjustments', [
            'transaction_id' => $paid->id,
            'action' => TransactionAdjustment::ACTION_REFUND,
            'payment_delta' => '-900.00',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.transactions.show', $paid, false))
            ->patch(route('admin.transactions.void', $paid, false), [
                'reason' => 'Should not reuse refund token for another action',
                'idempotency_key' => 'refund-1',
            ])
            ->assertRedirect(route('admin.transactions.show', $paid, false))
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(Transaction::PAYMENT_REFUNDED, $paid->fresh()->payment_status);

        $unpaid = Transaction::factory()->for($admin, 'recorder')->create([
            'appointment_id' => null,
            'amount' => 500,
            'amount_paid' => 0,
            'payment_status' => Transaction::PAYMENT_UNPAID,
            'payment_method' => null,
            'paid_at' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.transactions.void', $unpaid, false), [
                'reason' => 'Entered against the wrong customer',
                'idempotency_key' => 'void-1',
            ])
            ->assertRedirect(route('admin.transactions.show', $unpaid, false));

        $this->assertSame(Transaction::PAYMENT_VOID, $unpaid->fresh()->payment_status);
    }

    public function test_charge_correction_requires_a_reason_and_cannot_drop_below_paid_total(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->for($admin, 'recorder')->create([
            'appointment_id' => null,
            'amount' => 1000,
            'amount_paid' => 600,
            'payment_status' => Transaction::PAYMENT_PARTIAL,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.transactions.edit', $transaction, false))
            ->patch(route('admin.transactions.update', $transaction, false), [
                'customer_profile_id' => $transaction->customer_profile_id,
                'service_id' => $transaction->service_id,
                'amount' => 500,
                'reason' => 'Correct quoted charge',
                'idempotency_key' => 'correction-1',
            ])
            ->assertRedirect(route('admin.transactions.edit', $transaction, false))
            ->assertSessionHasErrors('amount');

        $this->assertSame('1000.00', $transaction->fresh()->amount);

        $this->actingAs($admin)
            ->patch(route('admin.transactions.update', $transaction, false), [
                'customer_profile_id' => $transaction->customer_profile_id,
                'service_id' => $transaction->service_id,
                'amount' => 1100,
                'reason' => 'Correct the total after reviewing the service charge',
                'idempotency_key' => 'correction-2',
            ])
            ->assertRedirect(route('admin.transactions.show', $transaction, false));

        $adjustment = $transaction->adjustments()
            ->where('action', TransactionAdjustment::ACTION_CORRECTION)
            ->sole();

        $this->assertSame(Transaction::METHOD_CASH, $adjustment->payment_method);
    }

    public function test_transaction_number_collision_is_retried_during_admin_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        Transaction::factory()
            ->for($customer)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'appointment_id' => null,
                'transaction_number' => 'TRX-COLLISION',
            ]);

        $numbers = new class extends TransactionNumber
        {
            private int $calls = 0;

            public function next(): string
            {
                return ++$this->calls === 1 ? 'TRX-COLLISION' : 'TRX-RETRIED';
            }
        };

        $this->app->instance(TransactionNumber::class, $numbers);

        $this->actingAs($admin)
            ->post(route('admin.transactions.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'amount' => 1000,
                'payment_amount' => 1000,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
                'idempotency_key' => 'collision-create-1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', ['transaction_number' => 'TRX-RETRIED']);
        $this->assertDatabaseCount('transactions', 2);
    }
}
