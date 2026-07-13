<?php

$opensLabel = '1:00 PM';
$closesLabel = '12:00 midnight';

return [
    'business_name' => 'Casa Paraiso Body and Wellness Spa',
    'marketing_line' => 'Reserve your spot. You deserve this.',
    'business_hours' => [
        'summary' => 'Open every day',
        'window' => "{$opensLabel} to {$closesLabel}",
        'opens_label' => $opensLabel,
        'closes_label' => $closesLabel,
        'timezone' => 'Asia/Manila',
        'opens_at' => '13:00',
        'closes_at' => '00:00',
        'closes_next_day' => true,
        'slot_interval_minutes' => 30,
        'availability_selection_minutes' => 60,
    ],
    'service_packages' => [
        [
            'name' => 'GAIA TOUCH',
            'slug' => 'gaia-touch',
            'description' => 'Signature Full Body Massage with Swedish, Shiatsu, and Traditional Hilot techniques.',
            'duration_minutes' => 60,
            'duration_label' => '1 Hour',
            'price' => 499.00,
            'includes' => [
                'Signature Full Body Massage',
                'Swedish',
                'Shiatsu',
                'Traditional Hilot',
            ],
        ],
        [
            'name' => 'TETHYS FLOW',
            'slug' => 'tethys-flow',
            'description' => 'Signature Full Body Massage with Ventosa or Hot Compress add-on options.',
            'duration_minutes' => 60,
            'duration_label' => '1 Hour',
            'price' => 649.00,
            'includes' => [
                'Signature Full Body Massage',
                'Ventosa',
                'Hot Compress',
            ],
        ],
        [
            'name' => 'HESTIA WARMTH',
            'slug' => 'hestia-warmth',
            'description' => 'Full Body Massage with warming add-on options for deeper body relief.',
            'duration_minutes' => 90,
            'duration_label' => '1 Hour 30 Minutes',
            'price' => 749.00,
            'includes' => [
                'Full Body Massage',
                'Ventosa',
                'Hot Stone',
                'Hot Compress',
            ],
        ],
        [
            'name' => 'AURORA BREEZE',
            'slug' => 'aurora-breeze',
            'description' => 'Extended Full Body Massage package with add-ons and VIP Room access.',
            'duration_minutes' => 120,
            'duration_label' => '2 Hours',
            'price' => 849.00,
            'includes' => [
                'Full Body Massage',
                'Ventosa',
                'Hot Compress',
                'Hot Stone',
                'VIP Room',
            ],
        ],
    ],
    'addons' => [
        [
            'name' => 'Ventosa',
            'price' => 200.00,
        ],
        [
            'name' => 'Hot Compress',
            'price' => 200.00,
        ],
        [
            'name' => 'Hot Stone',
            'price' => 200.00,
        ],
        [
            'name' => '30-Minute Back Massage',
            'price' => 299.00,
        ],
        [
            'name' => 'VIP Room',
            'price' => 200.00,
        ],
    ],
];
