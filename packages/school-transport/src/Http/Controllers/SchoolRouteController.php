<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\BusAssignment;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SchoolRouteController extends FleetbaseController
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
    public $resource = 'school_route';

    /**
     * Display a listing of school routes.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by school
                if ($request->filled('school')) {
                    $query->forSchool($request->input('school'));
                }

                // Filter by route type
                if ($request->filled('type')) {
                    $query->byType($request->input('type'));
                }

                // Filter by status
                if ($request->filled('status')) {
                    $query->where('status', $request->input('status'));
                }

                // Filter active routes
                if ($request->boolean('active')) {
                    $query->active();
                }

                // Filter wheelchair accessible routes
                if ($request->boolean('wheelchair_accessible')) {
                    $query->wheelchairAccessible();
                }

                // Include relationships
                $query->with(['busAssignments.student', 'communications']);
            },
            // Transform function
            function (&$routes) {
                return $routes->map(function ($route) {
                    return [
                        'id' => $route->uuid,
                        'public_id' => $route->public_id,
                        'route_name' => $route->route_name,
                        'route_number' => $route->route_number,
                        'school' => $route->school,
                        'route_type' => $route->route_type,
                        'start_time' => $route->start_time,
                        'end_time' => $route->end_time,
                        'estimated_duration' => $route->estimated_duration,
                        'estimated_distance' => $route->estimated_distance,
                        'capacity' => $route->capacity,
                        'wheelchair_accessible' => $route->wheelchair_accessible,
                        'assigned_students_count' => $route->assigned_students_count,
                        'available_capacity' => $route->available_capacity,
                        'utilization_percentage' => $route->utilization_percentage,
                        'efficiency_score' => $route->getEfficiencyScore(),
                        'is_current' => $route->is_current,
                        'status' => $route->status,
                        'is_active' => $route->is_active,
                        'days_of_week' => $route->days_of_week,
                        'operates_today' => $route->operatesToday(),
                        'created_at' => $route->created_at,
                        'updated_at' => $route->updated_at
                    ];
                });
            }
        );
    }

    /**
     * Store a newly created route.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'route_name' => 'required|string|max:255',
            'route_number' => 'nullable|string|max:50',
            'school' => 'required|string',
            'route_type' => 'required|in:pickup,dropoff,both',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'capacity' => 'required|integer|min:1|max:100',
            'stops' => 'required|array|min:1',
            'stops.*.name' => 'required|string',
            'stops.*.coordinates' => 'required|array',
            'stops.*.time' => 'required|date_format:H:i',
            'vehicle_uuid' => 'nullable|uuid',
            'driver_uuid' => 'nullable|uuid',
            'wheelchair_accessible' => 'boolean',
            'days_of_week' => 'required|array',
            'effective_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:effective_date'
        ]);

        // Calculate estimated duration
        $startTime = \Carbon\Carbon::createFromFormat('H:i', $request->input('start_time'));
        $endTime = \Carbon\Carbon::createFromFormat('H:i', $request->input('end_time'));
        $estimatedDuration = $endTime->diffInMinutes($startTime);

        $route = SchoolRoute::create([
            'route_name' => $request->input('route_name'),
            'route_number' => $request->input('route_number'),
            'description' => $request->input('description'),
            'school' => $request->input('school'),
            'route_type' => $request->input('route_type'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'estimated_duration' => $estimatedDuration,
            'stops' => $request->input('stops'),
            'vehicle_uuid' => $request->input('vehicle_uuid'),
            'driver_uuid' => $request->input('driver_uuid'),
            'capacity' => $request->input('capacity'),
            'wheelchair_accessible' => $request->boolean('wheelchair_accessible'),
            'days_of_week' => $request->input('days_of_week'),
            'effective_date' => $request->input('effective_date'),
            'end_date' => $request->input('end_date'),
            'special_instructions' => $request->input('special_instructions'),
            'company_uuid' => session('company')
        ]);

        return response()->json([
            'route' => $route->load('busAssignments')
        ], 201);
    }

    /**
     * Display the specified route.
     */
    public function show(string $id): JsonResponse
    {
        $route = SchoolRoute::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['busAssignments.student', 'attendanceRecords', 'communications'])
            ->firstOrFail();

        return response()->json([
            'route' => [
                'id' => $route->uuid,
                'public_id' => $route->public_id,
                'route_name' => $route->route_name,
                'route_number' => $route->route_number,
                'description' => $route->description,
                'school' => $route->school,
                'route_type' => $route->route_type,
                'start_time' => $route->start_time,
                'end_time' => $route->end_time,
                'estimated_duration' => $route->estimated_duration,
                'estimated_distance' => $route->estimated_distance,
                'formatted_duration' => $route->getFormattedDuration(),
                'stops' => $route->stops,
                'waypoints' => $route->waypoints,
                'vehicle_uuid' => $route->vehicle_uuid,
                'driver_uuid' => $route->driver_uuid,
                'capacity' => $route->capacity,
                'wheelchair_accessible' => $route->wheelchair_accessible,
                'assigned_students_count' => $route->assigned_students_count,
                'available_capacity' => $route->available_capacity,
                'utilization_percentage' => $route->utilization_percentage,
                'efficiency_score' => $route->getEfficiencyScore(),
                'is_current' => $route->is_current,
                'is_overutilized' => $route->isOverutilized(),
                'status' => $route->status,
                'is_active' => $route->is_active,
                'days_of_week' => $route->days_of_week,
                'operates_today' => $route->operatesToday(),
                'next_stop' => $route->next_stop,
                'effective_date' => $route->effective_date,
                'end_date' => $route->end_date,
                'special_instructions' => $route->special_instructions,
                'bus_assignments' => $route->busAssignments,
                'students' => $route->students,
                'created_at' => $route->created_at,
                'updated_at' => $route->updated_at
            ]
        ]);
    }

    /**
     * Update the specified route.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $route = SchoolRoute::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'route_name' => 'sometimes|string|max:255',
            'route_number' => 'nullable|string|max:50',
            'school' => 'sometimes|string',
            'route_type' => 'sometimes|in:pickup,dropoff,both',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'capacity' => 'sometimes|integer|min:1|max:100',
            'stops' => 'sometimes|array|min:1',
            'wheelchair_accessible' => 'boolean',
            'days_of_week' => 'sometimes|array',
            'status' => 'sometimes|in:draft,active,suspended,archived'
        ]);

        // Recalculate duration if times change
        if ($request->has('start_time') || $request->has('end_time')) {
            $startTime = \Carbon\Carbon::createFromFormat(
                'H:i',
                $request->input('start_time', $route->start_time)
            );
            $endTime = \Carbon\Carbon::createFromFormat(
                'H:i',
                $request->input('end_time', $route->end_time)
            );
            $request->merge(['estimated_duration' => $endTime->diffInMinutes($startTime)]);
        }

        $route->update($request->only([
            'route_name',
            'route_number',
            'description',
            'school',
            'route_type',
            'start_time',
            'end_time',
            'estimated_duration',
            'stops',
            'vehicle_uuid',
            'driver_uuid',
            'capacity',
            'wheelchair_accessible',
            'is_active',
            'status',
            'days_of_week',
            'effective_date',
            'end_date',
            'special_instructions'
        ]));

        return response()->json([
            'route' => $route->fresh()->load('busAssignments')
        ]);
    }

    /**
     * Remove the specified route.
     */
    public function destroy(string $id): JsonResponse
    {
        $route = SchoolRoute::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Check if route has active assignments
        if ($route->activeBusAssignments()->exists()) {
            return response()->json([
                'error' => 'Cannot delete route with active student assignments'
            ], 422);
        }

        $route->delete();

        return response()->json([
            'message' => 'Route deleted successfully'
        ]);
    }

    /**
     * Get students assigned to the route.
     */
    public function students(string $id): JsonResponse
    {
        $route = SchoolRoute::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $students = $route->students()
            ->with(['busAssignments' => function ($query) use ($id) {
                $query->where('route_uuid', $id)->active();
            }])
            ->get();

        return response()->json([
            'students' => $students->map(function ($student) {
                $assignment = $student->busAssignments->first();
                return [
                    'id' => $student->uuid,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'grade' => $student->grade,
                    'school' => $student->school,
                    'has_special_needs' => $student->has_special_needs,
                    'pickup_stop' => $assignment->pickup_stop ?? '',
                    'pickup_time' => $assignment->pickup_time ?? '',
                    'stop_sequence' => $assignment->stop_sequence ?? 0,
                    'requires_assistance' => $assignment->requires_assistance ?? false,
                    'attendance_rate' => $assignment->attendance_rate ?? 0
                ];
            })
        ]);
    }

    /**
     * Optimize route stops order.
     */
    public function optimizeRoute(Request $request, string $id): JsonResponse
    {
        $route = SchoolRoute::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // This would integrate with a routing optimization service
        // For now, we'll return a mock optimized route
        $optimizedStops = $route->optimizeStops();

        // Update route with optimized waypoints
        $route->update(['waypoints' => $optimizedStops]);

        return response()->json([
            'message' => 'Route optimized successfully',
            'optimized_stops' => $optimizedStops,
            'estimated_savings' => [
                'time_minutes' => rand(5, 15),
                'distance_miles' => round(rand(1, 5) * 0.1, 1),
                'fuel_cost' => round(rand(2, 8) * 0.5, 2)
            ]
        ]);
    }

    /**
     * Track route progress.
     */
    public function trackRoute(string $id): JsonResponse
    {
        $route = SchoolRoute::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        if (!$route->operatesToday()) {
            return response()->json([
                'message' => 'Route does not operate today',
                'tracking' => null
            ]);
        }

        // Mock tracking data - in reality would come from GPS/fleet tracking
        $tracking = [
            'route_id' => $route->uuid,
            'route_name' => $route->route_name,
            'status' => 'in_progress', // not_started, in_progress, completed
            'current_stop' => 3,
            'total_stops' => count($route->stops ?? []),
            'next_stop' => $route->next_stop,
            'estimated_arrival' => now()->addMinutes(rand(5, 20))->toISOString(),
            'delay_minutes' => rand(-2, 8),
            'students_picked_up' => rand(8, 15),
            'students_remaining' => rand(3, 8),
            'last_updated' => now()->toISOString()
        ];

        return response()->json([
            'tracking' => $tracking
        ]);
    }

    /**
     * Get route efficiency metrics.
     */
    public function routeEfficiency(): JsonResponse
    {
        $companyUuid = session('company');

        $routes = SchoolRoute::where('company_uuid', $companyUuid)
            ->active()
            ->with('busAssignments')
            ->get();

        $efficiency = [
            'overall_efficiency' => $routes->avg(function ($route) {
                return $route->getEfficiencyScore();
            }),
            'total_routes' => $routes->count(),
            'overutilized_routes' => $routes->filter(function ($route) {
                return $route->isOverutilized();
            })->count(),
            'underutilized_routes' => $routes->filter(function ($route) {
                return $route->utilization_percentage < 60;
            })->count(),
            'average_utilization' => $routes->avg('utilization_percentage'),
            'routes_by_efficiency' => $routes->map(function ($route) {
                return [
                    'id' => $route->uuid,
                    'name' => $route->route_name,
                    'school' => $route->school,
                    'efficiency_score' => $route->getEfficiencyScore(),
                    'utilization_percentage' => $route->utilization_percentage,
                    'student_count' => $route->assigned_students_count,
                    'capacity' => $route->capacity
                ];
            })->sortByDesc('efficiency_score')->values()
        ];

        return response()->json($efficiency);
    }
}
