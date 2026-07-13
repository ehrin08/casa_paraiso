<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StaffScheduleConflictException;
use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Concerns\HandlesStaffScheduleConflicts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffController extends Controller
{
    use HandlesIndexSorting, HandlesStaffScheduleConflicts;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $bookable = (string) $request->query('bookable');
        $sorts = [
            'name' => 'users.name',
            'position' => 'staff_profiles.position',
            'services' => 'services_count',
            'appointments' => 'appointments_count',
            'status' => 'users.is_active',
            'bookable' => 'staff_profiles.is_bookable',
        ];
        $sort = $this->indexSort($request, $sorts, 'status');
        $direction = $this->indexDirection($request, $sort === 'status' ? 'desc' : 'asc');

        $staffProfiles = StaffProfile::query()
            ->with(['user', 'services'])
            ->withCount(['services', 'appointments'])
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->select('staff_profiles.*')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%")
                    ->orWhere('staff_profiles.position', 'like', "%{$search}%")
                    ->orWhere('staff_profiles.specialization', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn ($query) => $query->where('users.is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('users.is_active', false))
            ->when($bookable === 'yes', fn ($query) => $query->where('staff_profiles.is_bookable', true))
            ->when($bookable === 'no', fn ($query) => $query->where('staff_profiles.is_bookable', false))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('users.name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.staff.index', [
            'staffProfiles' => $staffProfiles,
            'search' => $search,
            'status' => $status,
            'bookable' => $bookable,
            'sort' => $sort,
            'direction' => $direction,
            'activeAccountCount' => User::query()->where('role', User::ROLE_STAFF)->where('is_active', true)->count(),
            'inactiveAccountCount' => User::query()->where('role', User::ROLE_STAFF)->where('is_active', false)->count(),
            'bookableCount' => StaffProfile::query()->where('is_bookable', true)->count(),
            'staffAssignableServices' => $this->activeServices(),
            'newStaffProfile' => new StaffProfile(['is_bookable' => true]),
            'newStaffUser' => new User(['is_active' => true]),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $staffProfile = DB::transaction(function () use ($data, $request): StaffProfile {
            $staffUser = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'role' => User::ROLE_STAFF,
                'is_active' => $request->boolean('is_active'),
            ]);

            $staffProfile = $staffUser->staffProfile()->create(
                $this->staffProfileData($data, $request->boolean('is_bookable')),
            );

            $staffProfile->services()->sync($data['service_ids'] ?? []);

            return $staffProfile;
        });

        return redirect()
            ->route('admin.staff.show', $staffProfile)
            ->with('status', 'staff-created');
    }

    public function show(StaffProfile $staff): View
    {
        $staff->load([
            'user',
            'services' => fn ($query) => $query->orderBy('name'),
            'weeklySchedules' => fn ($query) => $query->orderBy('day_of_week')->orderBy('start_time'),
            'scheduleExceptions' => fn ($query) => $query
                ->with('creator')
                ->where('exception_date', '>=', today())
                ->orderBy('exception_date')
                ->orderBy('start_time'),
        ])
            ->loadCount(['services', 'appointments', 'weeklySchedules', 'scheduleExceptions']);

        return view('admin.staff.show', [
            'staffProfile' => $staff,
        ]);
    }

    public function edit(StaffProfile $staff): View
    {
        $staff->load(['user', 'services']);

        return view('admin.shared.form-workspace', [
            'page' => [
                'eyebrow' => __('Staff management'),
                'title' => __('Edit staff'),
                'description' => __('Update the operational profile, bookable status, and service assignments.'),
                'backUrl' => route('admin.staff.show', $staff),
                'backLabel' => __('View staff'),
            ],
            'form' => [
                'partial' => 'admin.staff.partials.form',
                'action' => route('admin.staff.update', $staff),
                'method' => 'PATCH',
                'submitLabel' => __('Save changes'),
                'data' => [],
            ],
            'staffProfile' => $staff,
            'staffUser' => $staff->user,
            'services' => $this->activeServices($staff),
            'assignedServiceIds' => $staff->services->pluck('id')->all(),
        ]);
    }

    public function update(StaffRequest $request, StaffProfile $staff, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $guard, $request, $staff): void {
                $managedStaff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->id);
                $staffUser = $managedStaff->user()->lockForUpdate()->firstOrFail();
                $newBookable = $request->boolean('is_bookable');
                $serviceIds = collect($data['service_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
                $removedServiceIds = $managedStaff->services()
                    ->pluck('services.id')
                    ->map(fn ($id) => (int) $id)
                    ->diff($serviceIds)
                    ->values()
                    ->all();

                if ($managedStaff->is_bookable && ! $newBookable) {
                    $guard->assertCanMakeUnavailable($managedStaff);
                } else {
                    $guard->assertCanRemoveServices($managedStaff, $removedServiceIds);
                }

                $staffUser->update([
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                ]);

                $managedStaff->update($this->staffProfileData($data, $newBookable));

                $managedStaff->services()->sync($serviceIds->all());
            });
        } catch (StaffScheduleConflictException $exception) {
            return $this->eligibilityConflictRedirect(
                $exception,
                __('Change blocked because it would make a therapist ineligible for a future confirmed appointment. Reschedule or cancel the affected visit first.'),
            );
        }

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'staff-updated');
    }

    private function activeServices(?StaffProfile $staff = null)
    {
        $assignedServiceIds = $staff?->services()->pluck('services.id')->all() ?? [];

        return Service::query()
            ->where(function ($query) use ($assignedServiceIds): void {
                $query->where('is_active', true);

                if ($assignedServiceIds !== []) {
                    $query->orWhereIn('id', $assignedServiceIds);
                }
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function staffProfileData(array $data, bool $isBookable): array
    {
        return [
            'position' => $data['position'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'bio' => $data['bio'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'is_bookable' => $isBookable,
        ];
    }
}
