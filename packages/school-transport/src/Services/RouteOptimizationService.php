<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\BusAssignment;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RouteOptimizationService
{
    /**
     * Optimize route stops based on student locations
     *
     * @param SchoolRoute $route
     * @return array
     */
    public function optimizeRoute(SchoolRoute $route)
    {
        try {
            $assignments = BusAssignment::where('route_uuid', $route->uuid)
                ->where('status', 'active')
                ->with('student')
                ->get();

            if ($assignments->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No active assignments found for this route'
                ];
            }

            // Extract pickup locations
            $stops = [];
            foreach ($assignments as $assignment) {
                if ($assignment->pickup_coordinates) {
                    $stops[] = [
                        'id' => $assignment->uuid,
                        'student_id' => $assignment->student_uuid,
                        'name' => $assignment->pickup_stop,
                        'coordinates' => $assignment->pickup_coordinates,
                        'type' => 'pickup'
                    ];
                }
            }

            // Add school as final destination
            if ($route->school_coordinates) {
                $stops[] = [
                    'id' => 'school',
                    'name' => $route->school,
                    'coordinates' => $route->school_coordinates,
                    'type' => 'destination'
                ];
            }

            // Optimize using nearest neighbor algorithm
            $optimizedStops = $this->nearestNeighborOptimization($stops);

            // Calculate total distance and estimated time
            $totalDistance = $this->calculateTotalDistance($optimizedStops);
            $estimatedDuration = $this->estimateDuration($totalDistance, count($optimizedStops));

            // Generate waypoints for mapping
            $waypoints = array_map(function ($stop) {
                return [
                    'lat' => $stop['coordinates']['lat'],
                    'lng' => $stop['coordinates']['lng'],
                    'name' => $stop['name']
                ];
            }, $optimizedStops);

            return [
                'success' => true,
                'optimized_stops' => $optimizedStops,
                'waypoints' => $waypoints,
                'total_distance' => round($totalDistance, 2),
                'estimated_duration' => $estimatedDuration,
                'savings' => $this->calculateSavings($route, $totalDistance, $estimatedDuration)
            ];
        } catch (\Exception $e) {
            Log::error('Route optimization failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Route optimization failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Nearest neighbor optimization algorithm
     *
     * @param array $stops
     * @return array
     */
    protected function nearestNeighborOptimization(array $stops)
    {
        if (empty($stops)) {
            return [];
        }

        $optimized = [];
        $remaining = $stops;

        // Start with the first stop
        $current = array_shift($remaining);
        $optimized[] = $current;

        // Find nearest neighbor for each subsequent stop
        while (!empty($remaining)) {
            $nearestIndex = 0;
            $nearestDistance = PHP_FLOAT_MAX;

            foreach ($remaining as $index => $stop) {
                $distance = $this->calculateDistance(
                    $current['coordinates']['lat'],
                    $current['coordinates']['lng'],
                    $stop['coordinates']['lat'],
                    $stop['coordinates']['lng']
                );

                if ($distance < $nearestDistance) {
                    $nearestDistance = $distance;
                    $nearestIndex = $index;
                }
            }

            $current = $remaining[$nearestIndex];
            $optimized[] = $current;
            array_splice($remaining, $nearestIndex, 1);
        }

        return $optimized;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in kilometers
     */
    protected function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return $distance;
    }

    /**
     * Calculate total distance for a route
     *
     * @param array $stops
     * @return float
     */
    protected function calculateTotalDistance(array $stops)
    {
        $totalDistance = 0;

        for ($i = 0; $i < count($stops) - 1; $i++) {
            $totalDistance += $this->calculateDistance(
                $stops[$i]['coordinates']['lat'],
                $stops[$i]['coordinates']['lng'],
                $stops[$i + 1]['coordinates']['lat'],
                $stops[$i + 1]['coordinates']['lng']
            );
        }

        return $totalDistance;
    }

    /**
     * Estimate duration based on distance and number of stops
     *
     * @param float $distance
     * @param int $stopCount
     * @return int Duration in minutes
     */
    protected function estimateDuration($distance, $stopCount)
    {
        // Average speed: 30 km/h in urban areas
        $travelTime = ($distance / 30) * 60;

        // Add 2 minutes per stop for boarding/alighting
        $stopTime = ($stopCount - 1) * 2;

        return round($travelTime + $stopTime);
    }

    /**
     * Calculate savings from optimization
     *
     * @param SchoolRoute $route
     * @param float $newDistance
     * @param int $newDuration
     * @return array
     */
    protected function calculateSavings(SchoolRoute $route, $newDistance, $newDuration)
    {
        $oldDistance = $route->estimated_distance ?? 0;
        $oldDuration = $route->estimated_duration ?? 0;

        return [
            'distance_saved' => round($oldDistance - $newDistance, 2),
            'time_saved' => $oldDuration - $newDuration,
            'distance_percentage' => $oldDistance > 0 ? round((($oldDistance - $newDistance) / $oldDistance) * 100, 1) : 0,
            'time_percentage' => $oldDuration > 0 ? round((($oldDuration - $newDuration) / $oldDuration) * 100, 1) : 0
        ];
    }

    /**
     * Calculate route efficiency score
     *
     * @param SchoolRoute $route
     * @return float Score from 0-100
     */
    public function calculateEfficiencyScore(SchoolRoute $route)
    {
        $assignmentCount = BusAssignment::where('route_uuid', $route->uuid)
            ->where('status', 'active')
            ->count();

        if ($assignmentCount === 0) {
            return 0;
        }

        // Factors: utilization (40%), distance efficiency (30%), time efficiency (30%)
        $utilizationScore = ($assignmentCount / $route->capacity) * 40;

        $distanceEfficiency = $route->estimated_distance > 0
            ? min(($assignmentCount / $route->estimated_distance) * 30, 30)
            : 0;

        $timeEfficiency = $route->estimated_duration > 0
            ? min((60 / $route->estimated_duration) * 30, 30)
            : 0;

        return round($utilizationScore + $distanceEfficiency + $timeEfficiency, 1);
    }

    /**
     * Suggest route improvements
     *
     * @param SchoolRoute $route
     * @return array
     */
    public function suggestImprovements(SchoolRoute $route)
    {
        $suggestions = [];
        $assignmentCount = BusAssignment::where('route_uuid', $route->uuid)
            ->where('status', 'active')
            ->count();

        $utilization = $route->capacity > 0 ? ($assignmentCount / $route->capacity) * 100 : 0;

        if ($utilization < 50) {
            $suggestions[] = [
                'type' => 'low_utilization',
                'message' => 'Route utilization is below 50%. Consider consolidating with another route.',
                'priority' => 'medium'
            ];
        }

        if ($utilization > 95) {
            $suggestions[] = [
                'type' => 'over_capacity',
                'message' => 'Route is near capacity. Consider splitting into two routes.',
                'priority' => 'high'
            ];
        }

        if ($route->estimated_duration > 90) {
            $suggestions[] = [
                'type' => 'long_duration',
                'message' => 'Route duration exceeds 90 minutes. Students may experience fatigue.',
                'priority' => 'medium'
            ];
        }

        if ($route->estimated_distance > 50) {
            $suggestions[] = [
                'type' => 'long_distance',
                'message' => 'Route distance is quite long. Review for optimization opportunities.',
                'priority' => 'low'
            ];
        }

        return $suggestions;
    }
}
