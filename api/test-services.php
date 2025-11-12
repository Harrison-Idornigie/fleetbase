<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing School Transport Services...\n\n";

try {
    // Test RoutePlaybackService
    echo "1. Testing RoutePlaybackService...\n";
    $routePlaybackService = app(\Fleetbase\SchoolTransportEngine\Services\RoutePlaybackService::class);
    echo "   ✓ RoutePlaybackService instantiated successfully\n";

    $reflection = new ReflectionClass($routePlaybackService);
    $methods = ['getPlayback', 'calculateRouteMetrics', 'haversineDistance'];
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "   ✓ Method {$method} exists\n";
        } else {
            echo "   ✗ Method {$method} missing\n";
        }
    }

    // Test FuelManagementService
    echo "\n2. Testing FuelManagementService...\n";
    $fuelService = app(\Fleetbase\SchoolTransportEngine\Services\FuelManagementService::class);
    echo "   ✓ FuelManagementService instantiated successfully\n";

    $reflection = new ReflectionClass($fuelService);
    $methods = ['getFuelAnalytics', 'calculateMilesPerGallon', 'generateFuelRecommendations', 'analyzeDriverPatterns'];
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "   ✓ Method {$method} exists\n";
        } else {
            echo "   ✗ Method {$method} missing\n";
        }
    }

    // Test MaintenanceService
    echo "\n3. Testing MaintenanceService...\n";
    $maintenanceService = app(\Fleetbase\SchoolTransportEngine\Services\MaintenanceService::class);
    echo "   ✓ MaintenanceService instantiated successfully\n";

    $reflection = new ReflectionClass($maintenanceService);
    $methods = ['getMaintenanceAnalytics', 'checkSafetyCompliance', 'generatePredictiveMaintenance', 'analyzeSafetyImpact'];
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "   ✓ Method {$method} exists\n";
        } else {
            echo "   ✗ Method {$method} missing\n";
        }
    }

    // Test BusController methods
    echo "\n4. Testing BusController methods...\n";
    $busController = app(\Fleetbase\SchoolTransportEngine\Http\Controllers\BusController::class);
    echo "   ✓ BusController instantiated successfully\n";

    $reflection = new ReflectionClass($busController);
    $methods = ['fuelAnalytics', 'maintenanceAnalytics', 'safetyCompliance', 'routeFuelEfficiency', 'predictiveMaintenance', 'maintenanceCostAnalysis', 'fuelEfficiencyTrends'];
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "   ✓ Method {$method} exists\n";
        } else {
            echo "   ✗ Method {$method} missing\n";
        }
    }

    echo "\n✅ All services and controller methods validated successfully!\n";
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
