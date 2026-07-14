<?php

namespace Database\Factories;

use App\Models\ApplicationSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationSetting>
 */
class ApplicationSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_name' => 'Casa Paraiso Body and Wellness Spa',
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'business_address' => fake()->address(),
            'default_payment_method' => Transaction::METHOD_CASH,
            'updated_by' => User::factory()->admin(),
        ];
    }
}
