<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Services\AppointmentAvailability;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $customerProfile = $request->user()->customerProfile;
        $customerProfileId = $customerProfile?->id ?? 0;

        $summary = [
            'upcoming' => Appointment::query()
                ->where('customer_profile_id', $customerProfileId)
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->where('scheduled_start_at', '>=', now())
                ->count(),
            'pending' => Appointment::query()
                ->where('customer_profile_id', $customerProfileId)
                ->where('status', Appointment::STATUS_PENDING)
                ->count(),
            'completed' => Appointment::query()
                ->where('customer_profile_id', $customerProfileId)
                ->where('status', Appointment::STATUS_COMPLETED)
                ->count(),
        ];

        return view('customer.appointments.index', [
            'summary' => $summary,
            'initialMonth' => now()->format('Y-m'),
        ]);
    }

    public function create(): View
    {
        return view('customer.appointments.create', [
            'services' => Service::query()->with('staffProfiles.user')->where('is_active', true)->orderBy('name')->get(),
            'staffProfiles' => StaffProfile::query()
                ->with(['user', 'services'])
                ->where('is_bookable', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->get()
                ->sortBy('user.name'),
        ]);
    }

    public function availability(Request $request, AppointmentAvailability $availability): array
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'preferred_staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $service = Service::query()
            ->where('is_active', true)
            ->findOrFail($data['service_id']);

        return $availability->month(
            $service,
            $data['month'],
            isset($data['preferred_staff_profile_id']) ? (int) $data['preferred_staff_profile_id'] : null,
        );
    }

    public function store(AppointmentRequest $request, AppointmentWorkflow $workflow, AppointmentAvailability $availability): RedirectResponse
    {
        $customerProfile = $request->user()->customerProfile;

        abort_unless($customerProfile, 403);

        $data = $request->validated();
        $service = Service::query()->findOrFail($data['service_id']);
        $requestedStart = Carbon::parse($data['requested_start_at']);
        $preferredStaffProfileId = ! empty($data['preferred_staff_profile_id']) ? (int) $data['preferred_staff_profile_id'] : null;

        if (! $availability->hasAvailableSlot($service, $requestedStart, $preferredStaffProfileId)) {
            throw ValidationException::withMessages([
                'requested_start_at' => __('Selected calendar slot is no longer available. Choose another date or time.'),
            ]);
        }

        if ($preferredStaffProfileId) {
            $preferredStaff = StaffProfile::query()->with('user')->findOrFail($preferredStaffProfileId);

            if (! $workflow->isStaffEligibleForService($preferredStaff, $service)) {
                return back()->withInput()->withErrors(['preferred_staff_profile_id' => __('Preferred staff must be eligible for the selected service.')]);
            }

        }

        $appointment = Appointment::query()->create([
            'appointment_number' => $workflow->nextAppointmentNumber(),
            'customer_profile_id' => $customerProfile->id,
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $preferredStaffProfileId,
            'requested_start_at' => $requestedStart,
            'status' => Appointment::STATUS_PENDING,
            'customer_notes' => filled($data['customer_notes'] ?? null) ? trim((string) $data['customer_notes']) : null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('customer.appointments.show', $appointment)
            ->with('status', 'appointment-requested');
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorizeOwnAppointment($request, $appointment);

        $appointment->load(['service', 'staffProfile.user', 'preferredStaffProfile.user', 'feedback', 'transactions']);

        return view('customer.appointments.show', [
            'appointment' => $appointment,
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOwnAppointment($request, $appointment);

        if ($appointment->status !== Appointment::STATUS_PENDING) {
            return back()->withErrors(['status' => __('Only pending requests can be cancelled online.')]);
        }

        $workflow->changeStatus($appointment, Appointment::STATUS_CANCELLED, $request->user()->id, __('Cancelled by customer'));

        return redirect()
            ->route('customer.appointments.show', $appointment)
            ->with('status', 'appointment-cancelled');
    }

    private function authorizeOwnAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless((int) $appointment->customer_profile_id === (int) ($request->user()->customerProfile?->id ?? 0), 403);
    }
}
