<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\StaffScheduleConflictException;
use App\Models\StaffProfile;
use App\Services\StaffScheduleConflictGuard;
use Closure;
use Illuminate\Http\RedirectResponse;

trait HandlesStaffScheduleConflicts
{
    protected function performScheduleMutation(
        Closure $mutation,
        StaffProfile $staffProfile,
        StaffScheduleConflictGuard $guard,
        string $status,
        bool $withInput = true,
    ): RedirectResponse {
        try {
            $guard->preservingFutureCoverage($staffProfile, $mutation);
        } catch (StaffScheduleConflictException $exception) {
            return $this->scheduleConflictRedirect($exception, $withInput);
        }

        return redirect()
            ->route('admin.staff.show', $staffProfile)
            ->with('status', $status);
    }

    protected function scheduleConflictRedirect(StaffScheduleConflictException $exception, bool $withInput = true): RedirectResponse
    {
        return $this->staffConflictRedirect(
            $exception,
            'schedule',
            __('Change blocked because it would conflict with a confirmed appointment. Reschedule or cancel the affected visit first.'),
            'schedule_conflicts',
            $withInput,
        );
    }

    protected function eligibilityConflictRedirect(StaffScheduleConflictException $exception, string $message): RedirectResponse
    {
        return $this->staffConflictRedirect(
            $exception,
            'staff_eligibility',
            $message,
            'eligibility_conflicts',
            true,
        );
    }

    private function staffConflictRedirect(
        StaffScheduleConflictException $exception,
        string $errorKey,
        string $message,
        string $conflictsKey,
        bool $withInput,
    ): RedirectResponse {
        $response = back()
            ->withErrors([$errorKey => $message])
            ->with($conflictsKey, collect($exception->conflicts)->map(fn (array $conflict) => [
                ...$conflict,
                'url' => route('admin.appointments.show', $conflict['id']),
            ])->all());

        return $withInput ? $response->withInput() : $response;
    }
}
