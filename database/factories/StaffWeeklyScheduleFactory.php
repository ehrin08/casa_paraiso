<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffWeeklySchedule>
 */
class StaffWeeklyScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'day_of_week' => fake()->numberBetween(1, 6),
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
            'is_available' => true,
        ];
    }
}
