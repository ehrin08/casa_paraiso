<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesStaffScheduleConflicts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffScheduleExceptionRequest;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffScheduleExceptionController extends Controller
{
    use HandlesStaffScheduleConflicts;

    public function create(Request $request, StaffProfile $staff): View
    {
        $data = $request->validate([
            'exception_date' => ['nullable', 'date'],
            'exception_type' => ['nullable', 'in:available,unavailable'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'ends_next_day' => ['nullable', 'boolean'],
        ]);

        $staffProfile = $staff->load('user');

        return view('admin.shared.form-workspace', [
            'page' => [
                'eyebrow' => __('Schedule exception'),
                'title' => __('Add exception'),
                'description' => __('Add a one-off availability override for ').$staffProfile->user->name.'.',
                'backUrl' => route('admin.staff.show', $staffProfile),
                'backLabel' => __('Back to staff'),
            ],
            'form' => [
                'partial' => 'admin.staff.schedule-exceptions.partials.form',
                'action' => route('admin.staff.schedule-exceptions.store', $staffProfile),
                'method' => 'POST',
                'submitLabel' => __('Create exception'),
            ],
            'staffProfile' => $staffProfile,
            'scheduleException' => new StaffScheduleException([
                'exception_date' => $data['exception_date'] ?? now()->toDateString(),
                'exception_type' => $data['exception_type'] ?? StaffScheduleException::TYPE_UNAVAILABLE,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'ends_next_day' => (bool) ($data['ends_next_day'] ?? false),
            ]),
        ]);
    }

    public function store(StaffScheduleExceptionRequest $request, StaffProfile $staff, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        return $this->performScheduleMutation(
            fn () => $staff->scheduleExceptions()->create([
                ...$this->exceptionData($request),
                'created_by' => $request->user()->id,
            ]),
            $staff,
            $guard,
            'schedule-exception-created',
        );
    }

    public function edit(StaffProfile $staff, StaffScheduleException $scheduleException): View
    {
        $this->assertExceptionBelongsToStaff($staff, $scheduleException);

        $staffProfile = $staff->load('user');

        return view('admin.shared.form-workspace', [
            'page' => [
                'eyebrow' => __('Schedule exception'),
                'title' => __('Edit exception'),
                'description' => __('Adjust one-off availability for ').$staffProfile->user->name.'.',
                'backUrl' => route('admin.staff.show', $staffProfile),
                'backLabel' => __('Back to staff'),
            ],
            'form' => [
                'partial' => 'admin.staff.schedule-exceptions.partials.form',
                'action' => route('admin.staff.schedule-exceptions.update', [$staffProfile, $scheduleException]),
                'method' => 'PATCH',
                'submitLabel' => __('Save exception'),
            ],
            'staffProfile' => $staffProfile,
            'scheduleException' => $scheduleException,
        ]);
    }

    public function update(StaffScheduleExceptionRequest $request, StaffProfile $staff, StaffScheduleException $scheduleException, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $this->assertExceptionBelongsToStaff($staff, $scheduleException);

        return $this->performScheduleMutation(
            fn () => $scheduleException->update($this->exceptionData($request)),
            $staff,
            $guard,
            'schedule-exception-updated',
        );
    }

    public function destroy(StaffProfile $staff, StaffScheduleException $scheduleException, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $this->assertExceptionBelongsToStaff($staff, $scheduleException);

        return $this->performScheduleMutation(
            fn () => $scheduleException->delete(),
            $staff,
            $guard,
            'schedule-exception-deleted',
            false,
        );
    }

    private function exceptionData(Request $request): array
    {
        $data = $request->validated();

        if ($data['exception_type'] === StaffScheduleException::TYPE_UNAVAILABLE && empty($data['start_time']) && empty($data['end_time'])) {
            $data['start_time'] = null;
            $data['end_time'] = null;
            $data['ends_next_day'] = false;
        } else {
            $data['ends_next_day'] = $request->boolean('ends_next_day');
        }

        return $data;
    }

    private function assertExceptionBelongsToStaff(StaffProfile $staff, StaffScheduleException $scheduleException): void
    {
        abort_unless($scheduleException->staff_profile_id === $staff->id, 404);
    }
}
