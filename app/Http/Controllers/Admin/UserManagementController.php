<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StaffScheduleConflictException;
use App\Http\Controllers\Concerns\HandlesStaffScheduleConflicts;
use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\CustomerDuplicateDetector;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    use HandlesStaffScheduleConflicts;

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with(['staffProfile', 'customerProfile'])->orderByRaw("FIELD(role, 'super_admin', 'admin', 'staff', 'customer')")->orderBy('name')->paginate(15),
            'assignableRoles' => [User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_CUSTOMER],
            'creatableRoles' => [User::ROLE_ADMIN, User::ROLE_CUSTOMER],
        ]);
    }

    public function store(Request $request, CustomerDuplicateDetector $duplicates): RedirectResponse
    {
        if ($existing = $duplicates->exactEmail((string) $request->input('email'))) {
            if ($existing->isCustomer() && $existing->customerProfile && ! $existing->customerProfile->trashed()) {
                return redirect()
                    ->route('admin.customers.show', $existing->customerProfile)
                    ->with('status', 'existing-customer-found');
            }

            return back()
                ->withInput()
                ->withErrors(['email' => __('An account already uses this email. Review it in User Access instead of creating another.')]);
        }

        $data = $this->validated($request);

        if ($data['role'] === User::ROLE_CUSTOMER) {
            $warnings = $duplicates->likelyMatches($data['name'], $data['phone'] ?? null);

            if ($warnings !== [] && ! $request->boolean('duplicate_reviewed')) {
                return back()
                    ->withInput()
                    ->with('duplicateCustomerWarnings', $warnings)
                    ->withErrors([
                        'duplicate_reviewed' => __('Review the possible customer match before creating a separate account.'),
                    ]);
            }
        }

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            $this->syncRoleProfile($user);
        });

        return back()->with('status', 'user-created');
    }

    public function update(Request $request, User $user, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403, 'The protected super administrator cannot be changed.');
        $data = $this->validated($request, $user);

        try {
            DB::transaction(function () use ($data, $guard, $user): void {
                // Appointment confirmation and staff administration both lock the
                // staff profile first. Keep the same order here so eligibility
                // changes cannot race a confirmation or deadlock another editor.
                $staffProfile = StaffProfile::withTrashed()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();
                $managedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $newActive = (bool) ($data['is_active'] ?? false);
                $removesStaffRole = $managedUser->isStaff() && $data['role'] !== User::ROLE_STAFF;
                $deactivatesStaff = $staffProfile && $managedUser->is_active && ! $newActive;

                if ($staffProfile && ($removesStaffRole || $deactivatesStaff)) {
                    $guard->assertCanMakeUnavailable($staffProfile);
                }

                $managedUser->update([
                    'email' => strtolower($data['email']),
                    'role' => $data['role'],
                    'is_active' => $newActive,
                ]);
                $this->syncRoleProfile($managedUser);
            });
        } catch (StaffScheduleConflictException $exception) {
            return $this->eligibilityConflictRedirect(
                $exception,
                __('Change blocked because this therapist has a future confirmed appointment. Reschedule or cancel the affected visit first.'),
            );
        }

        return back()->with('status', 'user-updated');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $roles = $user?->isStaff()
            ? [User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_CUSTOMER]
            : [User::ROLE_ADMIN, User::ROLE_CUSTOMER];

        return $request->validate([
            'name' => $user
                ? ['exclude']
                : ['required', 'string', 'max:255'],
            'phone' => $user
                ? ['exclude']
                : ['nullable', 'string', 'max:50'],
            'email' => [
                'required',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user?->id),
                Rule::notIn([strtolower(config('auth.super_admin_email'))]),
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if ($user?->google_id && strcasecmp(trim((string) $value), $user->email) !== 0) {
                        $fail('A Google-linked email is managed by Google and cannot be changed here.');
                    }
                },
            ],
            'role' => ['required', Rule::in($roles)],
            'is_active' => ['sometimes', 'boolean'],
            'duplicate_reviewed' => ['sometimes', 'boolean'],
        ]);
    }

    private function syncRoleProfile(User $user): void
    {
        if ($user->isStaff()) {
            $profile = $user->staffProfile()->firstOrCreate([], ['is_bookable' => true]);

            if ($profile->trashed()) {
                $profile->restore();
            }
        } elseif ($user->staffProfile()->exists()) {
            $user->staffProfile()->first()->delete();
        }

        if ($user->isCustomer()) {
            CustomerProfile::provisionFor($user);
        } elseif ($user->customerProfile()->exists()) {
            $user->customerProfile()->first()->delete();
        }
    }
}
