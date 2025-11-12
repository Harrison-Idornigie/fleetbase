<?php

return [
    /*
    |--------------------------------------------------------------------------
    | School Transport Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the School Transport Engine
    |
    */

    // Student management settings
    'students' => [
        'bulk_import_limit' => 1000,
        'photo_storage_disk' => 'public',
        'photo_max_size' => 2048, // KB
        'required_fields' => [
            'student_id',
            'first_name',
            'last_name',
            'grade',
            'school',
            'home_address'
        ],
        'special_needs_types' => [
            'wheelchair_accessible',
            'medical_alert',
            'behavioral_support',
            'early_dismissal',
            'late_arrival'
        ]
    ],

    // Route management settings
    'routes' => [
        'max_students_per_route' => 80,
        'max_route_duration' => 90, // minutes
        'default_pickup_window' => 5, // minutes
        'default_dropoff_window' => 5, // minutes
        'route_optimization_provider' => 'google_maps', // google_maps, mapbox
        'enable_live_tracking' => true,
        'tracking_interval' => 30 // seconds
    ],

    // Communication settings
    'communications' => [
        'notification_channels' => [
            'email' => true,
            'sms' => true,
            'app_notification' => true
        ],
        'auto_notifications' => [
            'bus_delay' => true,
            'route_change' => true,
            'emergency_alert' => true,
            'pickup_reminder' => false,
            'absence_notification' => true
        ],
        'notification_templates' => [
            'bus_delay' => 'The bus for route {route_name} is running {delay_minutes} minutes late.',
            'route_change' => 'There has been a change to route {route_name}. Please check the updated schedule.',
            'emergency_alert' => 'Emergency alert for route {route_name}: {message}',
            'absence_notification' => '{student_name} was not present at their pickup location today.'
        ]
    ],

    // Safety and compliance settings
    'safety' => [
        'require_bus_driver_checkin' => true,
        'require_student_scan' => false,
        'enable_parent_tracking' => true,
        'emergency_contact_limit' => 5,
        'require_pickup_authorization' => true,
        'enable_attendance_tracking' => true
    ],

    // Integration settings
    'integrations' => [
        'student_information_system' => null, // 'powerschool', 'skyward', 'infinite_campus'
        'mapping_service' => 'google_maps',
        'weather_service' => 'openweather',
        'parent_app' => null
    ],

    // Reporting settings
    'reporting' => [
        'default_timezone' => 'America/New_York',
        'academic_year_start' => '08-01', // MM-DD format
        'academic_year_end' => '06-30', // MM-DD format
        'attendance_export_formats' => ['csv', 'xlsx', 'pdf'],
        'route_report_formats' => ['csv', 'xlsx', 'pdf']
    ]
];
