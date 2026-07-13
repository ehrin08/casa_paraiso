<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\BuildsTransactionIndex;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    use BuildsTransactionIndex;

    public function index(Request $request): View
    {
        $staffProfile = $request->user()->staffProfile;
        $index = $this->transactionIndex($request, function ($query) use ($staffProfile): void {
            $query->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery
                ->where('staff_profile_id', $staffProfile?->id ?? 0)
                ->whereIn('status', Appointment::ACTIVE_STATUSES));
        });

        return view('staff.transactions.index', [
            ...$index,
        ]);
    }

    public function show(Request $request, Transaction $transaction): View
    {
        $this->authorizeTransaction($request, $transaction);

        $transaction->load(['customerProfile.user', 'service', 'appointment', 'recorder']);

        return view('transactions.show', [
            'transaction' => $transaction,
            'indexRouteName' => 'staff.transactions.index',
            'editRouteName' => null,
            'showAccountingDetails' => false,
        ]);
    }

    private function authorizeTransaction(Request $request, Transaction $transaction): void
    {
        $staffProfileId = $request->user()->staffProfile?->id ?? 0;

        $allowed = (int) $transaction->appointment?->staff_profile_id === (int) $staffProfileId
            && in_array($transaction->appointment?->status, Appointment::ACTIVE_STATUSES, true);

        abort_unless($allowed, 403);
    }
}
