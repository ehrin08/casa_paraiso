<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Models\Appointment;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $isStaff = $request->user()->isStaff();
        $staffProfileId = $request->user()->staffProfile?->id ?? 0;
        $sentiment = (string) $request->query('sentiment_label');
        $search = trim((string) $request->query('q'));
        $sorts = [
            'customer' => 'feedback_customers.name',
            'service' => 'feedback_services.name',
            'rating' => 'feedback.rating',
            'sentiment' => 'feedback.sentiment_label',
            'submitted' => 'feedback.submitted_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'submitted');
        $direction = $this->indexDirection($request, 'desc');

        $feedback = Feedback::query()
            ->forIndex()
            ->when($isStaff, fn ($query) => $query->whereHas('appointment', fn ($query) => $query
                ->where('staff_profile_id', $staffProfileId)
                ->whereIn('status', Appointment::ACTIVE_STATUSES)))
            ->withSentiment($sentiment)
            ->searchIndex($search)
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('feedback.submitted_at')
            ->paginate(12)
            ->withQueryString();

        return view($isStaff ? 'staff.feedback.index' : 'admin.feedback.index', [
            'feedback' => $feedback,
            'sentiment' => $sentiment,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            ...($isStaff ? [] : ['summary' => $this->summary()]),
        ]);
    }

    public function show(Request $request, Feedback $feedback): View
    {
        $isStaff = $request->user()->isStaff();
        $feedback->load(['customerProfile.user', 'service', 'appointment']);

        if ($isStaff) {
            abort_unless(
                (int) $feedback->appointment?->staff_profile_id === (int) ($request->user()->staffProfile?->id ?? 0)
                    && in_array($feedback->appointment?->status, Appointment::ACTIVE_STATUSES, true),
                403,
            );
        }

        return view('feedback.show', [
            'feedback' => $feedback,
            'indexRouteName' => $isStaff ? 'staff.feedback.index' : 'admin.feedback.index',
            'showScore' => ! $isStaff,
        ]);
    }

    /**
     * @return array{positive: int, neutral: int, negative: int}
     */
    private function summary(): array
    {
        return [
            'positive' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_POSITIVE)->count(),
            'neutral' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_NEUTRAL)->count(),
            'negative' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_NEGATIVE)->count(),
        ];
    }
}
