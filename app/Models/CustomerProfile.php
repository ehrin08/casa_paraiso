<?php

namespace App\Models;

use Database\Factories\CustomerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProfile extends Model
{
    /** @use HasFactory<CustomerProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'customer_code',
        'birth_date',
        'address',
        'contact_preference',
        'notes',
        'first_visit_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'first_visit_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    public function promotionSuggestions()
    {
        return $this->hasMany(PromotionSuggestion::class);
    }
}
