<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Fleetbase\SchoolTransportEngine\Services\AttendanceService;
use Illuminate\Http\Request;

class AttendanceController extends FleetbaseController
{
    /**
     * The attendance service instance
     *
     * @var AttendanceService
     */
    protected $attendanceService;

    /**
     * Create a new controller instance
     */
    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Display a listing of attendance records
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Attendance::where('company_uuid', session('company'));

        // Filter by student
        if ($request->has('student_uuid')) {
            $query->where('student_uuid', $request->input('student_uuid'));
        }

        // Filter by route
        if ($request->has('route_uuid')) {
            $query->where('route_uuid', $request->input('route_uuid'));
        }

        // Filter by date
        if ($request->has('date')) {
            $query->where('date', $request->input('date'));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        // Filter by event type
        if ($request->has('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        // Filter by present/absent
        if ($request->has('present')) {
            $query->where('present', $request->boolean('present'));
        }

        // Include relationships
        $query->with(['student', 'route', 'assignment']);

        // Order by date and time
        $query->orderBy('date', 'desc')
            ->orderBy('actual_time', 'desc');

        $attendance = $query->paginate($request->input('per_page', 15));

        return response()->json($attendance);
    }

    /**
     * Store a new attendance record
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_uuid' => 'required|exists:school_transport_students,uuid',
            'route_uuid' => 'required|exists:school_transport_routes,uuid',
            'assignment_uuid' => 'nullable|exists:school_transport_bus_assignments,uuid',
            'date' => 'required|date',
            'session' => 'required|in:morning,afternoon',
            'event_type' => 'required|in:pickup,dropoff,no_show,early_dismissal',
            'scheduled_time' => 'nullable|date_format:H:i:s',
            'actual_time' => 'nullable|date_format:H:i:s',
            'present' => 'required|boolean',
            'location' => 'nullable|string|max:255',
            'coordinates' => 'nullable|array',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:pending,completed,cancelled'
        ]);

        $validated['company_uuid'] = session('company');
        $validated['recorded_by_uuid'] = auth()->id();

        $attendance = Attendance::create($validated);

        return response()->json([
            'message' => 'Attendance record created successfully',
            'attendance' => $attendance->load(['student', 'route'])
        ], 201);
    }

    /**
     * Display the specified attendance record
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $attendance = Attendance::where('company_uuid', session('company'))
            ->with(['student', 'route', 'assignment'])
            ->findOrFail($id);

        return response()->json($attendance);
    }

    /**
     * Update the specified attendance record
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $attendance = Attendance::where('company_uuid', session('company'))
            ->findOrFail($id);

        $validated = $request->validate([
            'actual_time' => 'nullable|date_format:H:i:s',
            'present' => 'nullable|boolean',
            'location' => 'nullable|string|max:255',
            'coordinates' => 'nullable|array',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:pending,completed,cancelled'
        ]);

        $attendance->update($validated);

        return response()->json([
            'message' => 'Attendance record updated successfully',
            'attendance' => $attendance->load(['student', 'route'])
        ]);
    }

    /**
     * Record student pickup
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordPickup(Request $request)
    {
        $validated = $request->validate([
            'student_uuid' => 'required|exists:school_transport_students,uuid',
            'route_uuid' => 'required|exists:school_transport_routes,uuid',
            'actual_time' => 'nullable|date_format:H:i:s',
            'location' => 'nullable|string',
            'coordinates' => 'nullable|array',
            'notes' => 'nullable|string'
        ]);

        $result = $this->attendanceService->recordPickup(
            $validated['student_uuid'],
            $validated['route_uuid'],
            array_merge($validated, ['recorded_by' => auth()->id()])
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 201);
    }

    /**
     * Record student dropoff
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordDropoff(Request $request)
    {
        $validated = $request->validate([
            'student_uuid' => 'required|exists:school_transport_students,uuid',
            'route_uuid' => 'required|exists:school_transport_routes,uuid',
            'actual_time' => 'nullable|date_format:H:i:s',
            'location' => 'nullable|string',
            'coordinates' => 'nullable|array',
            'notes' => 'nullable|string'
        ]);

        $result = $this->attendanceService->recordDropoff(
            $validated['student_uuid'],
            $validated['route_uuid'],
            array_merge($validated, ['recorded_by' => auth()->id()])
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 201);
    }

    /**
     * Get attendance statistics for a student
     *
     * @param Request $request
     * @param string $studentUuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function studentStats(Request $request, $studentUuid)
    {
        $stats = $this->attendanceService->getStudentAttendanceStats(
            $studentUuid,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($stats);
    }

    /**
     * Get attendance statistics for a route
     *
     * @param Request $request
     * @param string $routeUuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function routeStats(Request $request, $routeUuid)
    {
        $stats = $this->attendanceService->getRouteAttendanceStats(
            $routeUuid,
            $request->input('date')
        );

        return response()->json($stats);
    }

    /**
     * Get daily attendance report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dailyReport(Request $request)
    {
        $report = $this->attendanceService->getDailyAttendanceReport(
            session('company'),
            $request->input('date')
        );

        return response()->json($report);
    }
}
