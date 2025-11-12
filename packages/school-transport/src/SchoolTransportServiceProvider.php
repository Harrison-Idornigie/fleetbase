<?php

namespace Fleetbase\SchoolTransport;

use Fleetbase\SchoolTransport\Models\Student;
use Fleetbase\SchoolTransport\Models\School;
use Fleetbase\SchoolTransport\Models\SchoolRoute;
use Fleetbase\SchoolTransport\Models\Bus;
use Fleetbase\SchoolTransport\Models\BusAssignment;
use Fleetbase\SchoolTransport\Models\Driver;
use Fleetbase\SchoolTransport\Models\ParentGuardian;
use Fleetbase\SchoolTransport\Models\Stop;
use Fleetbase\SchoolTransport\Models\Trip;
use Fleetbase\SchoolTransport\Models\TrackingLog;
use Fleetbase\SchoolTransport\Models\Alert;
use Fleetbase\SchoolTransport\Models\Communication;
use Illuminate\Support\ServiceProvider;

class SchoolTransportServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');

        // Register models for polymorphic relationships
        $this->app->bind('school-transport-models', function () {
            return [
                Student::class,
                School::class,
                SchoolRoute::class,
                Bus::class,
                BusAssignment::class,
                Driver::class,
                ParentGuardian::class,
                Stop::class,
                Trip::class,
                TrackingLog::class,
                Alert::class,
                Communication::class,
            ];
        });
    }
}
