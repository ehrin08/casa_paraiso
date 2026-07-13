<?php

namespace App\Models;

use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use HasFactory;

    protected $table = 'feedback';

    public const SENTIMENT_POSITIVE = 'positive';

    public const SENTIMENT_NEUTRAL = 'neutral';

    public const SENTIMENT_NEGATIVE = 'negative';

    public const SENTIMENT_LABELS = [
        self::SENTIMENT_POSITIVE,
        self::SENTIMENT_NEUTRAL,
        self::SENTIMENT_NEGATIVE,
    ];

    protected $fillable = [
        'customer_profile_id',
        'appointment_id',
        'service_id',
        'rating',
        'comment',
        'sentiment_label',
        'sentiment_score',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'sentiment_score' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function scopeForIndex(Builder $query): Builder
    {
        return $query
            ->with(['customerProfile.user', 'service', 'appointment'])
            ->leftJoin('customer_profiles as feedback_customer_profiles', 'feedback_customer_profiles.id', '=', 'feedback.customer_profile_id')
            ->leftJoin('users as feedback_customers', 'feedback_customers.id', '=', 'feedback_customer_profiles.user_id')
            ->leftJoin('services as feedback_services', 'feedback_services.id', '=', 'feedback.service_id')
            ->select('feedback.*');
    }

    public function scopeWithSentiment(Builder $query, string $sentiment): Builder
    {
        return $query->when(
            in_array($sentiment, self::SENTIMENT_LABELS, true),
            fn (Builder $query) => $query->where('feedback.sentiment_label', $sentiment),
        );
    }

    public function scopeSearchIndex(Builder $query, string $search): Builder
    {
        return $query->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
            $query->where('feedback_customers.name', 'like', "%{$search}%")
                ->orWhere('feedback_services.name', 'like', "%{$search}%")
                ->orWhere('feedback.comment', 'like', "%{$search}%");
        }));
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class)->withTrashed();
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
