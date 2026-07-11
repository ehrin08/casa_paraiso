<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $mode = in_array($request->query('mode'), ['bookings', 'availability'], true)
            ? (string) $request->query('mode')
            : 'bookings';

        return view('admin.appointments.index', [
            'mode' => $mode,
            'initialWeek' => now()->startOfWeek(Carbon::SUNDAY)->toDateString(),
            'summary' => [
                'pending' => Appointment::query()->where('status', Appointment::STATUS_PENDING)->count(),
                'confirmed' => Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->count(),
                'completed' => Appointment::query()->where('status', Appointment::STATUS_COMPLETED)->count(),
            ],
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'staffProfiles' => StaffProfile::query()
                ->with('user')
                ->where('is_bookable', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->get()
                ->sortBy('user.name'),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $request->validate([
            'customer_profile_id' => ['nullable', 'integer', 'exists:customer_profiles,id'],
            'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'requested_start_at' => ['nullable', 'date'],
            'scheduled_start_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,confirmed'],
        ]);
        $scheduledStart = ! empty($data['scheduled_start_at']) ? Carbon::parse($data['scheduled_start_at']) : null;
        $requestedStart = ! empty($data['requested_start_at'])
            ? Carbon::parse($data['requested_start_at'])
            : ($scheduledStart?->copy() ?? now()->addDay()->setTime(13, 0));

        return view('admin.appointments.create', $this->formData(new Appointment([
            'requested_start_at' => $requestedStart,
            'scheduled_start_at' => $scheduledStart,
            'status' => $data['status'] ?? ($scheduledStart && ! empty($data['staff_profile_id']) ? Appointment::STATUS_CONFIRMED : Appointment::STATUS_PENDING),
            'customer_profile_id' => $data['customer_profile_id'] ?? null,
            'staff_profile_id' => $data['staff_profile_id'] ?? null,
        ])));
    }

    public function store(AppointmentRequest $request, AppointmentWorkflow $workflow): RedirectResponse
    {
        $appointment = $this->persistAppointment(new Appointment, $request, $workflow);

        return redirect()
            ->route('admin.appointments.show', $appointment)
            ->with('status', 'appointment-created');
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load([
            'customerProfile.user',
            'service',
            'staffProfile.user',
            'preferredStaffProfile.user',
            'transactions.recorder',
            'feedback',
            'statusLogs.changedBy',
        ]);

        $formData = $this->formData($appointment);

        return view('admin.appointments.show', [
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
            'transactionAppointments' => collect([$appointment]),
            ...$formData,
        ]);
    }

    public function edit(Appointment $appointment): View
    {
        $appointment->load(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user']);

        return view('admin.appointments.edit', $this->formData($appointment));
    }

    public function update(AppointmentRequest $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $this->persistAppointment($appointment, $request, $workflow);

        return redirect()
            ->route('admin.appointments.show', $appointment)
            ->with('status', 'appointment-updated');
    }

    private function persistAppointment(Appointment $appointment, AppointmentRequest $request, AppointmentWorkflow $workflow): Appointment
    {
        $data = $request->validated();
        $service = Service::query()->findOrFail($data['service_id']);
        $status = $data['status'] ?? $appointment->status ?: Appointment::STATUS_PENDING;
        $requestedStart = Carbon::parse($data['requested_start_at']);
        $scheduledStart = ! empty($data['scheduled_start_at']) ? Carbon::parse($data['scheduled_start_at']) : null;
        $staffProfile = ! empty($data['staff_profile_id']) ? StaffProfile::query()->with('user')->findOrFail($data['staff_profile_id']) : null;
        $preferredStaffProfile = ! empty($data['preferred_staff_profile_id'])
            ? StaffProfile::query()->with(['user', 'services'])->findOrFail($data['preferred_staff_profile_id'])
            : null;

        if ($preferredStaffProfile && ! $workflow->isStaffEligibleForService($preferredStaffProfile, $service)) {
            throw ValidationException::withMessages([
                'preferred_staff_profile_id' => __('The preferred therapist must be active, bookable, and eligible for this service.'),
            ]);
        }

        if (! in_array($status, Appointment::STATUSES, true)) {
            $status = Appointment::STATUS_PENDING;
        }

        if ($status === Appointment::STATUS_PENDING) {
            $staffProfile = null;
            $scheduledStart = null;
            $scheduledEnd = null;
        } elseif ($status === Appointment::STATUS_CONFIRMED) {
            if (! $staffProfile || ! $scheduledStart) {
                throw ValidationException::withMessages([
                    'scheduled_start_at' => __('Confirmed appointments require staff and scheduled time.'),
                ]);
            }

        } else {
            $scheduledEnd = $scheduledStart ? $workflow->scheduledEnd($scheduledStart, $service) : null;
        }

        $appointment->fill([
            'appointment_number' => $appointment->appointment_number ?: $workflow->nextAppointmentNumber(),
            'customer_profile_id' => $data['customer_profile_id'] ?? $appointment->customer_profile_id,
            'service_id' => $service->id,
            'staff_profile_id' => $staffProfile?->id,
            'preferred_staff_profile_id' => $preferredStaffProfile?->id,
            'requested_start_at' => $requestedStart,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'customer_notes' => $data['customer_notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'created_by' => $appointment->created_by ?: $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        if (! $appointment->customer_profile_id) {
            throw ValidationException::withMessages([
                'customer_profile_id' => __('Select a customer for this appointment.'),
            ]);
        }

        if ($status === Appointment::STATUS_CONFIRMED) {
            return $workflow->schedule(
                $appointment,
                $staffProfile,
                $service,
                $scheduledStart,
                $request->user()->id,
                $data['reason'] ?? null,
            );
        }

        $appointment->save();
        $workflow->changeStatus($appointment, $status, $request->user()->id, $data['reason'] ?? null);

        return $appointment;
    }

    public function availableTherapists(Request $request, AppointmentWorkflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
        ]);
        $service = Service::query()->where('is_active', true)->findOrFail($data['service_id']);
        $start = Carbon::parse($data['starts_at']);
        $appointment = ! empty($data['appointment_id']) ? Appointment::query()->findOrFail($data['appointment_id']) : null;

        $therapists = StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->where('is_bookable', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->whereHas('services', fn ($query) => $query->whereKey($service->id))
            ->get()
            ->filter(fn (StaffProfile $staff) => $workflow->isStaffAvailable($staff, $service, $start, null, $appointment))
            ->sortBy('user.name')
            ->values()
            ->map(fn (StaffProfile $staff) => [
                'id' => $staff->id,
                'name' => $staff->user?->name,
                'specialization' => $staff->specialization,
            ]);

        return response()->json(['therapists' => $therapists]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Appointment $appointment): array
    {
        return [
            'appointment' => $appointment,
            'customers' => CustomerProfile::query()->with('user')->get()->sortBy('user.name'),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'staffProfiles' => StaffProfile::query()->with(['user', 'services'])->where('is_bookable', true)->get()->sortBy('user.name'),
        ];
    }
}
