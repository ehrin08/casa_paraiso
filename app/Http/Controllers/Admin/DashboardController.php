<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\TransactionAdjustment;
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
            'todayRevenue' => TransactionAdjustment::query()
                ->whereDate('occurred_at', $today)
                ->whereIn('action', [
                    TransactionAdjustment::ACTION_CREATED,
                    TransactionAdjustment::ACTION_PAYMENT,
                    TransactionAdjustment::ACTION_REFUND,
                    TransactionAdjustment::ACTION_VOID,
                ])
                ->sum('payment_delta'),
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
