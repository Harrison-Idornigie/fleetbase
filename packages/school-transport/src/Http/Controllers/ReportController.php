<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Services\ReportingService;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Fleetbase\SchoolTransportEngine\Models\Communication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends FleetbaseController
{
    /**
     * The reporting service instance
     *
     * @var ReportingService
     */
    protected $reportingService;

    /**
     * Create a new controller instance
     */
    public function __construct(ReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * Generate attendance report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function attendanceReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'student_uuid' => 'nullable|exists:school_transport_students,uuid',
            'route_uuid' => 'nullable|exists:school_transport_routes,uuid',
            'school' => 'nullable|string'
        ]);

        $companyUuid = session('company');

        if ($request->has('student_uuid')) {
            // Individual student report
            $student = Student::findOrFail($validated['student_uuid']);
            $attendance = Attendance::where('student_uuid', $validated['student_uuid'])
                ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
                ->with('route')
                ->get();

            $total = $attendance->count();
            $present = $attendance->where('present', true)->count();

            return response()->json([
                'type' => 'student',
                'student' => $student,
                'period' => [
                    'start' => $validated['start_date'],
                    'end' => $validated['end_date']
                ],
                'summary' => [
                    'total_days' => $total,
                    'present_days' => $present,
                    'absent_days' => $total - $present,
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0
                ],
                'records' => $attendance
            ]);
        }

        // General attendance report
        $report = $this->reportingService->getStudentAttendanceReport(
            $companyUuid,
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json($report);
    }

    /**
     * Generate route efficiency report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function routeEfficiencyReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $companyUuid = session('company');

        $report = $this->reportingService->getRouteEfficiencyReport(
            $companyUuid,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json($report);
    }

    /**
     * Generate communication report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function communicationReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'nullable|string'
        ]);

        $companyUuid = session('company');

        $query = Communication::where('company_uuid', $companyUuid)
            ->whereBetween('created_at', [$validated['start_date'], $validated['end_date']]);

        if ($request->has('type')) {
            $query->where('type', $validated['type']);
        }

        $communications = $query->get();

        $stats = [
            'total' => $communications->count(),
            'by_type' => $communications->groupBy('type')->map->count(),
            'by_status' => $communications->groupBy('status')->map->count(),
            'by_priority' => $communications->groupBy('priority')->map->count()
        ];

        return response()->json([
            'period' => [
                'start' => $validated['start_date'],
                'end' => $validated['end_date']
            ],
            'stats' => $stats,
            'communications' => $communications
        ]);
    }

    /**
     * Generate student roster report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function studentRosterReport(Request $request)
    {
        $validated = $request->validate([
            'school' => 'nullable|string',
            'grade' => 'nullable|string',
            'route_uuid' => 'nullable|exists:school_transport_routes,uuid',
            'active_only' => 'nullable|boolean'
        ]);

        $companyUuid = session('company');

        $query = Student::where('company_uuid', $companyUuid);

        if ($request->has('school')) {
            $query->where('school', $validated['school']);
        }

        if ($request->has('grade')) {
            $query->where('grade', $validated['grade']);
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $students = $query->with('busAssignments.route')->get();

        if ($request->has('route_uuid')) {
            $students = $students->filter(function ($student) use ($validated) {
                return $student->busAssignments->contains('route_uuid', $validated['route_uuid']);
            });
        }

        return response()->json([
            'total_students' => $students->count(),
            'filters' => $validated,
            'students' => $students
        ]);
    }
}

