<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\BusAssignment;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BusAssignmentController extends FleetbaseController
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
    public $resource = 'bus_assignment';

    /**
     * Display a listing of bus assignments.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by route
                if ($request->filled('route_id')) {
                    $query->forRoute($request->input('route_id'));
                }

                // Filter by student
                if ($request->filled('student_id')) {
                    $query->forStudent($request->input('student_id'));
                }

                // Filter by assignment type
                if ($request->filled('type')) {
                    $query->byType($request->input('type'));
                }

                // Filter by status
                if ($request->filled('status')) {
                    $query->where('status', $request->input('status'));
                }

                // Filter active assignments
                if ($request->boolean('active')) {
                    $query->active();
                }

                // Filter assignments requiring assistance
                if ($request->boolean('requires_assistance')) {
                    $query->requiringAssistance();
                }

                // Include relationships
                $query->with(['student', 'route', 'attendanceRecords']);
            },
            // Transform function
            function (&$assignments) {
                return $assignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->uuid,
                        'public_id' => $assignment->public_id,
                        'student' => [
                            'id' => $assignment->student->uuid,
                            'full_name' => $assignment->student->full_name,
                            'student_id' => $assignment->student->student_id,
                            'grade' => $assignment->student->grade,
                            'school' => $assignment->student->school,
                            'has_special_needs' => $assignment->student->has_special_needs
                        ],
                        'route' => [
                            'id' => $assignment->route->uuid,
                            'route_name' => $assignment->route->route_name,
                            'route_number' => $assignment->route->route_number,
                            'school' => $assignment->route->school,
                            'route_type' => $assignment->route->route_type
                        ],
                        'stop_sequence' => $assignment->stop_sequence,
                        'pickup_stop' => $assignment->pickup_stop,
                        'pickup_time' => $assignment->pickup_time,
                        'dropoff_stop' => $assignment->dropoff_stop,
                        'dropoff_time' => $assignment->dropoff_time,
                        'assignment_type' => $assignment->assignment_type,
                        'effective_date' => $assignment->effective_date,
                        'end_date' => $assignment->end_date,
                        'requires_assistance' => $assignment->requires_assistance,
                        'status' => $assignment->status,
                        'is_active' => $assignment->is_active,
                        'attendance_rate' => $assignment->attendance_rate,
                        'duration_in_days' => $assignment->duration_in_days,
                        'was_present_today' => $assignment->wasPresentToday(),
                        'created_at' => $assignment->created_at,
                        'updated_at' => $assignment->updated_at
                    ];
                });
            }
        );
    }

    /**
     * Store a newly created bus assignment.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('school-transport.assignments.manage');

        $this->validate($request, [
            'student_id' => 'required|exists:school_transport_students,uuid',
            'route_id' => 'required|exists:school_transport_routes,uuid',
            'stop_sequence' => 'required|integer|min:1',
            'pickup_stop' => 'nullable|string',
            'pickup_time' => 'nullable|date_format:H:i',
            'dropoff_stop' => 'nullable|string',
            'dropoff_time' => 'nullable|date_format:H:i',
            'assignment_type' => 'required|in:regular,temporary,emergency',
            'effective_date' => 'required|date',
            'end_date' => 'nullable|date|after:effective_date',
            'requires_assistance' => 'boolean',
            'special_instructions' => 'nullable|string'
        ]);

        // Check for overlapping assignments
        $student = Student::findOrFail($request->input('student_id'));
        $route = SchoolRoute::findOrFail($request->input('route_id'));

        $overlapping = BusAssignment::where('student_uuid', $student->uuid)
            ->active()
            ->where(function ($query) use ($request) {
                $effectiveDate = $request->input('effective_date');
                $endDate = $request->input('end_date');

                if ($endDate) {
                    $query->where(function ($q) use ($effectiveDate, $endDate) {
                        $q->whereBetween('effective_date', [$effectiveDate, $endDate])
                            ->orWhereBetween('end_date', [$effectiveDate, $endDate])
                            ->orWhere(function ($q2) use ($effectiveDate, $endDate) {
                                $q2->where('effective_date', '<=', $effectiveDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    });
                } else {
                    $query->where('effective_date', '<=', $effectiveDate)
                        ->where(function ($q) use ($effectiveDate) {
                            $q->whereNull('end_date')
                                ->orWhere('end_date', '>=', $effectiveDate);
                        });
                }
            })
            ->exists();

        if ($overlapping) {
            return response()->json([
                'error' => 'Student already has an active assignment for this period'
            ], 422);
        }

        // Check route capacity
        $assignedCount = BusAssignment::where('route_uuid', $route->uuid)
            ->active()
            ->count();

        if ($assignedCount >= $route->capacity) {
            return response()->json([
                'error' => 'Route is at full capacity'
            ], 422);
        }

        // Check wheelchair accessibility
        if ($student->requiresWheelchairAccess() && !$route->wheelchair_accessible) {
            return response()->json([
                'error' => 'Student requires wheelchair accessible route'
            ], 422);
        }

        $assignment = BusAssignment::create([
            'student_uuid' => $student->uuid,
            'route_uuid' => $route->uuid,
            'stop_sequence' => $request->input('stop_sequence'),
            'pickup_stop' => $request->input('pickup_stop'),
            'pickup_time' => $request->input('pickup_time'),
            'dropoff_stop' => $request->input('dropoff_stop'),
            'dropoff_time' => $request->input('dropoff_time'),
            'assignment_type' => $request->input('assignment_type'),
            'effective_date' => $request->input('effective_date'),
            'end_date' => $request->input('end_date'),
            'requires_assistance' => $request->boolean('requires_assistance'),
            'special_instructions' => $request->input('special_instructions'),
            'company_uuid' => session('company')
        ]);

        return response()->json([
            'assignment' => $assignment->load(['student', 'route'])
        ], 201);
    }

    /**
     * Display the specified assignment.
     */
    public function show(string $id): JsonResponse
    {
        $assignment = BusAssignment::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['student', 'route', 'attendanceRecords'])
            ->firstOrFail();

        return response()->json([
            'assignment' => [
                'id' => $assignment->uuid,
                'public_id' => $assignment->public_id,
                'student' => $assignment->student,
                'route' => $assignment->route,
                'stop_sequence' => $assignment->stop_sequence,
                'pickup_stop' => $assignment->pickup_stop,
                'pickup_coordinates' => $assignment->pickup_coordinates,
                'pickup_time' => $assignment->pickup_time,
                'pickup_location_display' => $assignment->getPickupLocationDisplay(),
                'dropoff_stop' => $assignment->dropoff_stop,
                'dropoff_coordinates' => $assignment->dropoff_coordinates,
                'dropoff_time' => $assignment->dropoff_time,
                'dropoff_location_display' => $assignment->getDropoffLocationDisplay(),
                'assignment_type' => $assignment->assignment_type,
                'effective_date' => $assignment->effective_date,
                'end_date' => $assignment->end_date,
                'requires_assistance' => $assignment->requires_assistance,
                'special_instructions' => $assignment->special_instructions,
                'status' => $assignment->status,
                'is_active' => $assignment->is_active,
                'duration_in_days' => $assignment->duration_in_days,
                'attendance_rate' => $assignment->attendance_rate,
                'recent_attendance' => $assignment->recent_attendance,
                'was_present_today' => $assignment->wasPresentToday(),
                'upcoming_pickup_time' => $assignment->getUpcomingPickupTime(),
                'estimated_pickup_time' => $assignment->calculateEstimatedPickupTime(),
                'is_for_special_needs' => $assignment->isForSpecialNeedsStudent(),
                'attendance_records' => $assignment->attendanceRecords->take(30),
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at
            ]
        ]);
    }

    /**
     * Update the specified assignment.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('school-transport.assignments.manage');

        $assignment = BusAssignment::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'stop_sequence' => 'sometimes|integer|min:1',
            'pickup_stop' => 'nullable|string',
            'pickup_time' => 'nullable|date_format:H:i',
            'dropoff_stop' => 'nullable|string',
            'dropoff_time' => 'nullable|date_format:H:i',
            'assignment_type' => 'sometimes|in:regular,temporary,emergency',
            'end_date' => 'nullable|date|after:effective_date',
            'requires_assistance' => 'boolean',
            'special_instructions' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,suspended'
        ]);

        $assignment->update($request->only([
            'stop_sequence',
            'pickup_stop',
            'pickup_time',
            'dropoff_stop',
            'dropoff_time',
            'assignment_type',
            'end_date',
            'requires_assistance',
            'special_instructions',
            'status'
        ]));

        return response()->json([
            'assignment' => $assignment->fresh()->load(['student', 'route'])
        ]);
    }

    /**
     * Remove the specified assignment.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->authorize('school-transport.assignments.manage');

        $assignment = BusAssignment::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Deactivate instead of delete to preserve historical data
        $assignment->deactivate();

        return response()->json([
            'message' => 'Assignment deactivated successfully'
        ]);
    }

    /**
     * Bulk assign students to routes.
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $this->authorize('school-transport.assignments.manage');

        $this->validate($request, [
            'assignments' => 'required|array|max:500',
            'assignments.*.student_id' => 'required|exists:school_transport_students,uuid',
            'assignments.*.route_id' => 'required|exists:school_transport_routes,uuid',
            'assignments.*.stop_sequence' => 'required|integer|min:1',
            'assignments.*.effective_date' => 'required|date',
            'assignments.*.assignment_type' => 'required|in:regular,temporary,emergency'
        ]);

        $created = [];
        $errors = [];

        DB::transaction(function () use ($request, &$created, &$errors) {
            foreach ($request->input('assignments') as $index => $assignmentData) {
                try {
                    $student = Student::findOrFail($assignmentData['student_id']);
                    $route = SchoolRoute::findOrFail($assignmentData['route_id']);

                    // Check capacity
                    $assignedCount = BusAssignment::where('route_uuid', $route->uuid)
                        ->active()
                        ->count();

                    if ($assignedCount >= $route->capacity) {
                        $errors[] = [
                            'row' => $index + 1,
                            'student_id' => $student->student_id,
                            'route_name' => $route->route_name,
                            'error' => 'Route at full capacity'
                        ];
                        continue;
                    }

                    $assignment = BusAssignment::create(array_merge($assignmentData, [
                        'student_uuid' => $student->uuid,
                        'route_uuid' => $route->uuid,
                        'company_uuid' => session('company')
                    ]));

                    $created[] = $assignment->load(['student', 'route']);
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        return response()->json([
            'created_count' => count($created),
            'error_count' => count($errors),
            'errors' => $errors,
            'assignments' => $created
        ]);
    }

    /**
     * Generate attendance report.
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $this->validate($request, [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'route_id' => 'nullable|exists:school_transport_routes,uuid',
            'student_id' => 'nullable|exists:school_transport_students,uuid'
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $routeId = $request->input('route_id');
        $studentId = $request->input('student_id');

        // Get attendance summary
        $summary = Attendance::getSummaryForDateRange($startDate, $endDate, $routeId);

        // Get detailed records
        $query = Attendance::forDateRange($startDate, $endDate)
            ->with(['student', 'route', 'assignment']);

        if ($routeId) {
            $query->forRoute($routeId);
        }

        if ($studentId) {
            $query->forStudent($studentId);
        }

        $records = $query->orderBy('date', 'desc')
            ->orderBy('student_uuid')
            ->get();

        // Group by student for patterns
        $studentPatterns = [];
        if (!$studentId) {
            $students = $records->groupBy('student_uuid');
            foreach ($students as $studentUuid => $studentRecords) {
                $student = $studentRecords->first()->student;
                $present = $studentRecords->where('present', true)->count();
                $total = $studentRecords->count();

                $studentPatterns[] = [
                    'student' => [
                        'id' => $student->uuid,
                        'student_id' => $student->student_id,
                        'full_name' => $student->full_name,
                        'grade' => $student->grade
                    ],
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
                    'total_days' => $total,
                    'present_days' => $present,
                    'absent_days' => $total - $present,
                    'late_count' => $studentRecords->filter(function ($record) {
                        return $record->is_late;
                    })->count()
                ];
            }
        }

        return response()->json([
            'summary' => $summary,
            'student_patterns' => $studentPatterns,
            'records' => $records->map(function ($record) {
                return [
                    'id' => $record->uuid,
                    'date' => $record->date,
                    'session' => $record->session,
                    'event_type' => $record->event_type,
                    'student' => [
                        'full_name' => $record->student->full_name,
                        'student_id' => $record->student->student_id,
                        'grade' => $record->student->grade
                    ],
                    'route' => [
                        'route_name' => $record->route->route_name,
                        'school' => $record->route->school
                    ],
                    'scheduled_time' => $record->scheduled_time,
                    'actual_time' => $record->actual_time,
                    'present' => $record->present,
                    'is_late' => $record->is_late,
                    'delay_minutes' => $record->delay_minutes,
                    'notes' => $record->notes
                ];
            }),
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }
}
