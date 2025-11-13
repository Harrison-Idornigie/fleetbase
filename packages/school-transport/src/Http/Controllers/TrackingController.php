<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Services\TrackingService;
use Fleetbase\SchoolTransportEngine\Events\BusLocationUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TrackingController extends FleetbaseController
{
    /**
     * The namespace for this controller
     *
     * @var string
     */
    public string $namespace = '\Fleetbase\SchoolTransportEngine';

    /**
     * The resource to query
     *
     * @var string
     */
    public $resource = 'tracking_log';

    /**
     * The tracking service instance
     *
     * @var TrackingService
     */
    protected $trackingService;

    /**
     * Create a new controller instance
     */
    public function __construct(TrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Display a listing of tracking logs.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by bus
                if ($request->filled('bus')) {
                    $query->forBus($request->input('bus'));
                }

                // Filter by trip
                if ($request->filled('trip')) {
                    $query->forTrip($request->input('trip'));
                }

                // Filter by driver
                if ($request->filled('driver')) {
                    $query->forDriver($request->input('driver'));
                }

                // Filter by date range
                if ($request->filled(['start_date', 'end_date'])) {
                    $query->dateRange($request->input('start_date'), $request->input('end_date'));
                }

                // Include relationships
                $query->with(['bus', 'trip', 'driver']);
            },
            // Transform function
            function (&$logs) {
                return $logs->map(function ($log) {
                    return [
                        'id' => $log->uuid,
                        'public_id' => $log->public_id,
                        'bus' => $log->bus,
                        'trip' => $log->trip,
                        'driver' => $log->driver,
                        'latitude' => $log->latitude,
                        'longitude' => $log->longitude,
                        'coordinates' => $log->coordinates,
                        'speed_kmh' => $log->speed_kmh,
                        'speed_mph' => $log->speed_mph,
                        'heading' => $log->heading,
                        'altitude' => $log->altitude,
                        'accuracy' => $log->accuracy,
                        'location_name' => $log->location_name,
                        'odometer_km' => $log->odometer_km,
                        'fuel_level_percent' => $log->fuel_level_percent,
                        'engine_status' => $log->engine_status,
                        'engine_status_display' => $log->engine_status_display,
                        'gps_timestamp' => $log->gps_timestamp,
                        'device_timestamp' => $log->device_timestamp,
                        'battery_level' => $log->battery_level,
                        'network_signal' => $log->network_signal,
                        'temperature_celsius' => $log->temperature_celsius,
                        'is_valid' => $log->isValid(),
                        'created_at' => $log->created_at
                    ];
                });
            }
        );
    }

    /**
     * Store a new tracking log.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('school-transport.tracking.manage');

        $this->validate($request, [
            'bus_uuid' => 'required|exists:school_transport_buses,uuid',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'nullable|numeric|min:0|max:200',
            'heading' => 'nullable|numeric|min:0|max:360',
            'altitude' => 'nullable|numeric|min:-100|max:10000',
            'accuracy' => 'nullable|numeric|min:0|max:1000',
            'location_name' => 'nullable|string|max:255',
            'odometer_km' => 'nullable|numeric|min:0',
            'fuel_level_percent' => 'nullable|numeric|min:0|max:100',
            'engine_status' => 'nullable|in:on,off,idle',
            'gps_timestamp' => 'nullable|date',
            'device_timestamp' => 'nullable|date',
            'battery_level' => 'nullable|numeric|min:0|max:100',
            'network_signal' => 'nullable|integer|min:0|max:100',
            'temperature_celsius' => 'nullable|numeric|min:-50|max:100',
            'trip_uuid' => 'nullable|exists:school_transport_trips,uuid',
            'driver_uuid' => 'nullable|exists:school_transport_drivers,uuid'
        ]);

        // Verify that bus belongs to the company
        $bus = Bus::where('uuid', $request->input('bus_uuid'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Verify optional trip and driver belong to the company
        if ($request->filled('trip_uuid')) {
            Trip::where('uuid', $request->input('trip_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        if ($request->filled('driver_uuid')) {
            Driver::where('uuid', $request->input('driver_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        $trackingLog = TrackingLog::create([
            'bus_uuid' => $bus->uuid,
            'trip_uuid' => $request->input('trip_uuid'),
            'driver_uuid' => $request->input('driver_uuid'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'speed_kmh' => $request->input('speed_kmh'),
            'heading' => $request->input('heading'),
            'altitude' => $request->input('altitude'),
            'accuracy' => $request->input('accuracy'),
            'location_name' => $request->input('location_name'),
            'odometer_km' => $request->input('odometer_km'),
            'fuel_level_percent' => $request->input('fuel_level_percent'),
            'engine_status' => $request->input('engine_status'),
            'gps_timestamp' => $request->input('gps_timestamp', now()),
            'device_timestamp' => $request->input('device_timestamp', now()),
            'battery_level' => $request->input('battery_level'),
            'network_signal' => $request->input('network_signal'),
            'temperature_celsius' => $request->input('temperature_celsius'),
            'company_uuid' => session('company')
        ]);

        // Broadcast location update event
        event(new BusLocationUpdated($trackingLog));

        return response()->json([
            'tracking_log' => $trackingLog->load(['bus', 'trip', 'driver'])
        ], 201);
    }

    /**
     * Display the specified tracking log.
     */
    public function show(string $id): JsonResponse
    {
        $log = TrackingLog::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['bus', 'trip', 'driver'])
            ->firstOrFail();

        return response()->json([
            'tracking_log' => [
                'id' => $log->uuid,
                'public_id' => $log->public_id,
                'bus' => $log->bus,
                'trip' => $log->trip,
                'driver' => $log->driver,
                'latitude' => $log->latitude,
                'longitude' => $log->longitude,
                'coordinates' => $log->coordinates,
                'speed_kmh' => $log->speed_kmh,
                'speed_mph' => $log->speed_mph,
                'heading' => $log->heading,
                'altitude' => $log->altitude,
                'accuracy' => $log->accuracy,
                'location_name' => $log->location_name,
                'odometer_km' => $log->odometer_km,
                'fuel_level_percent' => $log->fuel_level_percent,
                'engine_status' => $log->engine_status,
                'engine_status_display' => $log->engine_status_display,
                'gps_timestamp' => $log->gps_timestamp,
                'device_timestamp' => $log->device_timestamp,
                'battery_level' => $log->battery_level,
                'network_signal' => $log->network_signal,
                'temperature_celsius' => $log->temperature_celsius,
                'is_valid' => $log->isValid(),
                'created_at' => $log->created_at
            ]
        ]);
    }

    /**
     * Get current location for a bus.
     */
    public function currentLocation(string $busId): JsonResponse
    {
        $bus = Bus::where('uuid', $busId)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $latestLog = $bus->trackingLogs()
            ->latest('gps_timestamp')
            ->first();

        if (!$latestLog) {
            return response()->json([
                'bus_id' => $bus->uuid,
                'bus_number' => $bus->bus_number,
                'message' => 'No tracking data available'
            ]);
        }

        return response()->json([
            'bus_id' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'current_location' => [
                'latitude' => $latestLog->latitude,
                'longitude' => $latestLog->longitude,
                'speed_kmh' => $latestLog->speed_kmh,
                'heading' => $latestLog->heading,
                'altitude' => $latestLog->altitude,
                'accuracy' => $latestLog->accuracy,
                'location_name' => $latestLog->location_name,
                'odometer_km' => $latestLog->odometer_km,
                'fuel_level_percent' => $latestLog->fuel_level_percent,
                'engine_status' => $latestLog->engine_status,
                'gps_timestamp' => $latestLog->gps_timestamp,
                'device_timestamp' => $latestLog->device_timestamp,
                'battery_level' => $latestLog->battery_level,
                'network_signal' => $latestLog->network_signal,
                'temperature_celsius' => $latestLog->temperature_celsius
            ],
            'last_updated' => $latestLog->gps_timestamp
        ]);
    }

    /**
     * Get tracking history for a bus.
     */
    public function busHistory(Request $request, string $busId): JsonResponse
    {
        $this->validate($request, [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'limit' => 'nullable|integer|min:1|max:1000'
        ]);

        $bus = Bus::where('uuid', $busId)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $query = $bus->trackingLogs()
            ->orderBy('gps_timestamp', 'desc');

        if ($request->filled('start_date')) {
            $query->where('gps_timestamp', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('gps_timestamp', '<=', $request->input('end_date'));
        }

        $limit = $request->input('limit', 100);
        $logs = $query->take($limit)->get();

        return response()->json([
            'bus_id' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'total_logs' => $logs->count(),
            'tracking_logs' => $logs->map(function ($log) {
                return [
                    'id' => $log->uuid,
                    'latitude' => $log->latitude,
                    'longitude' => $log->longitude,
                    'coordinates' => $log->coordinates,
                    'speed_kmh' => $log->speed_kmh,
                    'heading' => $log->heading,
                    'altitude' => $log->altitude,
                    'accuracy' => $log->accuracy,
                    'location_name' => $log->location_name,
                    'odometer_km' => $log->odometer_km,
                    'fuel_level_percent' => $log->fuel_level_percent,
                    'engine_status' => $log->engine_status,
                    'gps_timestamp' => $log->gps_timestamp,
                    'device_timestamp' => $log->device_timestamp,
                    'battery_level' => $log->battery_level,
                    'network_signal' => $log->network_signal,
                    'temperature_celsius' => $log->temperature_celsius
                ];
            })
        ]);
    }

    /**
     * Get tracking history for a trip.
     */
    public function tripHistory(Request $request, string $tripId): JsonResponse
    {
        $this->validate($request, [
            'limit' => 'nullable|integer|min:1|max:1000'
        ]);

        $trip = Trip::where('uuid', $tripId)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $limit = $request->input('limit', 500);
        $logs = $trip->trackingLogs()
            ->orderBy('gps_timestamp')
            ->take($limit)
            ->get();

        return response()->json([
            'trip_id' => $trip->uuid,
            'trip_number' => $trip->trip_id,
            'total_logs' => $logs->count(),
            'tracking_logs' => $logs->map(function ($log) {
                return [
                    'id' => $log->uuid,
                    'latitude' => $log->latitude,
                    'longitude' => $log->longitude,
                    'coordinates' => $log->coordinates,
                    'speed_kmh' => $log->speed_kmh,
                    'heading' => $log->heading,
                    'altitude' => $log->altitude,
                    'accuracy' => $log->accuracy,
                    'location_name' => $log->location_name,
                    'odometer_km' => $log->odometer_km,
                    'fuel_level_percent' => $log->fuel_level_percent,
                    'engine_status' => $log->engine_status,
                    'gps_timestamp' => $log->gps_timestamp,
                    'device_timestamp' => $log->device_timestamp,
                    'battery_level' => $log->battery_level,
                    'network_signal' => $log->network_signal,
                    'temperature_celsius' => $log->temperature_celsius
                ];
            })
        ]);
    }

    /**
     * Bulk insert tracking logs.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $this->authorize('school-transport.tracking.manage');

        $this->validate($request, [
            'logs' => 'required|array|max:1000',
            'logs.*.bus_uuid' => 'required|exists:school_transport_buses,uuid',
            'logs.*.latitude' => 'required|numeric|between:-90,90',
            'logs.*.longitude' => 'required|numeric|between:-180,180',
            'logs.*.speed_kmh' => 'nullable|numeric|min:0|max:200',
            'logs.*.heading' => 'nullable|numeric|min:0|max:360',
            'logs.*.altitude' => 'nullable|numeric|min:-100|max:10000',
            'logs.*.accuracy' => 'nullable|numeric|min:0|max:1000',
            'logs.*.location_name' => 'nullable|string|max:255',
            'logs.*.odometer_km' => 'nullable|numeric|min:0',
            'logs.*.fuel_level_percent' => 'nullable|numeric|min:0|max:100',
            'logs.*.engine_status' => 'nullable|in:on,off,idle',
            'logs.*.gps_timestamp' => 'nullable|date',
            'logs.*.device_timestamp' => 'nullable|date',
            'logs.*.battery_level' => 'nullable|numeric|min:0|max:100',
            'logs.*.network_signal' => 'nullable|integer|min:0|max:100',
            'logs.*.temperature_celsius' => 'nullable|numeric|min:-50|max:100',
            'logs.*.trip_uuid' => 'nullable|exists:school_transport_trips,uuid',
            'logs.*.driver_uuid' => 'nullable|exists:school_transport_drivers,uuid'
        ]);

        $inserted = [];
        $errors = [];

        foreach ($request->input('logs') as $index => $logData) {
            try {
                // Verify that bus belongs to the company
                Bus::where('uuid', $logData['bus_uuid'])
                    ->where('company_uuid', session('company'))
                    ->firstOrFail();

                // Verify optional trip and driver belong to the company
                if (isset($logData['trip_uuid'])) {
                    Trip::where('uuid', $logData['trip_uuid'])
                        ->where('company_uuid', session('company'))
                        ->firstOrFail();
                }

                if (isset($logData['driver_uuid'])) {
                    Driver::where('uuid', $logData['driver_uuid'])
                        ->where('company_uuid', session('company'))
                        ->firstOrFail();
                }

                $trackingLog = TrackingLog::create(array_merge($logData, [
                    'company_uuid' => session('company')
                ]));

                $inserted[] = $trackingLog;
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'inserted_count' => count($inserted),
            'error_count' => count($errors),
            'errors' => $errors,
            'logs' => $inserted
        ]);
    }

    /**
     * Get tracking statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $companyUuid = session('company');

        $query = TrackingLog::where('company_uuid', $companyUuid);

        // Filter by date range if provided
        if ($request->filled(['start_date', 'end_date'])) {
            $query->dateRange($request->input('start_date'), $request->input('end_date'));
        }

        $stats = [
            'total_logs' => $query->count(),
            'average_speed' => (clone $query)->avg('speed_kmh'),
            'max_speed' => (clone $query)->max('speed_kmh'),
            'total_distance' => (clone $query)->whereNotNull('odometer_km')->max('odometer_km') -
                (clone $query)->whereNotNull('odometer_km')->min('odometer_km'),
            'logs_by_engine_status' => (clone $query)->whereNotNull('engine_status')
                ->groupBy('engine_status')
                ->selectRaw('engine_status, count(*) as count')
                ->get(),
            'average_fuel_level' => (clone $query)->avg('fuel_level_percent'),
            'average_battery_level' => (clone $query)->avg('battery_level'),
            'average_network_signal' => (clone $query)->avg('network_signal'),
            'logs_today' => (clone $query)->whereDate('gps_timestamp', today())->count(),
            'active_buses' => (clone $query)->where('gps_timestamp', '>=', now()->subMinutes(30))
                ->distinct('bus_uuid')
                ->count('bus_uuid')
        ];

        return response()->json($stats);
    }

    /**
     * Get real-time tracking data for all active buses.
     */
    public function realtime(): JsonResponse
    {
        $companyUuid = session('company');

        // Get latest tracking log for each active bus
        $latestLogs = TrackingLog::where('company_uuid', $companyUuid)
            ->whereIn('bus_uuid', function ($query) use ($companyUuid) {
                $query->select('uuid')
                    ->from('school_transport_buses')
                    ->where('company_uuid', $companyUuid)
                    ->where('status', 'in_route');
            })
            ->where('gps_timestamp', '>=', now()->subMinutes(30))
            ->orderBy('gps_timestamp', 'desc')
            ->get()
            ->unique('bus_uuid');

        $trackingData = $latestLogs->map(function ($log) {
            return [
                'bus_id' => $log->bus_uuid,
                'bus_number' => $log->bus->bus_number ?? 'Unknown',
                'latitude' => $log->latitude,
                'longitude' => $log->longitude,
                'speed_kmh' => $log->speed_kmh,
                'heading' => $log->heading,
                'location_name' => $log->location_name,
                'engine_status' => $log->engine_status,
                'last_update' => $log->gps_timestamp,
                'is_recent' => $log->gps_timestamp->diffInMinutes(now()) <= 5
            ];
        });

        return response()->json([
            'total_active_buses' => $trackingData->count(),
            'tracking_data' => $trackingData,
            'timestamp' => now()
        ]);
    }

    /**
     * Calculate ETA for a bus to reach a specific destination.
     */
    public function calculateETA(Request $request): JsonResponse
    {
        $this->validate($request, [
            'bus_id' => 'required|string|exists:school_transport_buses,uuid',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
            'provider' => 'nullable|string|in:osrm,google,mapbox' // Added free OSRM option
        ]);

        $bus = Bus::where('uuid', $request->input('bus_id'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $etaService = app(\Fleetbase\SchoolTransportEngine\Services\ETACalculationService::class);

        $destinationCoordinates = [
            'lat' => $request->input('destination_lat'),
            'lng' => $request->input('destination_lng')
        ];

        $options = [
            'provider' => $request->input('provider', 'osrm') // Free OSRM as default
        ];

        $eta = $etaService->calculateBusETA($bus, $destinationCoordinates, $options);

        return response()->json($eta);
    }

    /**
     * Get ETA for all stops on a route.
     */
    public function getRouteETAs(Request $request, string $tripId): JsonResponse
    {
        $trip = Trip::where('uuid', $tripId)
            ->where('company_uuid', session('company'))
            ->with(['route', 'bus'])
            ->firstOrFail();

        $etaService = app(\Fleetbase\SchoolTransportEngine\Services\ETACalculationService::class);

        $options = [
            'provider' => $request->input('provider', 'osrm') // Free OSRM as default
        ];

        $routeETAs = $etaService->calculateRouteETAs($trip, $options);

        return response()->json($routeETAs);
    }

    /**
     * Get ETA for a specific stop.
     */
    public function getStopETA(Request $request, string $routeId, string $stopId): JsonResponse
    {
        // Find active trip for this route
        $trip = Trip::where('route_uuid', $routeId)
            ->where('company_uuid', session('company'))
            ->where('status', 'in_progress')
            ->with(['route', 'bus'])
            ->firstOrFail();

        $route = $trip->route;
        if (!$route->stops) {
            return response()->json([
                'success' => false,
                'error' => 'Route has no stops defined'
            ]);
        }

        // Find the specific stop
        $stop = collect($route->stops)->firstWhere('id', $stopId);
        if (!$stop) {
            return response()->json([
                'success' => false,
                'error' => 'Stop not found on route'
            ]);
        }

        $etaService = app(\Fleetbase\SchoolTransportEngine\Services\ETACalculationService::class);

        $destinationCoordinates = [
            'lat' => $stop['coordinates']['lat'],
            'lng' => $stop['coordinates']['lng']
        ];

        $options = [
            'provider' => $request->input('provider', 'osrm') // Free OSRM as default
        ];

        $eta = $etaService->calculateBusETA($trip->bus, $destinationCoordinates, $options);

        if ($eta['success']) {
            $eta['stop_id'] = $stopId;
            $eta['stop_name'] = $stop['name'];
            $eta['route_id'] = $routeId;
            $eta['trip_id'] = $trip->uuid;
        }

        return response()->json($eta);
    }

    /**
     * Check if a bus is near a stop.
     */
    public function checkProximity(Request $request): JsonResponse
    {
        $this->validate($request, [
            'bus_id' => 'required|string|exists:school_transport_buses,uuid',
            'stop_lat' => 'required|numeric|between:-90,90',
            'stop_lng' => 'required|numeric|between:-180,180',
            'threshold_km' => 'nullable|numeric|min:0.1|max:5'
        ]);

        $bus = Bus::where('uuid', $request->input('bus_id'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $etaService = app(\Fleetbase\SchoolTransportEngine\Services\ETACalculationService::class);

        $stopCoordinates = [
            'lat' => $request->input('stop_lat'),
            'lng' => $request->input('stop_lng')
        ];

        $thresholdKm = $request->input('threshold_km', 0.5);

        $isNear = $etaService->isBusNearStop($bus, $stopCoordinates, $thresholdKm);

        return response()->json([
            'bus_id' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'stop_coordinates' => $stopCoordinates,
            'threshold_km' => $thresholdKm,
            'is_near_stop' => $isNear,
            'checked_at' => now()->toISOString()
        ]);
    }

    /**
     * Get cached ETA if available.
     */
    public function getCachedETA(Request $request): JsonResponse
    {
        $this->validate($request, [
            'bus_id' => 'required|string|exists:school_transport_buses,uuid',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180'
        ]);

        $etaService = app(\Fleetbase\SchoolTransportEngine\Services\ETACalculationService::class);

        $destinationCoordinates = [
            'lat' => $request->input('destination_lat'),
            'lng' => $request->input('destination_lng')
        ];

        $cachedETA = $etaService->getCachedETA($request->input('bus_id'), $destinationCoordinates);

        if ($cachedETA) {
            return response()->json([
                'success' => true,
                'cached' => true,
                ...$cachedETA
            ]);
        }

        return response()->json([
            'success' => false,
            'cached' => false,
            'message' => 'No cached ETA available'
        ]);
    }
}
