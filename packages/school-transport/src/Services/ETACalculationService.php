<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\SchoolTransportEngine\Models\Trip;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\TrackingLog;
use Fleetbase\SchoolTransportEngine\Events\ETAUpdated;
use Fleetbase\FleetOps\Support\OSRM;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Support\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Service for calculating real-time ETAs using FleetBase's free OSRM routing service
 */
class ETACalculationService
{
    /**
     * @var string Default mapping provider (OSRM is free and built into FleetBase)
     */
    protected $defaultProvider = 'osrm';

    /**
     * @var array Available mapping providers (OSRM is free, Google/Mapbox require paid API keys)
     */
    protected $providers = ['osrm', 'google', 'mapbox'];

    /**
     * Calculate ETA for a bus to reach a specific stop
     *
     * @param Bus $bus
     * @param array $destinationCoordinates
     * @param array $options
     * @return array
     */
    public function calculateBusETA(Bus $bus, array $destinationCoordinates, array $options = []): array
    {
        try {
            // Get current bus location
            $currentLocation = $this->getCurrentBusLocation($bus);
            if (!$currentLocation) {
                return $this->createErrorResponse('Bus location not available');
            }

            // Calculate ETA using selected provider
            $provider = $options['provider'] ?? $this->defaultProvider;
            $eta = $this->calculateETAWithProvider($provider, $currentLocation, $destinationCoordinates, $options);

            // Cache the ETA result
            $cacheKey = "eta_bus_{$bus->uuid}_" . md5(json_encode($destinationCoordinates));
            Cache::put($cacheKey, $eta, now()->addMinutes(5));

            return [
                'success' => true,
                'eta_minutes' => $eta['duration_minutes'],
                'distance_km' => $eta['distance_km'],
                'traffic_factor' => $eta['traffic_factor'] ?? 1.0,
                'estimated_arrival_time' => now()->addMinutes($eta['duration_minutes'])->toISOString(),
                'route_polyline' => $eta['polyline'] ?? null,
                'provider' => $provider,
                'calculated_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('ETA calculation failed', [
                'bus_id' => $bus->uuid,
                'destination' => $destinationCoordinates,
                'error' => $e->getMessage()
            ]);

            return $this->createErrorResponse('ETA calculation failed: ' . $e->getMessage());
        }
    }

    /**
     * Calculate ETA for all stops on a route
     *
     * @param Trip $trip
     * @param array $options
     * @return array
     */
    public function calculateRouteETAs(Trip $trip, array $options = []): array
    {
        try {
            $route = $trip->route;
            if (!$route || !$route->stops) {
                return $this->createErrorResponse('Route or stops not found');
            }

            $bus = $trip->bus;
            if (!$bus) {
                return $this->createErrorResponse('Bus not found for trip');
            }

            $currentLocation = $this->getCurrentBusLocation($bus);
            if (!$currentLocation) {
                return $this->createErrorResponse('Bus location not available');
            }

            $etas = [];
            $lastLocation = $currentLocation;

            foreach ($route->stops as $index => $stop) {
                $stopCoordinates = [
                    'lat' => $stop['coordinates']['lat'],
                    'lng' => $stop['coordinates']['lng']
                ];

                $eta = $this->calculateETAWithProvider(
                    $options['provider'] ?? $this->defaultProvider,
                    $lastLocation,
                    $stopCoordinates,
                    $options
                );

                $etas[] = [
                    'stop_id' => $stop['id'],
                    'stop_name' => $stop['name'],
                    'sequence' => $index + 1,
                    'eta_minutes' => $eta['duration_minutes'],
                    'distance_km' => $eta['distance_km'],
                    'estimated_arrival_time' => now()->addMinutes($eta['duration_minutes'])->toISOString(),
                    'coordinates' => $stopCoordinates
                ];

                // Update last location for cumulative calculation
                $lastLocation = $stopCoordinates;
            }

            return [
                'success' => true,
                'trip_id' => $trip->uuid,
                'route_id' => $route->uuid,
                'bus_id' => $bus->uuid,
                'current_location' => $currentLocation,
                'etas' => $etas,
                'calculated_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('Route ETA calculation failed', [
                'trip_id' => $trip->uuid,
                'error' => $e->getMessage()
            ]);

            return $this->createErrorResponse('Route ETA calculation failed: ' . $e->getMessage());
        }
    }

    /**
     * Calculate ETA using FleetBase's built-in OSRM service (FREE)
     *
     * @param array $origin
     * @param array $destination
     * @param array $options
     * @return array
     */
    protected function calculateETAWithOSRM(array $origin, array $destination, array $options = []): array
    {
        try {
            $startPoint = new Point($origin['lat'], $origin['lng']);
            $endPoint = new Point($destination['lat'], $destination['lng']);

            // Use FleetBase's OSRM service (completely free)
            $routeData = OSRM::getRoute($startPoint, $endPoint, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => false
            ]);

            if (!isset($routeData['routes']) || empty($routeData['routes'])) {
                throw new \Exception('No route found with OSRM');
            }

            $route = $routeData['routes'][0];
            $durationSeconds = $route['duration'] ?? 0;
            $distanceMeters = $route['distance'] ?? 0;

            return [
                'duration_minutes' => round($durationSeconds / 60, 1),
                'distance_km' => round($distanceMeters / 1000, 2),
                'traffic_factor' => 1.0, // OSRM doesn't include live traffic, but it's free!
                'polyline' => $route['geometry'] ?? null,
                'provider' => 'osrm'
            ];
        } catch (\Exception $e) {
            Log::warning('OSRM ETA calculation failed', [
                'origin' => $origin,
                'destination' => $destination,
                'error' => $e->getMessage()
            ]);

            // Fallback to Haversine calculation if OSRM fails
            return $this->calculateETAWithHaversine($origin, $destination);
        }
    }

    /**
     * Fallback ETA calculation using Haversine distance (always available)
     *
     * @param array $origin
     * @param array $destination
     * @return array
     */
    protected function calculateETAWithHaversine(array $origin, array $destination): array
    {
        $distanceKm = $this->calculateHaversineDistance(
            $origin['lat'],
            $origin['lng'],
            $destination['lat'],
            $destination['lng']
        );

        // Estimate driving time: average city speed ~30 km/h for school buses
        $avgSpeedKmh = 30;
        $durationMinutes = ($distanceKm / $avgSpeedKmh) * 60;

        return [
            'duration_minutes' => round($durationMinutes, 1),
            'distance_km' => round($distanceKm, 2),
            'traffic_factor' => 1.0,
            'polyline' => null,
            'provider' => 'haversine_fallback'
        ];
    }

    /**
     * Calculate ETA using Google Maps API (REQUIRES PAID API KEY)
     *
     * @param array $origin
     * @param array $destination
     * @param array $options
     * @return array
     */
    protected function calculateETAWithGoogle(array $origin, array $destination, array $options = []): array
    {
        $apiKey = config('services.google.maps_api_key');
        if (!$apiKey) {
            throw new \Exception('Google Maps API key not configured. Consider using free OSRM instead.');
        }

        $params = [
            'origins' => "{$origin['lat']},{$origin['lng']}",
            'destinations' => "{$destination['lat']},{$destination['lng']}",
            'departure_time' => $options['departure_time'] ?? 'now',
            'traffic_model' => $options['traffic_model'] ?? 'best_guess',
            'mode' => 'driving',
            'units' => 'metric',
            'key' => $apiKey
        ];

        $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', $params);

        if (!$response->successful()) {
            throw new \Exception('Google Maps API request failed');
        }

        $data = $response->json();

        if ($data['status'] !== 'OK') {
            throw new \Exception('Google Maps API error: ' . $data['status']);
        }

        $element = $data['rows'][0]['elements'][0];

        if ($element['status'] !== 'OK') {
            throw new \Exception('Route calculation failed: ' . $element['status']);
        }

        $durationInTraffic = $element['duration_in_traffic'] ?? $element['duration'];
        $trafficFactor = isset($element['duration_in_traffic'])
            ? $durationInTraffic['value'] / $element['duration']['value']
            : 1.0;

        return [
            'duration_minutes' => round($durationInTraffic['value'] / 60, 1),
            'distance_km' => round($element['distance']['value'] / 1000, 2),
            'traffic_factor' => round($trafficFactor, 2),
            'duration_text' => $durationInTraffic['text'],
            'distance_text' => $element['distance']['text']
        ];
    }

    /**
     * Calculate ETA using Mapbox API (REQUIRES PAID API KEY)
     *
     * @param array $origin
     * @param array $destination
     * @param array $options
     * @return array
     */
    protected function calculateETAWithMapbox(array $origin, array $destination, array $options = []): array
    {
        $apiKey = config('services.mapbox.access_token');
        if (!$apiKey) {
            throw new \Exception('Mapbox access token not configured. Consider using free OSRM instead.');
        }

        $coordinates = "{$origin['lng']},{$origin['lat']};{$destination['lng']},{$destination['lat']}";

        $params = [
            'access_token' => $apiKey,
            'annotations' => 'duration,distance',
            'overview' => 'full',
            'geometries' => 'geojson'
        ];

        $url = "https://api.mapbox.com/directions/v5/mapbox/driving-traffic/{$coordinates}";
        $response = Http::get($url, $params);

        if (!$response->successful()) {
            throw new \Exception('Mapbox API request failed');
        }

        $data = $response->json();

        if (empty($data['routes'])) {
            throw new \Exception('No route found');
        }

        $route = $data['routes'][0];

        return [
            'duration_minutes' => round($route['duration'] / 60, 1),
            'distance_km' => round($route['distance'] / 1000, 2),
            'traffic_factor' => 1.0, // Mapbox includes traffic by default
            'polyline' => $route['geometry'] ?? null
        ];
    }

    /**
     * Calculate ETA with specified provider
     *
     * @param string $provider
     * @param array $origin
     * @param array $destination
     * @param array $options
     * @return array
     */
    protected function calculateETAWithProvider(string $provider, array $origin, array $destination, array $options = []): array
    {
        switch ($provider) {
            case 'osrm':
                return $this->calculateETAWithOSRM($origin, $destination, $options);
            case 'google':
                return $this->calculateETAWithGoogle($origin, $destination, $options);
            case 'mapbox':
                return $this->calculateETAWithMapbox($origin, $destination, $options);
            default:
                // Default to free OSRM if unsupported provider is requested
                Log::warning("Unsupported ETA provider '{$provider}', falling back to OSRM");
                return $this->calculateETAWithOSRM($origin, $destination, $options);
        }
    }

    /**
     * Get current bus location from latest tracking log
     *
     * @param Bus $bus
     * @return array|null
     */
    protected function getCurrentBusLocation(Bus $bus): ?array
    {
        $latestLog = $bus->trackingLogs()
            ->where('created_at', '>=', now()->subHours(1)) // Only consider recent logs
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('gps_timestamp')
            ->first();

        if (!$latestLog) {
            return null;
        }

        return [
            'lat' => $latestLog->latitude,
            'lng' => $latestLog->longitude,
            'timestamp' => $latestLog->gps_timestamp
        ];
    }

    /**
     * Check if bus is near a stop (within threshold)
     *
     * @param Bus $bus
     * @param array $stopCoordinates
     * @param float $thresholdKm
     * @return bool
     */
    public function isBusNearStop(Bus $bus, array $stopCoordinates, float $thresholdKm = 0.5): bool
    {
        $currentLocation = $this->getCurrentBusLocation($bus);
        if (!$currentLocation) {
            return false;
        }

        $distance = $this->calculateHaversineDistance(
            $currentLocation['lat'],
            $currentLocation['lng'],
            $stopCoordinates['lat'],
            $stopCoordinates['lng']
        );

        return $distance <= $thresholdKm;
    }

    /**
     * Calculate distance using Haversine formula
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in kilometers
     */
    protected function calculateHaversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Estimate ETA based on historical route data
     *
     * @param SchoolRoute $route
     * @param int $stopIndex
     * @return int|null ETA in minutes
     */
    public function getHistoricalETA(SchoolRoute $route, int $stopIndex): ?int
    {
        // Get historical trip data for this route and stop
        $historicalTrips = Trip::where('route_uuid', $route->uuid)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        if ($historicalTrips->isEmpty()) {
            return null;
        }

        $totalMinutes = 0;
        $validTrips = 0;

        foreach ($historicalTrips as $trip) {
            // Calculate time taken to reach this stop
            $timeToStop = $this->calculateTimeToStop($trip, $stopIndex);
            if ($timeToStop) {
                $totalMinutes += $timeToStop;
                $validTrips++;
            }
        }

        return $validTrips > 0 ? round($totalMinutes / $validTrips) : null;
    }

    /**
     * Calculate time to reach a specific stop in a trip
     *
     * @param Trip $trip
     * @param int $stopIndex
     * @return int|null
     */
    protected function calculateTimeToStop(Trip $trip, int $stopIndex): ?int
    {
        if (!$trip->started_at) {
            return null;
        }

        // This would require tracking when the bus reached each stop
        // For now, estimate based on total trip duration and stop position
        $totalDuration = $trip->duration_minutes;
        $totalStops = count($trip->route->stops ?? []);

        if (!$totalDuration || !$totalStops) {
            return null;
        }

        // Simple estimation: divide total time by stops
        return round(($stopIndex / $totalStops) * $totalDuration);
    }

    /**
     * Create error response array
     *
     * @param string $message
     * @return array
     */
    protected function createErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'eta_minutes' => null,
            'calculated_at' => now()->toISOString()
        ];
    }

    /**
     * Trigger ETA updated event
     *
     * @param array $etaData
     * @return void
     */
    public function broadcastETAUpdate(array $etaData): void
    {
        event(new ETAUpdated($etaData));
    }

    /**
     * Get cached ETA if available
     *
     * @param string $busId
     * @param array $destination
     * @return array|null
     */
    public function getCachedETA(string $busId, array $destination): ?array
    {
        $cacheKey = "eta_bus_{$busId}_" . md5(json_encode($destination));
        return Cache::get($cacheKey);
    }

    /**
     * Clear ETA cache for a bus
     *
     * @param string $busId
     * @return void
     */
    public function clearETACache(string $busId): void
    {
        $pattern = "eta_bus_{$busId}_*";
        Cache::flush(); // In production, use more specific cache clearing
    }
}
