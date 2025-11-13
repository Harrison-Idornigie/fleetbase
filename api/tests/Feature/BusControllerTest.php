<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Fleetbase\SchoolTransportEngine\Services\RoutePlaybackService;
use Fleetbase\SchoolTransportEngine\Services\FuelManagementService;
use Fleetbase\SchoolTransportEngine\Services\MaintenanceService;

class BusControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_fuel_analytics_for_a_bus()
    {
        // Create a bus
        $bus = Bus::factory()->create();

        // Mock the service
        $this->mock(FuelManagementService::class, function ($mock) use ($bus) {
            $mock->shouldReceive('getFuelAnalytics')
                ->with($bus->id)
                ->andReturn([
                    'total_fuel_cost' => 1500.00,
                    'average_mpg' => 12.5,
                    'fuel_efficiency_trend' => 'improving',
                    'recommendations' => ['Reduce idling time']
                ]);
        });

        // Make request
        $response = $this->getJson("/api/school-transport/buses/{$bus->id}/fuel-analytics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_fuel_cost',
                'average_mpg',
                'fuel_efficiency_trend',
                'recommendations'
            ]);
    }

    /** @test */
    public function it_can_get_maintenance_analytics_for_a_bus()
    {
        // Create a bus
        $bus = Bus::factory()->create();

        // Mock the service
        $this->mock(MaintenanceService::class, function ($mock) use ($bus) {
            $mock->shouldReceive('getMaintenanceAnalytics')
                ->with($bus->id)
                ->andReturn([
                    'total_maintenance_cost' => 2500.00,
                    'upcoming_maintenance' => [],
                    'safety_compliance_status' => 'compliant',
                    'predictive_maintenance_alerts' => []
                ]);
        });

        // Make request
        $response = $this->getJson("/api/school-transport/buses/{$bus->id}/maintenance-analytics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_maintenance_cost',
                'upcoming_maintenance',
                'safety_compliance_status',
                'predictive_maintenance_alerts'
            ]);
    }

    /** @test */
    public function it_can_get_route_playback_for_a_bus()
    {
        // Create a bus
        $bus = Bus::factory()->create();

        // Mock the service
        $this->mock(RoutePlaybackService::class, function ($mock) use ($bus) {
            $mock->shouldReceive('getPlayback')
                ->with($bus->id, null, null)
                ->andReturn([
                    'timeline' => [],
                    'metrics' => [
                        'total_distance' => 45.5,
                        'average_speed' => 25.0,
                        'student_pickups' => 12
                    ]
                ]);
        });

        // Make request
        $response = $this->getJson("/api/school-transport/buses/{$bus->id}/route-playback");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'timeline',
                'metrics' => [
                    'total_distance',
                    'average_speed',
                    'student_pickups'
                ]
            ]);
    }
}
