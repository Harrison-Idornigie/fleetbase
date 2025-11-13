<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Services\AlertService;
use Fleetbase\SchoolTransportEngine\Events\AlertCreated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AlertController extends FleetbaseController
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
    public $resource = 'alert';

    /**
     * The alert service instance
     *
     * @var AlertService
     */
    protected $alertService;

    /**
     * Create a new controller instance
     */
    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Display a listing of alerts.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by type
                if ($request->filled('type')) {
                    $query->byType($request->input('type'));
                }

                // Filter by severity
                if ($request->filled('severity')) {
                    $query->bySeverity($request->input('severity'));
                }

                // Filter by status
                if ($request->filled('status')) {
                    $query->byStatus($request->input('status'));
                }

                // Filter by bus
                if ($request->filled('bus')) {
                    $query->where('bus_uuid', $request->input('bus'));
                }

                // Filter by driver
                if ($request->filled('driver')) {
                    $query->where('driver_uuid', $request->input('driver'));
                }

                // Filter by trip
                if ($request->filled('trip')) {
                    $query->where('trip_uuid', $request->input('trip'));
                }

                // Filter unresolved alerts
                if ($request->boolean('unresolved')) {
                    $query->unresolved();
                }

                // Filter active alerts
                if ($request->boolean('active')) {
                    $query->active();
                }

                // Filter by date range
                if ($request->filled(['start_date', 'end_date'])) {
                    $query->dateRange($request->input('start_date'), $request->input('end_date'));
                }

                // Include relationships
                $query->with(['bus', 'driver', 'student', 'trip', 'route']);
            },
            // Transform function
            function (&$alerts) {
                return $alerts->map(function ($alert) {
                    return [
                        'id' => $alert->uuid,
                        'public_id' => $alert->public_id,
                        'alert_type' => $alert->alert_type,
                        'alert_type_display' => $alert->alert_type_display,
                        'severity' => $alert->severity,
                        'severity_display' => $alert->severity_display,
                        'title' => $alert->title,
                        'message' => $alert->message,
                        'bus' => $alert->bus,
                        'driver' => $alert->driver,
                        'student' => $alert->student,
                        'trip' => $alert->trip,
                        'route' => $alert->route,
                        'location' => $alert->location,
                        'coordinates' => $alert->coordinates,
                        'status' => $alert->status,
                        'status_display' => $alert->status_display,
                        'acknowledged_at' => $alert->acknowledged_at,
                        'acknowledged_by' => $alert->acknowledgedBy,
                        'resolved_at' => $alert->resolved_at,
                        'resolved_by' => $alert->resolvedBy,
                        'resolution_notes' => $alert->resolution_notes,
                        'time_to_resolution' => $alert->time_to_resolution,
                        'is_active' => $alert->isActive(),
                        'is_resolved' => $alert->isResolved(),
                        'created_at' => $alert->created_at,
                        'updated_at' => $alert->updated_at
                    ];
                });
            }
        );
    }

    /**
     * Store a newly created alert.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'alert_type' => 'required|in:delay,emergency,maintenance,behavior,attendance,location,system',
            'severity' => 'required|in:low,medium,high,critical',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'bus_uuid' => 'nullable|exists:school_transport_buses,uuid',
            'trip_uuid' => 'nullable|exists:school_transport_trips,uuid',
            'driver_uuid' => 'nullable|exists:school_transport_drivers,uuid',
            'student_uuid' => 'nullable|exists:school_transport_students,uuid',
            'route_uuid' => 'nullable|exists:school_transport_routes,uuid',
            'location' => 'nullable|array',
            'coordinates' => 'nullable|array',
            'coordinates.latitude' => 'nullable|numeric|between:-90,90',
            'coordinates.longitude' => 'nullable|numeric|between:-180,180'
        ]);

        // Verify that related entities belong to the company
        if ($request->filled('bus_uuid')) {
            Bus::where('uuid', $request->input('bus_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

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

        if ($request->filled('student_uuid')) {
            Student::where('uuid', $request->input('student_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        if ($request->filled('route_uuid')) {
            SchoolRoute::where('uuid', $request->input('route_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        $alert = Alert::create([
            'alert_type' => $request->input('alert_type'),
            'severity' => $request->input('severity'),
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'bus_uuid' => $request->input('bus_uuid'),
            'trip_uuid' => $request->input('trip_uuid'),
            'driver_uuid' => $request->input('driver_uuid'),
            'student_uuid' => $request->input('student_uuid'),
            'route_uuid' => $request->input('route_uuid'),
            'location' => $request->input('location'),
            'coordinates' => $request->input('coordinates'),
            'company_uuid' => session('company')
        ]);

        // Broadcast alert created event
        event(new AlertCreated($alert));

        return response()->json([
            'alert' => $alert->load(['bus', 'driver', 'student', 'trip', 'route'])
        ], 201);
    }

    /**
     * Display the specified alert.
     */
    public function show(string $id): JsonResponse
    {
        $alert = Alert::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['bus', 'driver', 'student', 'trip', 'route', 'acknowledgedBy', 'resolvedBy'])
            ->firstOrFail();

        return response()->json([
            'alert' => [
                'id' => $alert->uuid,
                'public_id' => $alert->public_id,
                'alert_type' => $alert->alert_type,
                'alert_type_display' => $alert->alert_type_display,
                'severity' => $alert->severity,
                'severity_display' => $alert->severity_display,
                'title' => $alert->title,
                'message' => $alert->message,
                'bus' => $alert->bus,
                'driver' => $alert->driver,
                'student' => $alert->student,
                'trip' => $alert->trip,
                'route' => $alert->route,
                'location' => $alert->location,
                'coordinates' => $alert->coordinates,
                'status' => $alert->status,
                'status_display' => $alert->status_display,
                'acknowledged_at' => $alert->acknowledged_at,
                'acknowledged_by' => $alert->acknowledgedBy,
                'resolved_at' => $alert->resolved_at,
                'resolved_by' => $alert->resolvedBy,
                'resolution_notes' => $alert->resolution_notes,
                'time_to_resolution' => $alert->time_to_resolution,
                'is_active' => $alert->isActive(),
                'is_resolved' => $alert->isResolved(),
                'created_at' => $alert->created_at,
                'updated_at' => $alert->updated_at
            ]
        ]);
    }

    /**
     * Update the specified alert.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $alert = Alert::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'alert_type' => 'sometimes|in:delay,emergency,maintenance,behavior,attendance,location,system',
            'severity' => 'sometimes|in:low,medium,high,critical',
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string|max:1000',
            'bus_uuid' => 'nullable|exists:school_transport_buses,uuid',
            'trip_uuid' => 'nullable|exists:school_transport_trips,uuid',
            'driver_uuid' => 'nullable|exists:school_transport_drivers,uuid',
            'student_uuid' => 'nullable|exists:school_transport_students,uuid',
            'route_uuid' => 'nullable|exists:school_transport_routes,uuid',
            'location' => 'nullable|array',
            'coordinates' => 'nullable|array',
            'coordinates.latitude' => 'nullable|numeric|between:-90,90',
            'coordinates.longitude' => 'nullable|numeric|between:-180,180'
        ]);

        // Verify that related entities belong to the company
        if ($request->filled('bus_uuid')) {
            Bus::where('uuid', $request->input('bus_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

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

        if ($request->filled('student_uuid')) {
            Student::where('uuid', $request->input('student_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        if ($request->filled('route_uuid')) {
            SchoolRoute::where('uuid', $request->input('route_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        $alert->update($request->only([
            'alert_type',
            'severity',
            'title',
            'message',
            'bus_uuid',
            'trip_uuid',
            'driver_uuid',
            'student_uuid',
            'route_uuid',
            'location',
            'coordinates'
        ]));

        return response()->json([
            'alert' => $alert->fresh()->load(['bus', 'driver', 'student', 'trip', 'route'])
        ]);
    }

    /**
     * Remove the specified alert.
     */
    public function destroy(string $id): JsonResponse
    {
        $alert = Alert::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Don't allow deletion of resolved alerts
        if ($alert->isResolved()) {
            return response()->json([
                'error' => 'Cannot delete resolved alerts'
            ], 422);
        }

        $alert->delete();

        return response()->json([
            'message' => 'Alert deleted successfully'
        ]);
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledge(string $id): JsonResponse
    {
        $alert = Alert::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->whereIn('status', ['pending'])
            ->firstOrFail();

        $alert->acknowledge();

        return response()->json([
            'alert' => $alert->fresh()->load(['acknowledgedBy']),
            'message' => 'Alert acknowledged successfully'
        ]);
    }

    /**
     * Resolve an alert.
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'resolution_notes' => 'nullable|string|max:1000'
        ]);

        $alert = Alert::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->whereIn('status', ['pending', 'acknowledged', 'investigating'])
            ->firstOrFail();

        $alert->resolve(null, $request->input('resolution_notes'));

        return response()->json([
            'alert' => $alert->fresh()->load(['resolvedBy']),
            'message' => 'Alert resolved successfully'
        ]);
    }

    /**
     * Bulk acknowledge alerts.
     */
    public function bulkAcknowledge(Request $request): JsonResponse
    {
        $this->validate($request, [
            'alert_ids' => 'required|array',
            'alert_ids.*' => 'exists:school_transport_alerts,uuid'
        ]);

        $alerts = Alert::whereIn('uuid', $request->input('alert_ids'))
            ->where('company_uuid', session('company'))
            ->where('status', 'pending')
            ->get();

        $acknowledged = 0;
        foreach ($alerts as $alert) {
            $alert->acknowledge();
            $acknowledged++;
        }

        return response()->json([
            'message' => "{$acknowledged} alerts acknowledged successfully"
        ]);
    }

    /**
     * Bulk resolve alerts.
     */
    public function bulkResolve(Request $request): JsonResponse
    {
        $this->validate($request, [
            'alert_ids' => 'required|array',
            'alert_ids.*' => 'exists:school_transport_alerts,uuid',
            'resolution_notes' => 'nullable|string|max:1000'
        ]);

        $alerts = Alert::whereIn('uuid', $request->input('alert_ids'))
            ->where('company_uuid', session('company'))
            ->whereIn('status', ['pending', 'acknowledged', 'investigating'])
            ->get();

        $resolved = 0;
        foreach ($alerts as $alert) {
            $alert->resolve(null, $request->input('resolution_notes'));
            $resolved++;
        }

        return response()->json([
            'message' => "{$resolved} alerts resolved successfully"
        ]);
    }

    /**
     * Get alert statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $companyUuid = session('company');

        $query = Alert::where('company_uuid', $companyUuid);

        // Filter by date range if provided
        if ($request->filled(['start_date', 'end_date'])) {
            $query->dateRange($request->input('start_date'), $request->input('end_date'));
        }

        $stats = [
            'total_alerts' => $query->count(),
            'pending_alerts' => (clone $query)->byStatus('pending')->count(),
            'acknowledged_alerts' => (clone $query)->byStatus('acknowledged')->count(),
            'resolved_alerts' => (clone $query)->byStatus('resolved')->count(),
            'alerts_by_type' => (clone $query)->groupBy('alert_type')
                ->selectRaw('alert_type, count(*) as count')
                ->get(),
            'alerts_by_severity' => (clone $query)->groupBy('severity')
                ->selectRaw('severity, count(*) as count')
                ->get(),
            'average_resolution_time' => (clone $query)->whereNotNull('resolved_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_time')
                ->first()->avg_time ?? 0,
            'critical_alerts' => (clone $query)->bySeverity('critical')->count(),
            'unresolved_alerts' => (clone $query)->unresolved()->count()
        ];

        return response()->json($stats);
    }

    /**
     * Get dashboard statistics for alerts.
     */
    public function dashboardStats(): JsonResponse
    {
        $companyUuid = session('company');

        $stats = [
            'total_alerts_today' => Alert::where('company_uuid', $companyUuid)
                ->whereDate('created_at', today())
                ->count(),
            'unresolved_alerts' => Alert::where('company_uuid', $companyUuid)->unresolved()->count(),
            'critical_alerts' => Alert::where('company_uuid', $companyUuid)->bySeverity('critical')->unresolved()->count(),
            'recent_alerts' => Alert::where('company_uuid', $companyUuid)
                ->with(['bus', 'driver', 'student'])
                ->latest()
                ->take(10)
                ->get(),
            'alerts_by_status' => Alert::where('company_uuid', $companyUuid)
                ->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->get(),
            'most_common_alert_types' => Alert::where('company_uuid', $companyUuid)
                ->groupBy('alert_type')
                ->selectRaw('alert_type, count(*) as count')
                ->orderBy('count', 'desc')
                ->take(5)
                ->get()
        ];

        return response()->json($stats);
    }
}
