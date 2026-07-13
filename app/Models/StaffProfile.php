<?php

namespace App\Models;

use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'position',
        'specialization',
        'bio',
        'hire_date',
        'is_bookable',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'is_bookable' => 'boolean',
        ];
    }

    public function scopeEligibleForAppointments(Builder $query): Builder
    {
        return $query
            ->where('is_bookable', true)
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true));
    }

    public function scopeOfferingService(Builder $query, Service|int $service): Builder
    {
        $serviceId = $service instanceof Service ? $service->getKey() : $service;

        return $query->whereHas('services', fn (Builder $query) => $query->whereKey($serviceId));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'staff_services')->withTimestamps();
    }

    public function weeklySchedules()
    {
        return $this->hasMany(StaffWeeklySchedule::class);
    }

    public function scheduleExceptions()
    {
        return $this->hasMany(StaffScheduleException::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
