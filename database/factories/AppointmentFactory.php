<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $requestedStart = fake()->dateTimeBetween('+1 day', '+1 month');

        return [
            'appointment_number' => 'APT-'.fake()->unique()->numerify('######'),
            'customer_profile_id' => CustomerProfile::factory(),
            'service_id' => Service::factory(),
            'quoted_amount' => fake()->randomFloat(2, 600, 2500),
            'staff_profile_id' => null,
            'preferred_staff_profile_id' => null,
            'requested_start_at' => $requestedStart,
            'scheduled_start_at' => null,
            'scheduled_end_at' => null,
            'status' => Appointment::STATUS_PENDING,
            'customer_notes' => fake()->optional()->sentence(),
            'internal_notes' => null,
            'confirmed_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'created_by' => User::factory()->customer(),
            'updated_by' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(function (array $attributes) {
            $start = now()->addDays(3)->setTime(14, 0);

            return [
                'staff_profile_id' => StaffProfile::factory(),
                'scheduled_start_at' => $start,
                'scheduled_end_at' => (clone $start)->addHour(),
                'status' => Appointment::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ];
        });
    }
}
