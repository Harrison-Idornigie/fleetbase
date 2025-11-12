<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\SchoolTransportEngine\Models\Driver;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BusController extends FleetbaseController
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
    public $resource = 'bus';

    /**
     * Display a listing of buses.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by status
                if ($request->filled('status')) {
                    $query->byStatus($request->input('status'));
                }

                // Filter by active status
                if ($request->filled('active')) {
                    $query->where('is_active', $request->boolean('active'));
                }

                // Filter by driver
                if ($request->filled('driver')) {
                    $query->where('driver_uuid', $request->input('driver'));
                }

                // Filter by route
                if ($request->filled('route')) {
                    $query->where('route_uuid', $request->input('route'));
                }

                // Search by bus number or license plate
                if ($request->filled('search')) {
                    $search = $request->input('search');
                    $query->where(function ($q) use ($search) {
                        $q->where('bus_number', 'like', "%{$search}%")
                            ->orWhere('license_plate', 'like', "%{$search}%");
                    });
                }

                // Include relationships
                $query->with(['driver', 'route', 'assignments']);
            },
            // Transform function
            function (&$buses) {
                return $buses->map(function ($bus) {
                    return [
                        'id' => $bus->uuid,
                        'public_id' => $bus->public_id,
                        'bus_number' => $bus->bus_number,
                        'license_plate' => $bus->license_plate,
                        'make' => $bus->make,
                        'model' => $bus->model,
                        'year' => $bus->year,
                        'capacity' => $bus->capacity,
                        'current_occupancy' => $bus->current_occupancy,
                        'available_seats' => $bus->available_seats,
                        'status' => $bus->status,
                        'status_display' => $bus->status_display,
                        'driver' => $bus->driver,
                        'route' => $bus->route,
                        'last_maintenance_date' => $bus->last_maintenance_date,
                        'next_maintenance_date' => $bus->next_maintenance_date,
                        'needs_maintenance' => $bus->needsMaintenance(),
                        'insurance_expired' => $bus->insuranceExpired(),
                        'registration_expired' => $bus->registrationExpired(),
                        'is_active' => $bus->is_active,
                        'created_at' => $bus->created_at,
                        'updated_at' => $bus->updated_at
                    ];
                });
            }
        );
    }

    /**
     * Store a newly created bus.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'bus_number' => 'required|string|max:50|unique:school_transport_buses,bus_number',
            'license_plate' => 'required|string|max:20|unique:school_transport_buses,license_plate',
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'capacity' => 'required|integer|min:1|max:100',
            'fuel_type' => 'nullable|in:gasoline,diesel,electric,hybrid',
            'color' => 'nullable|string|max:50',
            'features' => 'nullable|array',
            'gps_device_id' => 'nullable|string|max:100',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date|after:last_maintenance_date',
            'insurance_expiry' => 'nullable|date|after:today',
            'registration_expiry' => 'nullable|date|after:today'
        ]);

        $bus = Bus::create([
            'bus_number' => $request->input('bus_number'),
            'license_plate' => $request->input('license_plate'),
            'make' => $request->input('make'),
            'model' => $request->input('model'),
            'year' => $request->input('year'),
            'capacity' => $request->input('capacity'),
            'fuel_type' => $request->input('fuel_type', 'diesel'),
            'color' => $request->input('color'),
            'features' => $request->input('features', []),
            'gps_device_id' => $request->input('gps_device_id'),
            'last_maintenance_date' => $request->input('last_maintenance_date'),
            'next_maintenance_date' => $request->input('next_maintenance_date'),
            'insurance_expiry' => $request->input('insurance_expiry'),
            'registration_expiry' => $request->input('registration_expiry'),
            'company_uuid' => session('company')
        ]);

        return response()->json([
            'bus' => $bus->load(['driver', 'route'])
        ], 201);
    }

    /**
     * Display the specified bus.
     */
    public function show(string $id): JsonResponse
    {
        $bus = Bus::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['driver', 'route', 'assignments.student', 'trips' => function ($query) {
                $query->latest()->take(10);
            }, 'trackingLogs' => function ($query) {
                $query->latest()->take(50);
            }])
            ->firstOrFail();

        return response()->json([
            'bus' => [
                'id' => $bus->uuid,
                'public_id' => $bus->public_id,
                'bus_number' => $bus->bus_number,
                'license_plate' => $bus->license_plate,
                'make' => $bus->make,
                'model' => $bus->model,
                'year' => $bus->year,
                'capacity' => $bus->capacity,
                'current_occupancy' => $bus->current_occupancy,
                'available_seats' => $bus->available_seats,
                'status' => $bus->status,
                'status_display' => $bus->status_display,
                'fuel_type' => $bus->fuel_type,
                'mileage' => $bus->mileage,
                'color' => $bus->color,
                'features' => $bus->features,
                'gps_device_id' => $bus->gps_device_id,
                'driver' => $bus->driver,
                'route' => $bus->route,
                'last_maintenance_date' => $bus->last_maintenance_date,
                'next_maintenance_date' => $bus->next_maintenance_date,
                'insurance_expiry' => $bus->insurance_expiry,
                'registration_expiry' => $bus->registration_expiry,
                'needs_maintenance' => $bus->needsMaintenance(),
                'insurance_expired' => $bus->insuranceExpired(),
                'registration_expired' => $bus->registrationExpired(),
                'is_active' => $bus->is_active,
                'assignments' => $bus->assignments,
                'recent_trips' => $bus->trips,
                'recent_tracking' => $bus->trackingLogs,
                'created_at' => $bus->created_at,
                'updated_at' => $bus->updated_at
            ]
        ]);
    }

    /**
     * Update the specified bus.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $bus = Bus::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'bus_number' => 'sometimes|string|max:50|unique:school_transport_buses,bus_number,' . $bus->id . ',id',
            'license_plate' => 'sometimes|string|max:20|unique:school_transport_buses,license_plate,' . $bus->id . ',id',
            'make' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'capacity' => 'sometimes|integer|min:1|max:100',
            'fuel_type' => 'nullable|in:gasoline,diesel,electric,hybrid',
            'color' => 'sometimes|string|max:50',
            'features' => 'nullable|array',
            'gps_device_id' => 'sometimes|string|max:100',
            'driver_uuid' => 'nullable|exists:school_transport_drivers,uuid',
            'route_uuid' => 'nullable|exists:school_transport_routes,uuid',
            'status' => 'sometimes|in:available,in_route,maintenance,out_of_service',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'insurance_expiry' => 'nullable|date',
            'registration_expiry' => 'nullable|date',
            'is_active' => 'sometimes|boolean'
        ]);

        $bus->update($request->only([
            'bus_number',
            'license_plate',
            'make',
            'model',
            'year',
            'capacity',
            'fuel_type',
            'color',
            'features',
            'gps_device_id',
            'driver_uuid',
            'route_uuid',
            'status',
            'last_maintenance_date',
            'next_maintenance_date',
            'insurance_expiry',
            'registration_expiry',
            'is_active'
        ]));

        return response()->json([
            'bus' => $bus->fresh()->load(['driver', 'route'])
        ]);
    }

    /**
     * Remove the specified bus.
     */
    public function destroy(string $id): JsonResponse
    {
        $bus = Bus::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Check if bus has active assignments or trips
        if ($bus->assignments()->exists() || $bus->trips()->where('status', 'in_progress')->exists()) {
            return response()->json([
                'error' => 'Cannot delete bus with active assignments or ongoing trips'
            ], 422);
        }

        $bus->delete();

        return response()->json([
            'message' => 'Bus deleted successfully'
        ]);
    }

    /**
     * Assign driver to bus.
     */
    public function assignDriver(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'driver_uuid' => 'required|exists:school_transport_drivers,uuid'
        ]);

        $bus = Bus::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $driver = Driver::where('uuid', $request->input('driver_uuid'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Check if driver is already assigned to another bus
        if ($driver->buses()->where('uuid', '!=', $bus->uuid)->exists()) {
            return response()->json([
                'error' => 'Driver is already assigned to another bus'
            ], 422);
        }

        $bus->update([
            'driver_uuid' => $driver->uuid,
            'status' => 'available'
        ]);

        return response()->json([
            'bus' => $bus->fresh()->load('driver'),
            'message' => 'Driver assigned successfully'
        ]);
    }

    /**
     * Unassign driver from bus.
     */
    public function unassignDriver(string $id): JsonResponse
    {
        $bus = Bus::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $bus->update([
            'driver_uuid' => null,
            'status' => 'available'
        ]);

        return response()->json([
            'bus' => $bus->fresh(),
            'message' => 'Driver unassigned successfully'
        ]);
    }

    /**
     * Assign route to bus.
     */
    public function assignRoute(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'route_uuid' => 'required|exists:school_transport_routes,uuid'
        ]);

        $bus = Bus::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $route = SchoolRoute::where('uuid', $request->input('route_uuid'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $bus->update([
            'route_uuid' => $route->uuid
        ]);

        return response()->json([
            'bus' => $bus->fresh()->load('route'),
            'message' => 'Route assigned successfully'
        ]);
    }

    /**
     * Get bus location and tracking data.
     */
    public function location(string $id): JsonResponse
    {
        $bus = Bus::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $latestTracking = $bus->trackingLogs()
            ->latest()
            ->first();

        return response()->json([
            'bus_id' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'latest_location' => $latestTracking ? [
                'latitude' => $latestTracking->latitude,
                'longitude' => $latestTracking->longitude,
                'speed_kmh' => $latestTracking->speed_kmh,
                'heading' => $latestTracking->heading,
                'timestamp' => $latestTracking->gps_timestamp,
                'location_name' => $latestTracking->location_name
            ] : null
        ]);
    }

    /**
     * Get dashboard statistics for buses.
     */
    public function dashboardStats(): JsonResponse
    {
        $companyUuid = session('company');

        $stats = [
            'total_buses' => Bus::where('company_uuid', $companyUuid)->count(),
            'active_buses' => Bus::where('company_uuid', $companyUuid)->active()->count(),
            'available_buses' => Bus::where('company_uuid', $companyUuid)->available()->count(),
            'buses_in_route' => Bus::where('company_uuid', $companyUuid)->byStatus('in_route')->count(),
            'buses_needing_maintenance' => Bus::where('company_uuid', $companyUuid)->get()->filter(function ($bus) {
                return $bus->needsMaintenance();
            })->count(),
            'buses_by_status' => Bus::where('company_uuid', $companyUuid)
                ->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->get(),
            'average_capacity' => Bus::where('company_uuid', $companyUuid)->avg('capacity'),
            'total_capacity' => Bus::where('company_uuid', $companyUuid)->sum('capacity')
        ];

        return response()->json($stats);
    }
}
