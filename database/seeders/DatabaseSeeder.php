<?php

namespace Database\Seeders;

use App\Models\PromotionRule;
use App\Models\RfmSegment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@casaparaiso.test'],
            [
                'name' => 'Casa Paraiso Admin',
                'phone' => '09170000001',
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'password' => Hash::make('password'),
            ],
        );

        $staff = User::updateOrCreate(
            ['email' => 'staff@casaparaiso.test'],
            [
                'name' => 'Demo Staff',
                'phone' => '09170000002',
                'role' => User::ROLE_STAFF,
                'is_active' => true,
                'password' => Hash::make('password'),
            ],
        );

        $secondStaff = User::updateOrCreate(
            ['email' => 'therapist@casaparaiso.test'],
            [
                'name' => 'Demo Therapist',
                'phone' => '09170000004',
                'role' => User::ROLE_STAFF,
                'is_active' => true,
                'password' => Hash::make('password'),
            ],
        );

        $customer = User::updateOrCreate(
            ['email' => 'customer@casaparaiso.test'],
            [
                'name' => 'Demo Customer',
                'phone' => '09170000003',
                'role' => User::ROLE_CUSTOMER,
                'is_active' => true,
                'password' => Hash::make('password'),
            ],
        );

        $returningCustomer = User::updateOrCreate(
            ['email' => 'returning.customer@casaparaiso.test'],
            [
                'name' => 'Returning Customer',
                'phone' => '09170000005',
                'role' => User::ROLE_CUSTOMER,
                'is_active' => true,
                'password' => Hash::make('password'),
            ],
        );

        $staffProfile = StaffProfile::updateOrCreate(
            ['user_id' => $staff->id],
            [
                'position' => 'Spa Therapist',
                'specialization' => 'Hilot and relaxation massage',
                'bio' => 'Handles daily massage and body wellness appointments.',
                'hire_date' => now()->subYear()->toDateString(),
                'is_bookable' => true,
            ],
        );

        $secondStaffProfile = StaffProfile::updateOrCreate(
            ['user_id' => $secondStaff->id],
            [
                'position' => 'Senior Therapist',
                'specialization' => 'Body treatments and ventosa therapy',
                'bio' => 'Supports specialty treatments and customer care.',
                'hire_date' => now()->subMonths(18)->toDateString(),
                'is_bookable' => true,
            ],
        );

        $customer->customerProfile()->updateOrCreate(
            ['user_id' => $customer->id],
            [
                'customer_code' => 'CP-00001',
                'address' => 'Demo address',
                'contact_preference' => 'SMS',
                'notes' => 'Demo customer account for booking workflows.',
                'first_visit_at' => null,
            ],
        );

        $returningCustomer->customerProfile()->updateOrCreate(
            ['user_id' => $returningCustomer->id],
            [
                'customer_code' => 'CP-00002',
                'address' => 'Demo returning customer address',
                'contact_preference' => 'Email',
                'notes' => 'Demo customer with returning-visit behavior.',
                'first_visit_at' => now()->subMonths(4),
            ],
        );

        $services = collect([
            [
                'name' => 'Signature Hilot Massage',
                'slug' => 'signature-hilot-massage',
                'description' => 'Traditional Filipino massage for relaxation and body relief.',
                'duration_minutes' => 60,
                'price' => 1200,
            ],
            [
                'name' => 'Aromatherapy Massage',
                'slug' => 'aromatherapy-massage',
                'description' => 'Relaxing massage with calming aromatic oils.',
                'duration_minutes' => 75,
                'price' => 1500,
            ],
            [
                'name' => 'Ventosa Therapy',
                'slug' => 'ventosa-therapy',
                'description' => 'Cupping therapy for muscle tension and circulation support.',
                'duration_minutes' => 60,
                'price' => 1400,
            ],
            [
                'name' => 'Body Scrub',
                'slug' => 'body-scrub',
                'description' => 'Exfoliating spa treatment for smoother skin.',
                'duration_minutes' => 75,
                'price' => 1600,
            ],
            [
                'name' => 'Foot Spa',
                'slug' => 'foot-spa',
                'description' => 'Foot soak, scrub, and relaxation service.',
                'duration_minutes' => 45,
                'price' => 800,
            ],
        ])->map(fn (array $service) => Service::updateOrCreate(
            ['slug' => $service['slug']],
            [...$service, 'is_active' => true],
        ));

        $staffProfile->services()->syncWithoutDetaching(
            $services->whereIn('slug', ['signature-hilot-massage', 'aromatherapy-massage', 'foot-spa'])->pluck('id')->all()
        );

        $secondStaffProfile->services()->syncWithoutDetaching(
            $services->whereIn('slug', ['ventosa-therapy', 'body-scrub', 'signature-hilot-massage'])->pluck('id')->all()
        );

        foreach ([$staffProfile, $secondStaffProfile] as $profile) {
            foreach ([1, 2, 3, 4, 5, 6] as $dayOfWeek) {
                StaffWeeklySchedule::firstOrCreate(
                    [
                        'staff_profile_id' => $profile->id,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => '10:00:00',
                        'end_time' => '18:00:00',
                    ],
                    ['is_available' => true],
                );
            }
        }

        $segments = collect([
            [
                'name' => 'New customer',
                'description' => 'Recently registered or first-time spa customer.',
                'recency_min_days' => null,
                'recency_max_days' => 30,
                'frequency_min' => 0,
                'frequency_max' => 1,
                'monetary_min' => null,
                'monetary_max' => null,
                'suggested_offer' => 'Welcome wellness add-on',
            ],
            [
                'name' => 'Loyal customer',
                'description' => 'Frequent customer with steady recent visits.',
                'recency_min_days' => null,
                'recency_max_days' => 60,
                'frequency_min' => 4,
                'frequency_max' => null,
                'monetary_min' => null,
                'monetary_max' => null,
                'suggested_offer' => 'Loyalty massage upgrade',
            ],
            [
                'name' => 'At-risk customer',
                'description' => 'Previously active customer who has not visited recently.',
                'recency_min_days' => 61,
                'recency_max_days' => 180,
                'frequency_min' => 2,
                'frequency_max' => null,
                'monetary_min' => null,
                'monetary_max' => null,
                'suggested_offer' => 'Return visit reminder offer',
            ],
            [
                'name' => 'High-value customer',
                'description' => 'Customer with high total spend.',
                'recency_min_days' => null,
                'recency_max_days' => 90,
                'frequency_min' => 3,
                'frequency_max' => null,
                'monetary_min' => 5000,
                'monetary_max' => null,
                'suggested_offer' => 'Premium wellness package perk',
            ],
            [
                'name' => 'Inactive customer',
                'description' => 'Customer with no recent completed paid visit.',
                'recency_min_days' => 181,
                'recency_max_days' => null,
                'frequency_min' => null,
                'frequency_max' => null,
                'monetary_min' => null,
                'monetary_max' => null,
                'suggested_offer' => 'We miss you reactivation offer',
            ],
        ]);

        $segments->each(function (array $segment): void {
            $suggestedOffer = $segment['suggested_offer'];
            unset($segment['suggested_offer']);

            $rfmSegment = RfmSegment::updateOrCreate(
                ['name' => $segment['name']],
                [...$segment, 'is_active' => true],
            );

            PromotionRule::updateOrCreate(
                [
                    'rfm_segment_id' => $rfmSegment->id,
                    'name' => $rfmSegment->name.' suggestion',
                ],
                [
                    'description' => 'Default rule for '.$rfmSegment->name.' promotion suggestions.',
                    'suggested_offer' => $suggestedOffer,
                    'is_active' => true,
                ],
            );
        });
    }
}
