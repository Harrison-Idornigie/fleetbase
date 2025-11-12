<?php

namespace Fleetbase\SchoolTransportEngine\Providers;

use Illuminate\Support\ServiceProvider;
use Fleetbase\SchoolTransportEngine\Http\Controllers;

class SchoolTransportServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'school-transport');

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/school-transport.php' => config_path('school-transport.php'),
        ], 'school-transport-config');

        // Register Fleetbase extension if method exists
        $this->app->booted(function () {
            if (method_exists(\Fleetbase\Support\Utils::class, 'registerExtension')) {
                \Fleetbase\Support\Utils::registerExtension('School Transport', [
                    'engine' => true,
                    'icon' => 'school-bus',
                    'description' => 'Complete school bus fleet management system',
                    'version' => '1.0.0',
                    'permissions' => [
                        'school-transport.view' => 'View School Transport',
                        'school-transport.manage' => 'Manage School Transport',
                        'school-transport.students.view' => 'View Students',
                        'school-transport.students.create' => 'Create Students',
                        'school-transport.students.update' => 'Update Students',
                        'school-transport.students.delete' => 'Delete Students',
                        'school-transport.routes.view' => 'View Routes',
                        'school-transport.routes.create' => 'Create Routes',
                        'school-transport.routes.update' => 'Update Routes',
                        'school-transport.routes.delete' => 'Delete Routes',
                        'school-transport.routes.optimize' => 'Optimize Routes',
                        'school-transport.assignments.view' => 'View Assignments',
                        'school-transport.assignments.manage' => 'Manage Assignments',
                        'school-transport.tracking.view' => 'View Real-time Tracking',
                        'school-transport.tracking.manage' => 'Manage Tracking Data',
                    ],
                    'menu' => [
                        'text' => 'School Transport',
                        'icon' => 'school-bus',
                        'items' => [
                            [
                                'text' => 'Students',
                                'route' => 'console.school-transport.students',
                                'permission' => 'school-transport.students.view'
                            ],
                            [
                                'text' => 'Routes',
                                'route' => 'console.school-transport.routes',
                                'permission' => 'school-transport.routes.view'
                            ],
                            [
                                'text' => 'Buses',
                                'route' => 'console.school-transport.buses',
                                'permission' => 'school-transport.buses.view'
                            ],
                            [
                                'text' => 'Bus Assignments',
                                'route' => 'console.school-transport.assignments',
                                'permission' => 'school-transport.assignments.view'
                            ],
                            [
                                'text' => 'Parent Communication',
                                'route' => 'console.school-transport.communications',
                                'permission' => 'school-transport.manage'
                            ]
                        ]
                    ]
                ]);
            }
        });
    }
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/school-transport.php', 'school-transport');

        // Bind controllers
        $this->app->bind('school-transport.student', Controllers\StudentController::class);
        $this->app->bind('school-transport.route', Controllers\SchoolRouteController::class);
        $this->app->bind('school-transport.assignment', Controllers\BusAssignmentController::class);
        $this->app->bind('school-transport.communication', Controllers\CommunicationController::class);

        // Register services as singletons
        $this->app->singleton(\Fleetbase\SchoolTransportEngine\Services\RouteOptimizationService::class);
        $this->app->singleton(\Fleetbase\SchoolTransportEngine\Services\EmailNotificationService::class);
        $this->app->singleton(\Fleetbase\SchoolTransportEngine\Services\SmsNotificationService::class);
    }
}
