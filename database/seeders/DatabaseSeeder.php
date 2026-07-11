<?php

namespace Database\Seeders;

use App\Models\PromotionRule;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Transaction;
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

        $customerProfile = $customer->customerProfile()->updateOrCreate(
            ['user_id' => $customer->id],
            [
                'customer_code' => 'CP-00001',
                'address' => 'Demo address',
                'contact_preference' => 'SMS',
                'notes' => 'Demo customer account for booking workflows.',
                'first_visit_at' => null,
            ],
        );

        $returningCustomerProfile = $returningCustomer->customerProfile()->updateOrCreate(
            ['user_id' => $returningCustomer->id],
            [
                'customer_code' => 'CP-00002',
                'address' => 'Demo returning customer address',
                'contact_preference' => 'Email',
                'notes' => 'Demo customer with returning-visit behavior.',
                'first_visit_at' => now()->subMonths(4),
            ],
        );

        Service::query()
            ->whereIn('slug', [
                'signature-hilot-massage',
                'aromatherapy-massage',
                'ventosa-therapy',
                'body-scrub',
                'foot-spa',
            ])
            ->update(['is_active' => false]);

        $services = collect(config('casa.service_packages'))->map(function (array $service): Service {
            $serviceData = [
                'name' => $service['name'],
                'slug' => $service['slug'],
                'description' => $service['description'],
                'duration_minutes' => $service['duration_minutes'],
                'price' => $service['price'],
            ];

            return Service::updateOrCreate(
                ['slug' => $serviceData['slug']],
                [...$serviceData, 'is_active' => true],
            );
        });

        $staffProfile->services()->syncWithoutDetaching(
            $services->whereIn('slug', ['gaia-touch', 'tethys-flow', 'hestia-warmth'])->pluck('id')->all()
        );

        $secondStaffProfile->services()->syncWithoutDetaching(
            $services->whereIn('slug', ['tethys-flow', 'hestia-warmth', 'aurora-breeze'])->pluck('id')->all()
        );

        foreach ([$staffProfile, $secondStaffProfile] as $profile) {
            foreach ([0, 1, 2, 3, 4, 5, 6] as $dayOfWeek) {
                StaffWeeklySchedule::updateOrCreate(
                    [
                        'staff_profile_id' => $profile->id,
                        'day_of_week' => $dayOfWeek,
                    ],
                    [
                        'start_time' => '13:00:00',
                        'end_time' => '00:00:00',
                        'ends_next_day' => true,
                        'is_available' => true,
                    ],
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

        $gaiaTouch = $services->firstWhere('slug', 'gaia-touch');
        $tethysFlow = $services->firstWhere('slug', 'tethys-flow');

        Appointment::updateOrCreate(
            ['appointment_number' => 'APT-DEMO-PENDING'],
            [
                'customer_profile_id' => $customerProfile->id,
                'service_id' => $gaiaTouch->id,
                'staff_profile_id' => null,
                'preferred_staff_profile_id' => $staffProfile->id,
                'requested_start_at' => now()->addDays(2)->setTime(14, 0),
                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
                'status' => Appointment::STATUS_PENDING,
                'customer_notes' => 'Prefers a quiet room if available.',
                'created_by' => $customer->id,
            ],
        );

        $confirmedStart = now()->addDay()->setTime(15, 0);
        Appointment::updateOrCreate(
            ['appointment_number' => 'APT-DEMO-CONFIRMED'],
            [
                'customer_profile_id' => $returningCustomerProfile->id,
                'service_id' => $tethysFlow->id,
                'staff_profile_id' => $staffProfile->id,
                'requested_start_at' => $confirmedStart,
                'scheduled_start_at' => $confirmedStart,
                'scheduled_end_at' => $confirmedStart->copy()->addMinutes($tethysFlow->duration_minutes),
                'status' => Appointment::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $completedStart = now()->subDays(10)->setTime(14, 0);
        $completedAppointment = Appointment::updateOrCreate(
            ['appointment_number' => 'APT-DEMO-COMPLETED'],
            [
                'customer_profile_id' => $returningCustomerProfile->id,
                'service_id' => $gaiaTouch->id,
                'staff_profile_id' => $staffProfile->id,
                'requested_start_at' => $completedStart,
                'scheduled_start_at' => $completedStart,
                'scheduled_end_at' => $completedStart->copy()->addMinutes($gaiaTouch->duration_minutes),
                'status' => Appointment::STATUS_COMPLETED,
                'confirmed_at' => $completedStart->copy()->subDay(),
                'completed_at' => $completedStart->copy()->addMinutes($gaiaTouch->duration_minutes),
                'created_by' => $admin->id,
                'updated_by' => $staff->id,
            ],
        );

        Transaction::updateOrCreate(
            ['transaction_number' => 'TRX-DEMO-PAID'],
            [
                'customer_profile_id' => $returningCustomerProfile->id,
                'appointment_id' => $completedAppointment->id,
                'service_id' => $gaiaTouch->id,
                'amount' => $gaiaTouch->price,
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_GCASH,
                'paid_at' => $completedAppointment->completed_at,
                'recorded_by' => $staff->id,
                'notes' => 'Demo completed appointment payment.',
            ],
        );

        Feedback::updateOrCreate(
            ['appointment_id' => $completedAppointment->id],
            [
                'customer_profile_id' => $returningCustomerProfile->id,
                'service_id' => $gaiaTouch->id,
                'rating' => 5,
                'comment' => 'Excellent and relaxing service.',
                'sentiment_label' => Feedback::SENTIMENT_POSITIVE,
                'sentiment_score' => 1.0,
                'submitted_at' => $completedAppointment->completed_at->copy()->addDay(),
            ],
        );

        $loyalSegment = RfmSegment::query()->where('name', 'New customer')->first();
        $loyalRule = $loyalSegment?->promotionRules()->first();

        if ($loyalSegment && $loyalRule) {
            PromotionSuggestion::updateOrCreate(
                [
                    'customer_profile_id' => $returningCustomerProfile->id,
                    'promotion_rule_id' => $loyalRule->id,
                ],
                [
                    'rfm_segment_id' => $loyalSegment->id,
                    'recency_days' => 10,
                    'frequency_count' => 1,
                    'monetary_total' => $gaiaTouch->price,
                    'suggested_offer' => $loyalRule->suggested_offer,
                    'status' => PromotionSuggestion::STATUS_SUGGESTED,
                    'notes' => 'Demo suggestion for the promotion review queue.',
                ],
            );
        }
    }
}
