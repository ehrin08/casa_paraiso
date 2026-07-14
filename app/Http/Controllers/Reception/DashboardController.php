<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('reception.dashboard', [
            'summary' => [
                'today' => Appointment::query()->whereDate('scheduled_start_at', today())->count(),
                'upcoming' => Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->where('scheduled_start_at', '>=', now())->count(),
                'customers' => CustomerProfile::query()->count(),
                'paymentsToday' => Transaction::query()->whereDate('paid_at', today())->sum('amount'),
            ],
            'todayAppointments' => Appointment::query()->with(['customerProfile.user', 'service', 'staffProfile.user'])->whereDate('scheduled_start_at', today())->orderBy('scheduled_start_at')->limit(8)->get(),
        ]);
    }
}
