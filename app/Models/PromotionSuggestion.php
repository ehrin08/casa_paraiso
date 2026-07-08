<?php

namespace App\Models;

use Database\Factories\PromotionSuggestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionSuggestion extends Model
{
    /** @use HasFactory<PromotionSuggestionFactory> */
    use HasFactory;

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUSES = [
        self::STATUS_SUGGESTED,
        self::STATUS_REVIEWED,
        self::STATUS_APPLIED,
        self::STATUS_DISMISSED,
    ];

    protected $fillable = [
        'customer_profile_id',
        'rfm_segment_id',
        'promotion_rule_id',
        'recency_days',
        'frequency_count',
        'monetary_total',
        'suggested_offer',
        'status',
        'reviewed_by',
        'reviewed_at',
        'applied_at',
        'dismissed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recency_days' => 'integer',
            'frequency_count' => 'integer',
            'monetary_total' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function rfmSegment()
    {
        return $this->belongsTo(RfmSegment::class);
    }

    public function promotionRule()
    {
        return $this->belongsTo(PromotionRule::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
