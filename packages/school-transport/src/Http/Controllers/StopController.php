<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StopController extends FleetbaseController
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
    public $resource = 'stop';

    /**
     * Display a listing of stops.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by route
                if ($request->filled('route')) {
                    $query->where('school_route_uuid', $request->input('route'));
                }

                // Filter by stop type
                if ($request->filled('type')) {
                    $query->where('type', $request->input('type'));
                }

                // Search by name or address
                if ($request->filled('search')) {
                    $search = $request->input('search');
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%")
                            ->orWhere('landmark', 'like', "%{$search}%");
                    });
                }

                // Include relationships
                $query->with(['route', 'students']);
            },
            // Transform function
            function (&$stops) {
                return $stops->map(function ($stop) {
                    return [
                        'id' => $stop->uuid,
                        'public_id' => $stop->public_id,
                        'name' => $stop->name,
                        'type' => $stop->type,
                        'type_display' => $stop->type_display,
                        'latitude' => $stop->latitude,
                        'longitude' => $stop->longitude,
                        'coordinates' => $stop->coordinates,
                        'address' => $stop->address,
                        'landmark' => $stop->landmark,
                        'estimated_arrival_time' => $stop->estimated_arrival_time,
                        'estimated_departure_time' => $stop->estimated_departure_time,
                        'sequence' => $stop->sequence,
                        'route' => $stop->route ? [
                            'id' => $stop->route->uuid,
                            'name' => $stop->route->name,
                            'route_id' => $stop->route->route_id
                        ] : null,
                        'students_count' => $stop->students->count(),
                        'students' => $stop->students->map(function ($student) {
                            return [
                                'id' => $student->uuid,
                                'first_name' => $student->first_name,
                                'last_name' => $student->last_name,
                                'student_id' => $student->student_id
                            ];
                        }),
                        'created_at' => $stop->created_at
                    ];
                });
            }
        );
    }

    /**
     * Store a new stop.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'type' => 'required|in:pickup,dropoff,both',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'required|string|max:500',
            'landmark' => 'nullable|string|max:255',
            'estimated_arrival_time' => 'nullable|date_format:H:i',
            'estimated_departure_time' => 'nullable|date_format:H:i',
            'sequence' => 'nullable|integer|min:1',
            'school_route_uuid' => 'required|exists:school_transport_school_routes,uuid',
            'student_uuids' => 'nullable|array',
            'student_uuids.*' => 'exists:school_transport_students,uuid'
        ]);

        // Verify that route belongs to the company
        $route = SchoolRoute::where('uuid', $request->input('school_route_uuid'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Verify that all students belong to the company
        if ($request->filled('student_uuids')) {
            foreach ($request->input('student_uuids') as $studentUuid) {
                Student::where('uuid', $studentUuid)
                    ->where('company_uuid', session('company'))
                    ->firstOrFail();
            }
        }

        $stop = Stop::create([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'address' => $request->input('address'),
            'landmark' => $request->input('landmark'),
            'estimated_arrival_time' => $request->input('estimated_arrival_time'),
            'estimated_departure_time' => $request->input('estimated_departure_time'),
            'sequence' => $request->input('sequence'),
            'school_route_uuid' => $route->uuid,
            'company_uuid' => session('company')
        ]);

        // Attach students if provided
        if ($request->filled('student_uuids')) {
            $stop->students()->attach($request->input('student_uuids'));
        }

        return response()->json([
            'stop' => $stop->load(['route', 'students'])
        ], 201);
    }

    /**
     * Display the specified stop.
     */
    public function show(string $id): JsonResponse
    {
        $stop = Stop::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['route', 'students'])
            ->firstOrFail();

        return response()->json([
            'stop' => [
                'id' => $stop->uuid,
                'public_id' => $stop->public_id,
                'name' => $stop->name,
                'type' => $stop->type,
                'type_display' => $stop->type_display,
                'latitude' => $stop->latitude,
                'longitude' => $stop->longitude,
                'coordinates' => $stop->coordinates,
                'address' => $stop->address,
                'landmark' => $stop->landmark,
                'estimated_arrival_time' => $stop->estimated_arrival_time,
                'estimated_departure_time' => $stop->estimated_departure_time,
                'sequence' => $stop->sequence,
                'route' => $stop->route ? [
                    'id' => $stop->route->uuid,
                    'name' => $stop->route->name,
                    'route_id' => $stop->route->route_id,
                    'direction' => $stop->route->direction
                ] : null,
                'students' => $stop->students->map(function ($student) {
                    return [
                        'id' => $student->uuid,
                        'public_id' => $student->public_id,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'student_id' => $student->student_id,
                        'grade' => $student->grade,
                        'school' => $student->school
                    ];
                }),
                'created_at' => $stop->created_at,
                'updated_at' => $stop->updated_at
            ]
        ]);
    }

    /**
     * Update the specified stop.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $stop = Stop::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:pickup,dropoff,both',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'address' => 'sometimes|required|string|max:500',
            'landmark' => 'nullable|string|max:255',
            'estimated_arrival_time' => 'nullable|date_format:H:i',
            'estimated_departure_time' => 'nullable|date_format:H:i',
            'sequence' => 'nullable|integer|min:1',
            'school_route_uuid' => 'sometimes|required|exists:school_transport_school_routes,uuid',
            'student_uuids' => 'nullable|array',
            'student_uuids.*' => 'exists:school_transport_students,uuid'
        ]);

        // Verify that route belongs to the company if provided
        if ($request->filled('school_route_uuid')) {
            SchoolRoute::where('uuid', $request->input('school_route_uuid'))
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        // Verify that all students belong to the company
        if ($request->filled('student_uuids')) {
            foreach ($request->input('student_uuids') as $studentUuid) {
                Student::where('uuid', $studentUuid)
                    ->where('company_uuid', session('company'))
                    ->firstOrFail();
            }
        }

        $stop->update($request->only([
            'name',
            'type',
            'latitude',
            'longitude',
            'address',
            'landmark',
            'estimated_arrival_time',
            'estimated_departure_time',
            'sequence',
            'school_route_uuid'
        ]));

        // Sync students if provided
        if ($request->has('student_uuids')) {
            $stop->students()->sync($request->input('student_uuids'));
        }

        return response()->json([
            'stop' => $stop->load(['route', 'students'])
        ]);
    }

    /**
     * Remove the specified stop.
     */
    public function destroy(string $id): JsonResponse
    {
        $stop = Stop::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $stop->delete();

        return response()->json([
            'message' => 'Stop deleted successfully'
        ]);
    }

    /**
     * Get stops for a specific route.
     */
    public function byRoute(string $routeId): JsonResponse
    {
        $route = SchoolRoute::where('uuid', $routeId)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $stops = $route->stops()
            ->orderBy('sequence')
            ->with(['students'])
            ->get();

        return response()->json([
            'route' => [
                'id' => $route->uuid,
                'name' => $route->name,
                'route_id' => $route->route_id,
                'direction' => $route->direction
            ],
            'stops' => $stops->map(function ($stop) {
                return [
                    'id' => $stop->uuid,
                    'name' => $stop->name,
                    'type' => $stop->type_display,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'address' => $stop->address,
                    'landmark' => $stop->landmark,
                    'estimated_arrival_time' => $stop->estimated_arrival_time,
                    'estimated_departure_time' => $stop->estimated_departure_time,
                    'sequence' => $stop->sequence,
                    'students_count' => $stop->students->count()
                ];
            })
        ]);
    }

    /**
     * Add students to a stop.
     */
    public function addStudents(Request $request, string $id): JsonResponse
    {
        $stop = Stop::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'student_uuids' => 'required|array|min:1',
            'student_uuids.*' => 'exists:school_transport_students,uuid'
        ]);

        // Verify that all students belong to the company
        foreach ($request->input('student_uuids') as $studentUuid) {
            Student::where('uuid', $studentUuid)
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        $stop->students()->attach($request->input('student_uuids'));

        return response()->json([
            'stop' => $stop->load('students'),
            'message' => 'Students added to stop successfully'
        ]);
    }

    /**
     * Remove students from a stop.
     */
    public function removeStudents(Request $request, string $id): JsonResponse
    {
        $stop = Stop::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'student_uuids' => 'required|array|min:1',
            'student_uuids.*' => 'exists:school_transport_students,uuid'
        ]);

        $stop->students()->detach($request->input('student_uuids'));

        return response()->json([
            'stop' => $stop->load('students'),
            'message' => 'Students removed from stop successfully'
        ]);
    }

    /**
     * Update stop sequence for a route.
     */
    public function updateSequence(Request $request, string $routeId): JsonResponse
    {
        $route = SchoolRoute::where('uuid', $routeId)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'stops' => 'required|array',
            'stops.*.id' => 'required|exists:school_transport_stops,uuid',
            'stops.*.sequence' => 'required|integer|min:1'
        ]);

        $updatedStops = [];

        foreach ($request->input('stops') as $stopData) {
            $stop = Stop::where('uuid', $stopData['id'])
                ->where('school_route_uuid', $route->uuid)
                ->firstOrFail();

            $stop->update(['sequence' => $stopData['sequence']]);
            $updatedStops[] = $stop;
        }

        return response()->json([
            'route' => $route,
            'stops' => $updatedStops,
            'message' => 'Stop sequence updated successfully'
        ]);
    }

    /**
     * Get stops near a location.
     */
    public function nearby(Request $request): JsonResponse
    {
        $this->validate($request, [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $radiusKm = $request->input('radius_km', 5);
        $limit = $request->input('limit', 20);

        // Using Haversine formula for distance calculation
        $stops = Stop::where('company_uuid', session('company'))
            ->selectRaw("
                *,
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance
            ", [$latitude, $longitude, $latitude])
            ->having('distance', '<=', $radiusKm)
            ->orderBy('distance')
            ->limit($limit)
            ->with(['route', 'students'])
            ->get();

        return response()->json([
            'center' => [
                'latitude' => $latitude,
                'longitude' => $longitude
            ],
            'radius_km' => $radiusKm,
            'stops' => $stops->map(function ($stop) {
                return [
                    'id' => $stop->uuid,
                    'name' => $stop->name,
                    'type' => $stop->type_display,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'address' => $stop->address,
                    'landmark' => $stop->landmark,
                    'distance_km' => round($stop->distance, 2),
                    'route' => $stop->route ? [
                        'id' => $stop->route->uuid,
                        'name' => $stop->route->name
                    ] : null,
                    'students_count' => $stop->students->count()
                ];
            })
        ]);
    }

    /**
     * Get pickup stops.
     */
    public function pickups(): JsonResponse
    {
        $stops = Stop::where('company_uuid', session('company'))
            ->whereIn('type', ['pickup', 'both'])
            ->with(['route', 'students'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'pickup_stops' => $stops->map(function ($stop) {
                return [
                    'id' => $stop->uuid,
                    'name' => $stop->name,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'address' => $stop->address,
                    'landmark' => $stop->landmark,
                    'estimated_arrival_time' => $stop->estimated_arrival_time,
                    'route' => $stop->route ? [
                        'id' => $stop->route->uuid,
                        'name' => $stop->route->name
                    ] : null,
                    'students_count' => $stop->students->count()
                ];
            })
        ]);
    }

    /**
     * Get dropoff stops.
     */
    public function dropoffs(): JsonResponse
    {
        $stops = Stop::where('company_uuid', session('company'))
            ->whereIn('type', ['dropoff', 'both'])
            ->with(['route', 'students'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'dropoff_stops' => $stops->map(function ($stop) {
                return [
                    'id' => $stop->uuid,
                    'name' => $stop->name,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'address' => $stop->address,
                    'landmark' => $stop->landmark,
                    'estimated_departure_time' => $stop->estimated_departure_time,
                    'route' => $stop->route ? [
                        'id' => $stop->route->uuid,
                        'name' => $stop->route->name
                    ] : null,
                    'students_count' => $stop->students->count()
                ];
            })
        ]);
    }
}
