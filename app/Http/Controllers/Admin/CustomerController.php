<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerNoteRequest;
use App\Models\CustomerProfile;
use App\Services\CustomerDuplicateDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $sorts = [
            'name' => 'users.name',
            'code' => 'customer_profiles.customer_code',
            'appointments' => 'appointments_count',
            'transactions' => 'transactions_count',
            'feedback' => 'feedback_count',
            'preference' => 'customer_profiles.contact_preference',
            'status' => 'users.is_active',
        ];
        $sort = $this->indexSort($request, $sorts, 'name');
        $direction = $this->indexDirection($request);

        $customers = CustomerProfile::query()
            ->forIndex(['appointments', 'transactions', 'feedback', 'promotionSuggestions'])
            ->searchIdentity($search)
            ->when($status === 'active', fn ($query) => $query->where('users.is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('users.is_active', false))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('users.name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'totalCustomers' => CustomerProfile::query()->count(),
        ]);
    }

    public function show(CustomerProfile $customer): View
    {
        $customer->load([
            'user',
            'appointments' => fn ($query) => $query
                ->with(['service', 'staffProfile.user'])
                ->latest('requested_start_at')
                ->limit(8),
            'transactions' => fn ($query) => $query
                ->with(['service', 'appointment'])
                ->latest()
                ->limit(8),
            'feedback' => fn ($query) => $query
                ->with(['service', 'appointment'])
                ->latest('submitted_at')
                ->limit(8),
            'promotionSuggestions' => fn ($query) => $query
                ->with(['rfmSegment', 'promotionRule'])
                ->latest()
                ->limit(8),
        ])->loadCount(['appointments', 'transactions', 'feedback', 'promotionSuggestions']);

        return view('admin.customers.show', [
            'customer' => $customer,
        ]);
    }

    public function duplicates(CustomerDuplicateDetector $duplicates): View
    {
        return view('admin.customers.duplicates', [
            'duplicateGroups' => $duplicates->reviewGroups(),
        ]);
    }

    public function update(CustomerNoteRequest $request, CustomerProfile $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'customer-updated');
    }
}
