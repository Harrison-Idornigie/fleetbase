<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\Trip;
use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\SchoolTransportEngine\Models\Driver;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TripController extends FleetbaseController
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
    public $resource = 'trip';

    /**
     * Display a listing of trips.
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

                // Filter by type
                if ($request->filled('type')) {
                    $query->byType($request->input('type'));
                }

                // Filter by bus
                if ($request->filled('bus')) {
                    $query->where('bus_uuid', $request->input('bus'));
                }

                // Filter by driver
                if ($request->filled('driver')) {
                    $query->where('driver_uuid', $request->input('driver'));
                }

                // Filter by route
                if ($request->filled('route')) {
                    $query->where('route_uuid', $request->input('route'));
                }

                // Filter by date range
                if ($request->filled(['start_date', 'end_date'])) {
                    $query->dateRange($request->input('start_date'), $request->input('end_date'));
                }

                // Filter delayed trips
                if ($request->boolean('delayed')) {
                    $query->delayed();
                }

                // Include relationships
                $query->with(['bus', 'driver', 'route', 'attendanceRecords']);
            },
            // Transform function
            function (&$trips) {
                return $trips->map(function ($trip) {
                    return [
                        'id' => $trip->uuid,
                        'public_id' => $trip->public_id,
                        'trip_id' => $trip->trip_id,
                        'bus' => $trip->bus,
                        'driver' => $trip->driver,
                        'route' => $trip->route,
                        'trip_type' => $trip->trip_type,
                        'trip_type_display' => $trip->trip_type_display,
                        'scheduled_start_time' => $trip->scheduled_start_time,
                        'scheduled_end_time' => $trip->scheduled_end_time,
                        'actual_start_time' => $trip->actual_start_time,
                        'actual_end_time' => $trip->actual_end_time,
                        'status' => $trip->status,
                        'status_display' => $trip->status_display,
                        'distance_km' => $trip->distance_km,
                        'duration' => $trip->duration,
                        'estimated_duration_minutes' => $trip->estimated_duration_minutes,
                        'actual_duration_minutes' => $trip->actual_duration_minutes,
                        'fuel_consumed_liters' => $trip->fuel_consumed_liters,
                        'is_delayed' => $trip->is_delayed,
                        'delay_reason' => $trip->delay_reason,
                        'delay_minutes' => $trip->delay_minutes,
                        'delay_status' => $trip->delay_status,
                        'weather_conditions' => $trip->weather_conditions,
                        'traffic_conditions' => $trip->traffic_conditions,
                        'incidents' => $trip->incidents,
                        'notes' => $trip->notes,
                        'attendance_count' => $trip->attendanceRecords->count(),
                        'created_at' => $trip->created_at,
                        'updated_at' => $trip->updated_at
                    ];
                });
            }
        );
    }

    /**
     * Store a newly created trip.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'bus_uuid' => 'required|exists:school_transport_buses,uuid',
            'driver_uuid' => 'required|exists:school_transport_drivers,uuid',
            'route_uuid' => 'required|exists:school_transport_routes,uuid',
            'trip_type' => 'required|in:morning_pickup,afternoon_dropoff,special',
            'scheduled_start_time' => 'required|date|after:now',
            'scheduled_end_time' => 'required|date|after:scheduled_start_time',
            'estimated_duration_minutes' => 'nullable|integer|min:1|max:480',
            'distance_km' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Verify that bus, driver, and route belong to the company
        $bus = Bus::where('uuid', $request->input('bus_uuid'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $driver = Driver::where('uuid', $request->input('driver_uuid'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $route = SchoolRoute::where('uuid', $request->input('route_uuid'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Check for scheduling conflicts
        $conflictingTrip = Trip::where('bus_uuid', $bus->uuid)
            ->where(function ($query) use ($request) {
                $query->whereBetween('scheduled_start_time', [$request->input('scheduled_start_time'), $request->input('scheduled_end_time')])
                    ->orWhereBetween('scheduled_end_time', [$request->input('scheduled_start_time'), $request->input('scheduled_end_time')])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('scheduled_start_time', '<=', $request->input('scheduled_start_time'))
                            ->where('scheduled_end_time', '>=', $request->input('scheduled_end_time'));
                    });
            })
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->first();

        if ($conflictingTrip) {
            return response()->json([
                'error' => 'Bus is already scheduled for this time period'
            ], 422);
        }

        $trip = Trip::create([
            'trip_id' => 'T' . date('Ymd') . str_pad(Trip::count() + 1, 4, '0', STR_PAD_LEFT),
            'bus_uuid' => $bus->uuid,
            'driver_uuid' => $driver->uuid,
            'route_uuid' => $route->uuid,
            'trip_type' => $request->input('trip_type'),
            'scheduled_start_time' => $request->input('scheduled_start_time'),
            'scheduled_end_time' => $request->input('scheduled_end_time'),
            'estimated_duration_minutes' => $request->input('estimated_duration_minutes'),
            'distance_km' => $request->input('distance_km'),
            'notes' => $request->input('notes'),
            'company_uuid' => session('company')
        ]);

        return response()->json([
            'trip' => $trip->load(['bus', 'driver', 'route'])
        ], 201);
    }

    /**
     * Display the specified trip.
     */
    public function show(string $id): JsonResponse
    {
        $trip = Trip::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['bus', 'driver', 'route.stops', 'attendanceRecords.student', 'trackingLogs', 'alerts'])
            ->firstOrFail();

        return response()->json([
            'trip' => [
                'id' => $trip->uuid,
                'public_id' => $trip->public_id,
                'trip_id' => $trip->trip_id,
                'bus' => $trip->bus,
                'driver' => $trip->driver,
                'route' => $trip->route,
                'trip_type' => $trip->trip_type,
                'trip_type_display' => $trip->trip_type_display,
                'scheduled_start_time' => $trip->scheduled_start_time,
                'scheduled_end_time' => $trip->scheduled_end_time,
                'actual_start_time' => $trip->actual_start_time,
                'actual_end_time' => $trip->actual_end_time,
                'status' => $trip->status,
                'status_display' => $trip->status_display,
                'distance_km' => $trip->distance_km,
                'duration' => $trip->duration,
                'estimated_duration_minutes' => $trip->estimated_duration_minutes,
                'actual_duration_minutes' => $trip->actual_duration_minutes,
                'fuel_consumed_liters' => $trip->fuel_consumed_liters,
                'is_delayed' => $trip->is_delayed,
                'delay_reason' => $trip->delay_reason,
                'delay_minutes' => $trip->delay_minutes,
                'delay_status' => $trip->delay_status,
                'weather_conditions' => $trip->weather_conditions,
                'traffic_conditions' => $trip->traffic_conditions,
                'incidents' => $trip->incidents,
                'notes' => $trip->notes,
                'attendance_records' => $trip->attendanceRecords,
                'tracking_logs' => $trip->trackingLogs->take(100),
                'alerts' => $trip->alerts,
                'created_at' => $trip->created_at,
                'updated_at' => $trip->updated_at
            ]
        ]);
    }

    /**
     * Update the specified trip.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $trip = Trip::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'bus_uuid' => 'sometimes|exists:school_transport_buses,uuid',
            'driver_uuid' => 'sometimes|exists:school_transport_drivers,uuid',
            'route_uuid' => 'sometimes|exists:school_transport_routes,uuid',
            'trip_type' => 'sometimes|in:morning_pickup,afternoon_dropoff,special',
            'scheduled_start_time' => 'sometimes|date',
            'scheduled_end_time' => 'sometimes|date',
            'actual_start_time' => 'nullable|date',
            'actual_end_time' => 'nullable|date',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled,delayed',
            'distance_km' => 'nullable|numeric|min:0',
            'estimated_duration_minutes' => 'nullable|integer|min:1|max:480',
            'actual_duration_minutes' => 'nullable|integer|min:1|max:480',
            'fuel_consumed_liters' => 'nullable|numeric|min:0',
            'is_delayed' => 'sometimes|boolean',
            'delay_reason' => 'nullable|string|max:500',
            'delay_minutes' => 'nullable|integer|min:0',
            'weather_conditions' => 'nullable|array',
            'traffic_conditions' => 'nullable|array',
            'incidents' => 'nullable|array',
            'notes' => 'nullable|string|max:1000'
        ]);

        // If changing bus/driver/route, verify they belong to the company
        if ($request->filled('bus_uuid')) {
            Bus::where('uuid', $request->input('bus_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        if ($request->filled('driver_uuid')) {
            Driver::where('uuid', $request->input('driver_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        if ($request->filled('route_uuid')) {
            SchoolRoute::where('uuid', $request->input('route_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        $trip->update($request->only([
            'bus_uuid',
            'driver_uuid',
            'route_uuid',
            'trip_type',
            'scheduled_start_time',
            'scheduled_end_time',
            'actual_start_time',
            'actual_end_time',
            'status',
            'distance_km',
            'estimated_duration_minutes',
            'actual_duration_minutes',
            'fuel_consumed_liters',
            'is_delayed',
            'delay_reason',
            'delay_minutes',
            'weather_conditions',
            'traffic_conditions',
            'incidents',
            'notes'
        ]));

        return response()->json([
            'trip' => $trip->fresh()->load(['bus', 'driver', 'route'])
        ]);
    }

    /**
     * Remove the specified trip.
     */
    public function destroy(string $id): JsonResponse
    {
        $trip = Trip::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Don't allow deletion of trips that have started or are completed
        if (in_array($trip->status, ['in_progress', 'completed'])) {
            return response()->json([
                'error' => 'Cannot delete trips that have started or are completed'
            ], 422);
        }

        $trip->delete();

        return response()->json([
            'message' => 'Trip deleted successfully'
        ]);
    }

    /**
     * Start a trip.
     */
    public function startTrip(string $id): JsonResponse
    {
        $trip = Trip::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->where('status', 'scheduled')
            ->firstOrFail();

        $trip->update([
            'status' => 'in_progress',
            'actual_start_time' => now()
        ]);

        // Update bus status
        $trip->bus->update(['status' => 'in_route']);

        return response()->json([
            'trip' => $trip->fresh()->load(['bus', 'driver', 'route']),
            'message' => 'Trip started successfully'
        ]);
    }

    /**
     * Complete a trip.
     */
    public function completeTrip(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'actual_end_time' => 'nullable|date',
            'actual_duration_minutes' => 'nullable|integer|min:1|max:480',
            'distance_km' => 'nullable|numeric|min:0',
            'fuel_consumed_liters' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        $trip = Trip::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->where('status', 'in_progress')
            ->firstOrFail();

        $trip->update([
            'status' => 'completed',
            'actual_end_time' => $request->input('actual_end_time', now()),
            'actual_duration_minutes' => $request->input('actual_duration_minutes'),
            'distance_km' => $request->input('distance_km'),
            'fuel_consumed_liters' => $request->input('fuel_consumed_liters'),
            'notes' => $request->input('notes')
        ]);

        // Update bus status back to available
        $trip->bus->update(['status' => 'available']);

        return response()->json([
            'trip' => $trip->fresh()->load(['bus', 'driver', 'route']),
            'message' => 'Trip completed successfully'
        ]);
    }

    /**
     * Mark trip as delayed.
     */
    public function markDelayed(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'delay_reason' => 'required|string|max:500',
            'delay_minutes' => 'required|integer|min:1|max:480'
        ]);

        $trip = Trip::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->firstOrFail();

        $trip->update([
            'is_delayed' => true,
            'delay_reason' => $request->input('delay_reason'),
            'delay_minutes' => $request->input('delay_minutes'),
            'status' => 'delayed'
        ]);

        return response()->json([
            'trip' => $trip->fresh(),
            'message' => 'Trip marked as delayed'
        ]);
    }

    /**
     * Get trip attendance records.
     */
    public function attendance(string $id): JsonResponse
    {
        $trip = Trip::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $attendance = $trip->attendanceRecords()
            ->with('student')
            ->orderBy('sequence_order')
            ->get();

        return response()->json([
            'trip_id' => $trip->uuid,
            'attendance_records' => $attendance
        ]);
    }

    /**
     * Get trip tracking data.
     */
    public function tracking(string $id): JsonResponse
    {
        $trip = Trip::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $trackingLogs = $trip->trackingLogs()
            ->orderBy('gps_timestamp')
            ->get();

        return response()->json([
            'trip_id' => $trip->uuid,
            'tracking_logs' => $trackingLogs
        ]);
    }

    /**
     * Get dashboard statistics for trips.
     */
    public function dashboardStats(): JsonResponse
    {
        $companyUuid = session('company');

        $stats = [
            'total_trips' => Trip::where('company_uuid', $companyUuid)->count(),
            'scheduled_trips' => Trip::where('company_uuid', $companyUuid)->byStatus('scheduled')->count(),
            'in_progress_trips' => Trip::where('company_uuid', $companyUuid)->byStatus('in_progress')->count(),
            'completed_trips' => Trip::where('company_uuid', $companyUuid)->byStatus('completed')->count(),
            'delayed_trips' => Trip::where('company_uuid', $companyUuid)->delayed()->count(),
            'trips_by_type' => Trip::where('company_uuid', $companyUuid)
                ->groupBy('trip_type')
                ->selectRaw('trip_type, count(*) as count')
                ->get(),
            'trips_by_status' => Trip::where('company_uuid', $companyUuid)
                ->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->get(),
            'average_trip_duration' => Trip::where('company_uuid', $companyUuid)->completed()->avg('actual_duration_minutes'),
            'total_distance' => Trip::where('company_uuid', $companyUuid)->completed()->sum('distance_km'),
            'on_time_percentage' => Trip::where('company_uuid', $companyUuid)->completed()->count() > 0 ?
                (Trip::where('company_uuid', $companyUuid)->completed()->where('is_delayed', false)->count() /
                    Trip::where('company_uuid', $companyUuid)->completed()->count()) * 100 : 0
        ];

        return response()->json($stats);
    }
}
