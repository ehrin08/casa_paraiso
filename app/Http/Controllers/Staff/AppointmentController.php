<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
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
        ]);
    }

    private function authorizeOperationalAccess(Request $request, Appointment $appointment): void
    {
        $staffProfile = $request->user()->staffProfile;
        $allowed = (int) $appointment->staff_profile_id === (int) ($staffProfile?->id ?? 0)
            && in_array($appointment->status, Appointment::ACTIVE_STATUSES, true);

        abort_unless($allowed, 403);
    }
}
