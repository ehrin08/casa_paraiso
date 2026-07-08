<?php

namespace App\Models;

use Database\Factories\RfmSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RfmSegment extends Model
{
    /** @use HasFactory<RfmSegmentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'recency_min_days',
        'recency_max_days',
        'frequency_min',
        'frequency_max',
        'monetary_min',
        'monetary_max',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'recency_min_days' => 'integer',
            'recency_max_days' => 'integer',
            'frequency_min' => 'integer',
            'frequency_max' => 'integer',
            'monetary_min' => 'decimal:2',
            'monetary_max' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function promotionRules()
    {
        return $this->hasMany(PromotionRule::class);
    }

    public function promotionSuggestions()
    {
        return $this->hasMany(PromotionSuggestion::class);
    }
}
