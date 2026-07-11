<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Models\Transaction;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $staffProfile = $request->user()->staffProfile;

        return view('staff.appointments.index', [
            'staffProfile' => $staffProfile,
            'initialWeek' => now()->startOfWeek(Carbon::SUNDAY)->toDateString(),
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorizeOperationalAccess($request, $appointment);

        $appointment->load(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'transactions', 'feedback']);

        return view('staff.appointments.show', [
            'appointment' => $appointment,
            'transaction' => new Transaction([
                'appointment_id' => $appointment->id,
                'customer_profile_id' => $appointment->customer_profile_id,
                'service_id' => $appointment->service_id,
                'amount' => $appointment->service?->price,
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now(),
            ]),
            'appointments' => collect([$appointment]),
        ]);
    }

    public function update(AppointmentRequest $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOperationalAccess($request, $appointment);

        $data = $request->validated();
        $staffProfile = $request->user()->staffProfile;
        $status = $data['status'] ?? $appointment->status;

        if ($status === Appointment::STATUS_CONFIRMED) {
            $scheduledStart = ! empty($data['scheduled_start_at'])
                ? Carbon::parse($data['scheduled_start_at'])
                : Carbon::parse($appointment->requested_start_at);
            $scheduledEnd = $workflow->scheduledEnd($scheduledStart, $appointment->service);

            if (! $staffProfile || ! $workflow->isStaffAvailable($staffProfile, $appointment->service, $scheduledStart, $scheduledEnd, $appointment)) {
                return back()->withErrors(['scheduled_start_at' => __('Your schedule cannot accept this appointment time.')]);
            }

            $appointment->fill([
                'internal_notes' => $data['internal_notes'] ?? $appointment->internal_notes,
                'updated_by' => $request->user()->id,
            ]);

            $workflow->schedule(
                $appointment,
                $staffProfile,
                $appointment->service,
                $scheduledStart,
                $request->user()->id,
                $data['reason'] ?? null,
            );

            return redirect()
                ->route('staff.appointments.show', $appointment)
                ->with('status', 'appointment-updated');
        }

        if (in_array($status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED], true)
            && (int) $appointment->staff_profile_id !== (int) $staffProfile?->id) {
            abort(403);
        }

        $workflow->changeStatus($appointment, $status, $request->user()->id, $data['reason'] ?? null);

        return redirect()
            ->route('staff.appointments.show', $appointment)
            ->with('status', 'appointment-updated');
    }

    private function authorizeOperationalAccess(Request $request, Appointment $appointment): void
    {
        $staffProfile = $request->user()->staffProfile;
        $serviceIds = $staffProfile?->services()->pluck('services.id') ?? collect();

        $allowed = (int) $appointment->staff_profile_id === (int) ($staffProfile?->id ?? 0)
            || ($appointment->status === Appointment::STATUS_PENDING
                && $serviceIds->contains($appointment->service_id)
                && ($appointment->preferred_staff_profile_id === null
                    || (int) $appointment->preferred_staff_profile_id === (int) ($staffProfile?->id ?? 0)));

        abort_unless($allowed, 403);
    }
}
