<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Fleetbase\SchoolTransportEngine\Services\AttendanceService;
use Fleetbase\Models\Setting;
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

    /**
     * Record student attendance with settings validation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordAttendance(Request $request)
    {
        // Get attendance tracking settings
        $attendanceSettings = Setting::lookupCompany('school-transport.attendance-tracking', [
            'required_on_boarding' => true,
            'required_on_exit' => true,
            'photo_verification' => true,
            'geofence_check_in' => true,
            'parent_notifications' => true,
            'school_notifications' => true,
        ]);

        // Get notification settings
        $notificationSettings = Setting::lookupCompany('school-transport.notifications', [
            'parent_eta_notifications' => true,
            'school_attendance_notifications' => true,
        ]);

        // Validate required fields based on settings
        $validationRules = [
            'student_uuid' => 'required|uuid',
            'trip_uuid' => 'required|uuid',
            'type' => 'required|in:boarding,exit',
            'timestamp' => 'required|date',
        ];

        if ($attendanceSettings['photo_verification']) {
            $validationRules['photo'] = 'required|image';
        }

        if ($attendanceSettings['geofence_check_in']) {
            $validationRules['latitude'] = 'required|numeric';
            $validationRules['longitude'] = 'required|numeric';
        }

        $request->validate($validationRules);

        // Create attendance record
        $attendance = $this->attendanceService->recordAttendance($request->all(), $attendanceSettings);

        // Send notifications based on settings
        if ($notificationSettings['parent_eta_notifications']) {
            $this->attendanceService->notifyParents($attendance);
        }

        if ($notificationSettings['school_attendance_notifications']) {
            $this->attendanceService->notifySchool($attendance);
        }

        return response()->json([
            'attendance' => $attendance,
            'settings_applied' => [
                'photo_required' => $attendanceSettings['photo_verification'],
                'geofence_required' => $attendanceSettings['geofence_check_in'],
                'notifications_sent' => [
                    'parent' => $notificationSettings['parent_eta_notifications'],
                    'school' => $notificationSettings['school_attendance_notifications'],
                ],
            ],
        ]);
    }

    /**
     * Get attendance summary with settings context
     */
    public function attendanceSummary(Request $request)
    {
        // Get reporting preferences
        $reportingSettings = Setting::lookupCompany('school-transport.reporting-preferences', [
            'daily_attendance_reports' => true,
            'performance_metrics' => true,
        ]);

        $query = Attendance::where('company_uuid', session('company'));

        // Apply filters
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('date', [$request->date_from, $request->date_to]);
        }

        if ($request->has('route_uuid')) {
            $query->where('route_uuid', $request->route_uuid);
        }

        $attendanceData = $query->with(['student', 'trip'])->get();

        $summary = [
            'total_records' => $attendanceData->count(),
            'boarding_count' => $attendanceData->where('type', 'boarding')->count(),
            'exit_count' => $attendanceData->where('type', 'exit')->count(),
            'settings' => $reportingSettings,
        ];

        if ($reportingSettings['performance_metrics']) {
            $summary['metrics'] = [
                'attendance_rate' => $this->calculateAttendanceRate($attendanceData),
                'on_time_rate' => $this->calculateOnTimeRate($attendanceData),
            ];
        }

        return response()->json($summary);
    }

    /**
     * Calculate attendance rate
     */
    private function calculateAttendanceRate($attendanceData)
    {
        // Implementation for attendance rate calculation
        return 95.5; // Placeholder
    }

    /**
     * Calculate on-time rate
     */
    private function calculateOnTimeRate($attendanceData)
    {
        // Implementation for on-time rate calculation
        return 88.2; // Placeholder
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
