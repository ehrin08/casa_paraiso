<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRuleRequest;
use App\Models\PromotionRule;
use App\Models\RfmSegment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionRuleController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $segmentId = $request->integer('rfm_segment_id');
        $sorts = [
            'name' => 'promotion_rules.name',
            'segment' => 'rfm_segments.name',
            'status' => 'promotion_rules.is_active',
            'suggestions' => 'promotion_suggestions_count',
        ];
        $sort = $this->indexSort($request, $sorts, 'status');
        $direction = $this->indexDirection($request, $sort === 'status' ? 'desc' : 'asc');

        $rules = PromotionRule::query()
            ->select('promotion_rules.*')
            ->with('rfmSegment')
            ->withCount('promotionSuggestions')
            ->leftJoin('rfm_segments', 'rfm_segments.id', '=', 'promotion_rules.rfm_segment_id')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('promotion_rules.name', 'like', "%{$search}%")
                    ->orWhere('promotion_rules.description', 'like', "%{$search}%")
                    ->orWhere('promotion_rules.suggested_offer', 'like', "%{$search}%")
                    ->orWhere('rfm_segments.name', 'like', "%{$search}%");
            }))
            ->when($segmentId > 0, fn ($query) => $query->where('promotion_rules.rfm_segment_id', $segmentId))
            ->when($status === 'active', fn ($query) => $query->where('promotion_rules.is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('promotion_rules.is_active', false))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('promotion_rules.name')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.promotion-rules.index', [
            'rules' => $rules,
            'segments' => RfmSegment::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'search' => $search,
            'status' => $status,
            'segmentId' => $segmentId,
            'sort' => $sort,
            'direction' => $direction,
            'activeCount' => PromotionRule::query()->where('is_active', true)->count(),
            'inactiveCount' => PromotionRule::query()->where('is_active', false)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.promotion-rules.create', $this->formData(new PromotionRule([
            'rfm_segment_id' => $request->integer('rfm_segment_id') ?: null,
            'is_active' => true,
        ])));
    }

    public function store(PromotionRuleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        PromotionRule::query()->create($data);

        return redirect()
            ->route('admin.promotion-rules.index')
            ->with('status', 'promotion-rule-created');
    }

    public function edit(PromotionRule $promotionRule): View
    {
        return view('admin.promotion-rules.edit', $this->formData($promotionRule));
    }

    public function update(PromotionRuleRequest $request, PromotionRule $promotionRule): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $promotionRule->update($data);

        return redirect()
            ->route('admin.promotion-rules.index')
            ->with('status', 'promotion-rule-updated');
    }

    public function toggle(PromotionRule $promotionRule): RedirectResponse
    {
        $promotionRule->update(['is_active' => ! $promotionRule->is_active]);

        return back()->with('status', $promotionRule->is_active ? 'promotion-rule-activated' : 'promotion-rule-deactivated');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(PromotionRule $promotionRule): array
    {
        return [
            'promotionRule' => $promotionRule,
            'segments' => RfmSegment::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ];
    }
}
