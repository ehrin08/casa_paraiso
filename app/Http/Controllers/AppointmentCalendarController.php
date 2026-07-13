<?php

namespace App\Http\Controllers;

use App\Http\Requests\OperationalCalendarRequest;
use App\Services\AppointmentCalendar;
use Illuminate\Http\JsonResponse;

class AppointmentCalendarController extends Controller
{
    public function __invoke(OperationalCalendarRequest $request, AppointmentCalendar $calendar): JsonResponse
    {
        $data = $request->validated();
        [$start, $end] = $request->range();
        $user = $request->user();

        if ($user->isStaff()) {
            abort_unless($user->staffProfile, 403);

            return response()->json($calendar->staff($user->staffProfile, $start, $end, $data['status'] ?? null));
        }

        return response()->json($calendar->admin($start, $end, $data['mode'] ?? 'bookings', $data));
    }
}
