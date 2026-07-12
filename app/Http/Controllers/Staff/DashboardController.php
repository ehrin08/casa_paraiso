<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = today();
        $staffProfile = $request->user()->staffProfile;
        $summary = [
            'assignedToday' => 0,
            'upcoming' => 0,
            'completedToday' => 0,
        ];

        $todayAppointments = collect();

        if ($staffProfile) {
            $summary['assignedToday'] = Appointment::query()
                ->where('staff_profile_id', $staffProfile->id)
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->whereDate('scheduled_start_at', $today)
                ->count();

            $summary['upcoming'] = Appointment::query()
                ->where('staff_profile_id', $staffProfile->id)
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->where('scheduled_start_at', '>', now())
                ->count();

            $summary['completedToday'] = Appointment::query()
                ->where('staff_profile_id', $staffProfile->id)
                ->where('status', Appointment::STATUS_COMPLETED)
                ->whereDate('completed_at', $today)
                ->count();

            $todayAppointments = Appointment::query()
                ->with(['customerProfile.user', 'service'])
                ->where('staff_profile_id', $staffProfile->id)
                ->whereDate('scheduled_start_at', $today)
                ->orderBy('scheduled_start_at')
                ->limit(6)
                ->get();
        }

        return view('staff.dashboard', [
            'summary' => $summary,
            'staffProfile' => $staffProfile,
            'todayAppointments' => $todayAppointments,
        ]);
    }
}
