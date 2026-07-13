<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsTransactionIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionActionRequest;
use App\Http\Requests\TransactionPaymentRequest;
use App\Http\Requests\TransactionRequest;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    use BuildsTransactionIndex;

    public function index(Request $request): View
    {
        $index = $this->transactionIndex($request);

        $createTransaction = new Transaction([
            'amount_paid' => 0,
            'payment_status' => Transaction::PAYMENT_UNPAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now(),
        ]);
        $totals = Transaction::query()
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as collected')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN payment_status IN (?, ?) THEN amount - amount_paid ELSE 0 END), 0) as open_balance',
                [Transaction::PAYMENT_UNPAID, Transaction::PAYMENT_PARTIAL],
            )
            ->first();

        return view('admin.transactions.index', [
            ...$index,
            'summary' => [
                'paid' => $totals?->collected ?? 0,
                'unpaid' => $totals?->open_balance ?? 0,
                'count' => Transaction::query()->count(),
            ],
            ...$this->formData($createTransaction),
        ]);
    }

    public function store(TransactionRequest $request, PaymentService $payments): RedirectResponse
    {
        $transaction = $payments->createOrApply($request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'transaction-created');
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['customerProfile.user', 'service', 'appointment', 'recorder', 'adjustments.actor']);

        return view('transactions.show', [
            'transaction' => $transaction,
            'indexRouteName' => 'admin.transactions.index',
            'editRouteName' => 'admin.transactions.edit',
            'showAccountingDetails' => true,
        ]);
    }

    public function edit(Transaction $transaction): View
    {
        return view('admin.shared.form-workspace', [
            'page' => [
                'eyebrow' => __('Admin transaction'),
                'title' => __('Edit transaction'),
                'description' => $transaction->transaction_number,
            ],
            'form' => [
                'partial' => 'admin.transactions.partials.form',
                'action' => route('admin.transactions.update', $transaction),
                'method' => 'PATCH',
                'submitLabel' => __('Update transaction'),
            ],
            ...$this->formData($transaction),
        ]);
    }

    public function update(TransactionRequest $request, Transaction $transaction, PaymentService $payments): RedirectResponse
    {
        $payments->correct($transaction, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'transaction-updated');
    }

    public function recordPayment(
        TransactionPaymentRequest $request,
        Transaction $transaction,
        PaymentService $payments,
    ): RedirectResponse {
        $payments->recordPayment($transaction, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'payment-recorded');
    }

    public function refund(
        TransactionActionRequest $request,
        Transaction $transaction,
        PaymentService $payments,
    ): RedirectResponse {
        $payments->refund($transaction, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'transaction-refunded');
    }

    public function void(
        TransactionActionRequest $request,
        Transaction $transaction,
        PaymentService $payments,
    ): RedirectResponse {
        $payments->void($transaction, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'transaction-voided');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Transaction $transaction): array
    {
        return [
            'transaction' => $transaction,
            'customers' => CustomerProfile::query()->with('user')->get()->sortBy('user.name'),
            'services' => Service::query()->orderBy('name')->get(),
            'appointments' => Appointment::query()
                ->with(['customerProfile.user', 'service'])
                ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED])
                ->where(function ($query) use ($transaction): void {
                    $query->whereDoesntHave('transaction');

                    if ($transaction->appointment_id) {
                        $query->orWhereKey($transaction->appointment_id);
                    }
                })
                ->latest('scheduled_start_at')
                ->get(),
        ];
    }
}
