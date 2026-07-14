<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionSuggestionStatusRequest;
use App\Models\Feedback;
use App\Models\PromotionRule;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Services\RfmPromotionGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $status = (string) $request->query('status');
        $search = trim((string) $request->query('q'));
        $sorts = [
            'customer' => 'promotion_customers.name',
            'segment' => 'rfm_segments.name',
            'recency' => 'promotion_suggestions.recency_days',
            'frequency' => 'promotion_suggestions.frequency_count',
            'monetary' => 'promotion_suggestions.monetary_total',
            'status' => 'promotion_suggestions.status',
            'created' => 'promotion_suggestions.created_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'created');
        $direction = $this->indexDirection($request, 'desc');

        $suggestions = PromotionSuggestion::query()
            ->with(['customerProfile.user', 'rfmSegment', 'promotionRule', 'reviewer'])
            ->leftJoin('customer_profiles as promotion_customer_profiles', 'promotion_customer_profiles.id', '=', 'promotion_suggestions.customer_profile_id')
            ->leftJoin('users as promotion_customers', 'promotion_customers.id', '=', 'promotion_customer_profiles.user_id')
            ->leftJoin('rfm_segments', 'rfm_segments.id', '=', 'promotion_suggestions.rfm_segment_id')
            ->select('promotion_suggestions.*')
            ->when(in_array($status, PromotionSuggestion::STATUSES, true), fn ($query) => $query->where('promotion_suggestions.status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('promotion_customers.name', 'like', "%{$search}%")
                    ->orWhere('rfm_segments.name', 'like', "%{$search}%")
                    ->orWhere('promotion_suggestions.suggested_offer', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('promotion_suggestions.created_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.promotions.index', [
            'suggestions' => $suggestions,
            'segments' => RfmSegment::query()->withCount(['promotionRules', 'promotionSuggestions'])->orderBy('name')->get(),
            'rules' => PromotionRule::query()->with('rfmSegment')->orderBy('name')->get(),
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'summary' => [
                'suggested' => PromotionSuggestion::query()->where('status', PromotionSuggestion::STATUS_SUGGESTED)->count(),
                'applied' => PromotionSuggestion::query()->where('status', PromotionSuggestion::STATUS_APPLIED)->count(),
                'dismissed' => PromotionSuggestion::query()->where('status', PromotionSuggestion::STATUS_DISMISSED)->count(),
            ],
            'feedbackSummary' => [
                'positive' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_POSITIVE)->count(),
                'neutral' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_NEUTRAL)->count(),
                'negative' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_NEGATIVE)->count(),
            ],
            'recentFeedback' => Feedback::query()
                ->with(['customerProfile.user', 'service'])
                ->latest('submitted_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function generate(RfmPromotionGenerator $generator): RedirectResponse
    {
        $created = $generator->generate();

        return redirect()
            ->route('admin.promotions.index')
            ->with('status', 'promotions-generated')
            ->with('generated_count', $created->count());
    }

    public function show(PromotionSuggestion $promotion): View
    {
        $promotion->load(['customerProfile.user', 'rfmSegment', 'promotionRule', 'reviewer']);

        return view('admin.promotions.show', [
            'suggestion' => $promotion,
        ]);
    }

    public function update(PromotionSuggestionStatusRequest $request, PromotionSuggestion $promotion): RedirectResponse
    {
        $data = $request->validated();
        $status = $data['status'];
        $previousStatus = $promotion->status;
        $statusChanged = $previousStatus !== $status;

        $promotion->fill([
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($status !== PromotionSuggestion::STATUS_SUGGESTED && ! $promotion->reviewed_at) {
            $promotion->reviewed_by = $request->user()->id;
            $promotion->reviewed_at = now();
        }

        if ($statusChanged && $status === PromotionSuggestion::STATUS_APPLIED) {
            $promotion->applied_at = now();
            $promotion->dismissed_at = null;
        } elseif ($statusChanged && $status === PromotionSuggestion::STATUS_DISMISSED) {
            $promotion->dismissed_at = now();
            $promotion->applied_at = null;
        } elseif ($statusChanged) {
            $promotion->applied_at = null;
            $promotion->dismissed_at = null;
        }

        $promotion->save();

        return redirect()
            ->route('admin.promotions.show', $promotion)
            ->with('status', 'promotion-updated');
    }
}
