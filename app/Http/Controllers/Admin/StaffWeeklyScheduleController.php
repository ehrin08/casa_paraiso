<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesStaffScheduleConflicts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffWeeklyScheduleRequest;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Services\ScheduleWindowResolver;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffWeeklyScheduleController extends Controller
{
    use HandlesStaffScheduleConflicts;

    public function create(Request $request, StaffProfile $staff, ScheduleWindowResolver $scheduleWindows): View
    {
        $data = $request->validate([
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'ends_next_day' => ['nullable', 'boolean'],
        ]);

        $staffProfile = $staff->load('user');
        $businessHours = $scheduleWindows->businessHours();

        return view('admin.shared.form-workspace', [
            'page' => [
                'eyebrow' => __('Weekly schedule'),
                'title' => __('Add weekly shift'),
                'description' => __('Create a recurring working window for ').$staffProfile->user->name.'.',
                'backUrl' => route('admin.staff.show', $staffProfile),
                'backLabel' => __('Back to staff'),
            ],
            'form' => [
                'partial' => 'admin.staff.weekly-schedules.partials.form',
                'action' => route('admin.staff.weekly-schedules.store', $staffProfile),
                'method' => 'POST',
                'submitLabel' => __('Create shift'),
            ],
            'staffProfile' => $staffProfile,
            'weeklySchedule' => new StaffWeeklySchedule([
                'day_of_week' => $data['day_of_week'] ?? now()->dayOfWeek,
                'start_time' => $data['start_time'] ?? $businessHours['opens_at'],
                'end_time' => $data['end_time'] ?? $businessHours['closes_at'],
                'ends_next_day' => (bool) ($data['ends_next_day'] ?? $businessHours['closes_next_day']),
                'is_available' => true,
            ]),
        ]);
    }

    public function store(StaffWeeklyScheduleRequest $request, StaffProfile $staff, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        return $this->performScheduleMutation(
            fn () => $staff->weeklySchedules()->create($this->scheduleData($request)),
            $staff,
            $guard,
            'weekly-schedule-created',
        );
    }

    public function edit(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule): View
    {
        $this->assertScheduleBelongsToStaff($staff, $weeklySchedule);

        $staffProfile = $staff->load('user');

        return view('admin.shared.form-workspace', [
            'page' => [
                'eyebrow' => __('Weekly schedule'),
                'title' => __('Edit weekly shift'),
                'description' => __('Adjust recurring availability for ').$staffProfile->user->name.'.',
                'backUrl' => route('admin.staff.show', $staffProfile),
                'backLabel' => __('Back to staff'),
            ],
            'form' => [
                'partial' => 'admin.staff.weekly-schedules.partials.form',
                'action' => route('admin.staff.weekly-schedules.update', [$staffProfile, $weeklySchedule]),
                'method' => 'PATCH',
                'submitLabel' => __('Save shift'),
            ],
            'staffProfile' => $staffProfile,
            'weeklySchedule' => $weeklySchedule,
        ]);
    }

    public function update(StaffWeeklyScheduleRequest $request, StaffProfile $staff, StaffWeeklySchedule $weeklySchedule, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $this->assertScheduleBelongsToStaff($staff, $weeklySchedule);

        return $this->performScheduleMutation(
            fn () => $weeklySchedule->update($this->scheduleData($request)),
            $staff,
            $guard,
            'weekly-schedule-updated',
        );
    }

    public function destroy(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $this->assertScheduleBelongsToStaff($staff, $weeklySchedule);

        return $this->performScheduleMutation(
            fn () => $weeklySchedule->delete(),
            $staff,
            $guard,
            'weekly-schedule-deleted',
            false,
        );
    }

    private function assertScheduleBelongsToStaff(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule): void
    {
        abort_unless($weeklySchedule->staff_profile_id === $staff->id, 404);
    }

    private function scheduleData(StaffWeeklyScheduleRequest $request): array
    {
        return [
            ...$request->validated(),
            'ends_next_day' => $request->boolean('ends_next_day'),
            'is_available' => $request->boolean('is_available'),
        ];
    }
}
