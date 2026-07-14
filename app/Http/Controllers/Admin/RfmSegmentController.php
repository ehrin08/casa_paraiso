<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RfmSegmentRequest;
use App\Models\RfmSegment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RfmSegmentController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $sorts = [
            'name' => 'name',
            'status' => 'is_active',
            'rules' => 'promotion_rules_count',
            'suggestions' => 'promotion_suggestions_count',
        ];
        $sort = $this->indexSort($request, $sorts, 'status');
        $direction = $this->indexDirection($request, $sort === 'status' ? 'desc' : 'asc');

        $segments = RfmSegment::query()
            ->withCount(['promotionRules', 'promotionSuggestions'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('name')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.rfm-segments.index', [
            'segments' => $segments,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'activeCount' => RfmSegment::query()->where('is_active', true)->count(),
            'inactiveCount' => RfmSegment::query()->where('is_active', false)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.rfm-segments.create', [
            'rfmSegment' => new RfmSegment(['is_active' => true]),
        ]);
    }

    public function store(RfmSegmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        RfmSegment::query()->create($data);

        return redirect()
            ->route('admin.rfm-segments.index')
            ->with('status', 'rfm-segment-created');
    }

    public function edit(RfmSegment $rfmSegment): View
    {
        return view('admin.rfm-segments.edit', [
            'rfmSegment' => $rfmSegment,
        ]);
    }

    public function update(RfmSegmentRequest $request, RfmSegment $rfmSegment): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $rfmSegment->update($data);

        return redirect()
            ->route('admin.rfm-segments.index')
            ->with('status', 'rfm-segment-updated');
    }

    public function toggle(RfmSegment $rfmSegment): RedirectResponse
    {
        $rfmSegment->update(['is_active' => ! $rfmSegment->is_active]);

        return back()->with('status', $rfmSegment->is_active ? 'rfm-segment-activated' : 'rfm-segment-deactivated');
    }
}
