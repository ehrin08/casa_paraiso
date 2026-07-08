<?php

namespace App\Models;

use Database\Factories\PromotionRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromotionRule extends Model
{
    /** @use HasFactory<PromotionRuleFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rfm_segment_id',
        'name',
        'description',
        'suggested_offer',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function rfmSegment()
    {
        return $this->belongsTo(RfmSegment::class);
    }

    public function promotionSuggestions()
    {
        return $this->hasMany(PromotionSuggestion::class);
    }
}
