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
            ->paginate(12)
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
        return view('admin.shared.form-workspace', [
            'page' => [
                'eyebrow' => __('Promotion configuration'),
                'title' => __('Add RFM segment'),
                'description' => __('Create a clear customer group using measurable visit and spending thresholds.'),
                'backUrl' => route('admin.rfm-segments.index'),
                'backLabel' => __('Back to segments'),
            ],
            'form' => [
                'partial' => 'admin.rfm-segments.partials.form',
                'action' => route('admin.rfm-segments.store'),
                'method' => 'POST',
                'submitLabel' => __('Create segment'),
            ],
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
        return view('admin.shared.form-workspace', [
            'page' => [
                'eyebrow' => __('Promotion configuration'),
                'title' => __('Edit RFM segment'),
                'description' => __('Changes affect future generation only; stored suggestion snapshots remain unchanged.'),
                'backUrl' => route('admin.rfm-segments.index'),
                'backLabel' => __('Back to segments'),
            ],
            'form' => [
                'partial' => 'admin.rfm-segments.partials.form',
                'action' => route('admin.rfm-segments.update', $rfmSegment),
                'method' => 'PATCH',
                'submitLabel' => __('Save changes'),
            ],
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
