<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $staffProfileId = (int) ($request->user()->staffProfile?->id ?? 0);
        abort_unless($staffProfileId > 0, 403);

        $search = trim((string) $request->query('q'));
        $sorts = [
            'name' => 'users.name',
            'code' => 'customer_profiles.customer_code',
            'appointments' => 'appointments_count',
            'feedback' => 'feedback_count',
            'preference' => 'customer_profiles.contact_preference',
        ];
        $sort = $this->indexSort($request, $sorts, 'name');
        $direction = $this->indexDirection($request);

        $customers = CustomerProfile::query()
            ->with('user')
            ->withCount([
                'appointments as appointments_count' => fn ($query) => $query
                    ->where('staff_profile_id', $staffProfileId)
                    ->whereIn('status', Appointment::ACTIVE_STATUSES),
                'feedback as feedback_count' => fn ($query) => $query->whereHas(
                    'appointment',
                    fn ($appointmentQuery) => $appointmentQuery
                        ->where('staff_profile_id', $staffProfileId)
                        ->whereIn('status', Appointment::ACTIVE_STATUSES),
                ),
            ])
            ->assignedToStaff($staffProfileId)
            ->join('users', 'users.id', '=', 'customer_profiles.user_id')
            ->select('customer_profiles.*')
            ->searchIdentity($search)
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('users.name')
            ->paginate(10)
            ->withQueryString();

        return view('staff.customers.index', [
            'customers' => $customers,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(Request $request, CustomerProfile $customer): View
    {
        $staffProfileId = (int) ($request->user()->staffProfile?->id ?? 0);

        abort_unless(
            $staffProfileId > 0 && $customer->appointments()
                ->where('staff_profile_id', $staffProfileId)
                ->whereIn('status', Appointment::ACTIVE_STATUSES)
                ->exists(),
            403,
        );

        $customer->load([
            'user',
            'appointments' => fn ($query) => $query
                ->where('staff_profile_id', $staffProfileId)
                ->whereIn('status', Appointment::ACTIVE_STATUSES)
                ->with(['service', 'staffProfile.user'])
                ->latest('scheduled_start_at')
                ->limit(10),
            'feedback' => fn ($query) => $query
                ->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery
                    ->where('staff_profile_id', $staffProfileId)
                    ->whereIn('status', Appointment::ACTIVE_STATUSES))
                ->with(['service', 'appointment'])
                ->latest('submitted_at')
                ->limit(8),
        ])->loadCount([
            'appointments' => fn ($query) => $query
                ->where('staff_profile_id', $staffProfileId)
                ->whereIn('status', Appointment::ACTIVE_STATUSES),
            'feedback' => fn ($query) => $query->whereHas(
                'appointment',
                fn ($appointmentQuery) => $appointmentQuery
                    ->where('staff_profile_id', $staffProfileId)
                    ->whereIn('status', Appointment::ACTIVE_STATUSES),
            ),
        ]);

        return view('staff.customers.show', [
            'customer' => $customer,
        ]);
    }
}
