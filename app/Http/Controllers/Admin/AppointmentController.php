<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAppointmentCompletionRequest;
use App\Http\Requests\AdminAppointmentOutcomeRequest;
use App\Http\Requests\AdminAppointmentStoreRequest;
use App\Http\Requests\AdminAppointmentUpdateRequest;
use App\Http\Requests\AdminCalendarAppointmentStoreRequest;
use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Services\AppointmentCompletion;
use App\Services\AppointmentManagement;
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

        $calendarAppointment = new Appointment([
            'requested_start_at' => now()->addDay()->setTime(13, 0),
            'scheduled_start_at' => now()->addDay()->setTime(13, 0),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        return view('admin.appointments.index', [
            'mode' => $mode,
            'initialWeek' => now()->startOfWeek(Carbon::SUNDAY)->toDateString(),
            'summary' => [
                'confirmed' => Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->count(),
                'completed' => Appointment::query()->where('status', Appointment::STATUS_COMPLETED)->count(),
                'cancelled' => Appointment::query()->where('status', Appointment::STATUS_CANCELLED)->count(),
            ],
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'staffProfiles' => StaffProfile::query()
                ->with('user')
                ->where('is_bookable', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->get()
                ->sortBy('user.name'),
            'customers' => CustomerProfile::query()->with('user')->get()->sortBy('user.name')->values(),
            'calendarAppointment' => $calendarAppointment,
            'serviceQueue' => Appointment::query()
                ->with(['customerProfile.user', 'service', 'staffProfile.user'])
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->orderBy('scheduled_start_at')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $request->validate([
            'customer_profile_id' => ['nullable', 'integer', 'exists:customer_profiles,id'],
            'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'requested_start_at' => ['nullable', 'date'],
            'scheduled_start_at' => ['nullable', 'date'],
        ]);
        $scheduledStart = ! empty($data['scheduled_start_at']) ? Carbon::parse($data['scheduled_start_at']) : null;
        $requestedStart = ! empty($data['requested_start_at'])
            ? Carbon::parse($data['requested_start_at'])
            : ($scheduledStart?->copy() ?? now()->addDay()->setTime(13, 0));

        return view('admin.appointments.create', $this->formData(new Appointment([
            'requested_start_at' => $requestedStart,
            'scheduled_start_at' => $scheduledStart,
            'status' => Appointment::STATUS_CONFIRMED,
            'customer_profile_id' => $data['customer_profile_id'] ?? null,
            'staff_profile_id' => $data['staff_profile_id'] ?? null,
        ])));
    }

    public function store(AdminAppointmentStoreRequest $request, AppointmentManagement $management): RedirectResponse
    {
        $appointment = $management->persist(new Appointment, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.appointments.show', $appointment)
            ->with('status', 'appointment-created');
    }

    public function storeFromCalendar(AdminCalendarAppointmentStoreRequest $request, AppointmentManagement $management): RedirectResponse
    {
        $management->persist(new Appointment, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.appointments.index')
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
                'payment_method' => ApplicationSetting::current()->default_payment_method,
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

    public function update(AdminAppointmentUpdateRequest $request, Appointment $appointment, AppointmentManagement $management): RedirectResponse
    {
        $management->persist($appointment, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.appointments.show', $appointment)
            ->with('status', 'appointment-updated');
    }

    public function complete(
        AdminAppointmentCompletionRequest $request,
        Appointment $appointment,
        AppointmentCompletion $completion,
    ): RedirectResponse {
        $transaction = $completion->complete($appointment, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'appointment-completed');
    }

    public function outcome(
        AdminAppointmentOutcomeRequest $request,
        Appointment $appointment,
        AppointmentWorkflow $workflow,
    ): RedirectResponse {
        $data = $request->validated();

        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['status' => __('Only a confirmed appointment can receive this outcome.')]);
        }

        if ($data['status'] === Appointment::STATUS_NO_SHOW
            && (! $appointment->scheduled_start_at || $appointment->scheduled_start_at->isFuture())) {
            throw ValidationException::withMessages(['status' => __('A no-show can be recorded once the scheduled start time is reached.')]);
        }

        $workflow->changeStatus($appointment, $data['status'], $request->user()->id, $data['reason'] ?? null);

        return redirect()->route('admin.appointments.index')->with('status', 'appointment-updated');
    }

    public function availableTherapists(Request $request, AppointmentWorkflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
        ]);
        $appointment = ! empty($data['appointment_id']) ? Appointment::query()->findOrFail($data['appointment_id']) : null;
        $service = Service::query()->findOrFail($data['service_id']);

        if (! $service->is_active && (int) $appointment?->service_id !== (int) $service->id) {
            throw ValidationException::withMessages([
                'service_id' => __('Select an active service.'),
            ]);
        }

        $start = Carbon::parse($data['starts_at']);
        $workflow->assertBookableStart($start, $service, 'starts_at');

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
        $customers = CustomerProfile::query()->with('user')->get();
        $services = Service::query()->where('is_active', true)->orderBy('name')->get();
        $staffProfiles = StaffProfile::query()
            ->with(['user', 'services'])
            ->where('is_bookable', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->get();

        if ($appointment->customerProfile && ! $customers->contains('id', $appointment->customerProfile->id)) {
            $customers->push($appointment->customerProfile);
        }

        if ($appointment->service && ! $services->contains('id', $appointment->service->id)) {
            $services->push($appointment->service);
        }

        foreach ([$appointment->staffProfile, $appointment->preferredStaffProfile] as $historicalStaff) {
            if ($historicalStaff && ! $staffProfiles->contains('id', $historicalStaff->id)) {
                $historicalStaff->loadMissing(['user', 'services']);
                $staffProfiles->push($historicalStaff);
            }
        }

        return [
            'appointment' => $appointment,
            'customers' => $customers->sortBy('user.name')->values(),
            'services' => $services->sortBy('name')->values(),
            'staffProfiles' => $staffProfiles->sortBy('user.name')->values(),
        ];
    }
}
