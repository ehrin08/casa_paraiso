<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $baseName = fake()->randomElement([
            'Signature Hilot Massage',
            'Aromatherapy Massage',
            'Foot Spa',
            'Body Scrub',
            'Ventosa Therapy',
        ]);
        $name = $baseName.' '.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'duration_minutes' => fake()->randomElement([45, 60, 75, 90]),
            'price' => fake()->randomFloat(2, 600, 2500),
            'is_active' => true,
        ];
    }
}
