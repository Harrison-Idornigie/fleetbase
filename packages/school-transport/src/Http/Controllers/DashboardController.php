<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Services\ReportingService;
use Fleetbase\SchoolTransportEngine\Services\RouteOptimizationService;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\BusAssignment;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Illuminate\Http\Request;

class DashboardController extends FleetbaseController
{
    /**
     * The reporting service instance
     *
     * @var ReportingService
     */
    protected $reportingService;

    /**
     * The route optimization service instance
     *
     * @var RouteOptimizationService
     */
    protected $routeOptimizationService;

    /**
     * Create a new controller instance
     */
    public function __construct(
        ReportingService $reportingService,
        RouteOptimizationService $routeOptimizationService
    ) {
        $this->reportingService = $reportingService;
        $this->routeOptimizationService = $routeOptimizationService;
    }

    /**
     * Get dashboard statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        $companyUuid = session('company');
        $stats = $this->reportingService->getDashboardStats($companyUuid);

        return response()->json($stats);
    }

    /**
     * Get route efficiency metrics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function routeEfficiency(Request $request)
    {
        $companyUuid = session('company');
        
        $report = $this->reportingService->getRouteEfficiencyReport(
            $companyUuid,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($report);
    }

    /**
     * Get student attendance overview
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function studentAttendance(Request $request)
    {
        $companyUuid = session('company');
        
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $report = $this->reportingService->getStudentAttendanceReport(
            $companyUuid,
            $startDate,
            $endDate
        );

        return response()->json($report);
    }

    /**
     * Get real-time route status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function routeStatus(Request $request)
    {
        $companyUuid = session('company');

        $routes = SchoolRoute::where('company_uuid', $companyUuid)
            ->where('is_active', true)
            ->with(['busAssignments.student', 'vehicle', 'driver'])
            ->get();

        $routeStatus = [];

        foreach ($routes as $route) {
            $assignmentCount = $route->busAssignments->where('status', 'active')->count();
            $utilization = $route->capacity > 0 
                ? round(($assignmentCount / $route->capacity) * 100, 1)
                : 0;

            $routeStatus[] = [
                'route_id' => $route->uuid,
                'route_name' => $route->route_name,
                'route_number' => $route->route_number,
                'status' => $route->status,
                'assigned_students' => $assignmentCount,
                'capacity' => $route->capacity,
                'utilization' => $utilization,
                'vehicle' => $route->vehicle ? [
                    'id' => $route->vehicle->uuid,
                    'name' => $route->vehicle->name ?? 'Unknown'
                ] : null,
                'driver' => $route->driver ? [
                    'id' => $route->driver->uuid,
                    'name' => $route->driver->name ?? 'Unknown'
                ] : null
            ];
        }

        return response()->json([
            'total_routes' => count($routeStatus),
            'routes' => $routeStatus
        ]);
    }

    /**
     * Get alerts and notifications
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function alerts(Request $request)
    {
        $companyUuid = session('company');
        $alerts = [];

        // Check for over-capacity routes
        $routes = SchoolRoute::where('company_uuid', $companyUuid)
            ->where('is_active', true)
            ->with('busAssignments')
            ->get();

        foreach ($routes as $route) {
            $assignmentCount = $route->busAssignments->where('status', 'active')->count();
            $utilization = $route->capacity > 0 ? ($assignmentCount / $route->capacity) * 100 : 0;

            if ($utilization > 95) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'capacity',
                    'message' => "Route {$route->route_name} is at {$utilization}% capacity",
                    'route_id' => $route->uuid,
                    'priority' => 'high'
                ];
            }
        }

        // Check for low attendance
        $todayAttendance = Attendance::where('company_uuid', $companyUuid)
            ->where('date', now()->toDateString())
            ->get();

        $totalExpected = BusAssignment::where('company_uuid', $companyUuid)
            ->where('status', 'active')
            ->count();

        $totalPresent = $todayAttendance->where('present', true)->count();
        $attendanceRate = $totalExpected > 0 ? ($totalPresent / $totalExpected) * 100 : 0;

        if ($attendanceRate < 80) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'attendance',
                'message' => "Today's attendance is low at {$attendanceRate}%",
                'priority' => 'medium'
            ];
        }

        return response()->json([
            'total_alerts' => count($alerts),
            'alerts' => $alerts
        ]);
    }
}

