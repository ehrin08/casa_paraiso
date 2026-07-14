<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();

        $summary = [
            'todayAppointments' => Appointment::query()
                ->whereDate('scheduled_start_at', $today)
                ->count(),
            'upcomingAppointments' => Appointment::query()
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->where('scheduled_start_at', '>=', now())
                ->count(),
            'todayRevenue' => Transaction::query()
                ->where('payment_status', Transaction::PAYMENT_PAID)
                ->whereDate('paid_at', $today)
                ->sum('amount'),
            'newFeedback' => Feedback::query()
                ->whereDate('submitted_at', $today)
                ->count(),
            'promotionReviews' => PromotionSuggestion::query()
                ->where('status', PromotionSuggestion::STATUS_SUGGESTED)
                ->count(),
        ];

        $upcomingAppointments = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '>=', now())
            ->orderBy('scheduled_start_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'summary' => $summary,
            'upcomingAppointments' => $upcomingAppointments,
        ]);
    }
}
