<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\BusAssignment;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Fleetbase\SchoolTransportEngine\Models\Communication;
use Fleetbase\FleetOps\Models\FuelReport;
use Fleetbase\FleetOps\Models\Maintenance;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportingService
{
    /**
     * Generate dashboard statistics
     *
     * @param string $companyUuid
     * @return array
     */
    public function getDashboardStats($companyUuid)
    {
        $totalStudents = Student::where('company_uuid', $companyUuid)
            ->where('is_active', true)
            ->count();

        $totalRoutes = SchoolRoute::where('company_uuid', $companyUuid)
            ->where('is_active', true)
            ->count();

        $activeAssignments = BusAssignment::where('company_uuid', $companyUuid)
            ->where('status', 'active')
            ->count();

        $todayAttendance = Attendance::where('company_uuid', $companyUuid)
            ->where('date', now()->toDateString())
            ->count();

        $todayPresent = Attendance::where('company_uuid', $companyUuid)
            ->where('date', now()->toDateString())
            ->where('present', true)
            ->count();

        $attendanceRate = $todayAttendance > 0
            ? round(($todayPresent / $todayAttendance) * 100, 1)
            : 0;

        // Get FleetOps data for the dashboard
        $fleetOpsStats = $this->getFleetOpsStats($companyUuid);

        return [
            'total_students' => $totalStudents,
            'total_routes' => $totalRoutes,
            'active_assignments' => $activeAssignments,
            'today_attendance' => [
                'total' => $todayAttendance,
                'present' => $todayPresent,
                'absent' => $todayAttendance - $todayPresent,
                'rate' => $attendanceRate
            ],
            'fleet_operations' => $fleetOpsStats
        ];
    }

    /**
     * Generate route efficiency report
     *
     * @param string $companyUuid
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getRouteEfficiencyReport($companyUuid, $startDate = null, $endDate = null)
    {
        $routes = SchoolRoute::where('company_uuid', $companyUuid)
            ->where('is_active', true)
            ->with('busAssignments')
            ->get();

        $report = [];

        foreach ($routes as $route) {
            $assignmentCount = $route->busAssignments->where('status', 'active')->count();
            $utilization = $route->capacity > 0
                ? round(($assignmentCount / $route->capacity) * 100, 1)
                : 0;

            // Get FleetOps data for this route
            $fleetOpsData = $this->getRouteFleetOpsData($route, $startDate, $endDate);

            $report[] = [
                'route_id' => $route->uuid,
                'route_name' => $route->route_name,
                'route_number' => $route->route_number,
                'capacity' => $route->capacity,
                'assigned_students' => $assignmentCount,
                'utilization_percentage' => $utilization,
                'estimated_distance' => $route->estimated_distance,
                'estimated_duration' => $route->estimated_duration,
                'efficiency_score' => $this->calculateRouteEfficiency($route, $assignmentCount),
                'fleet_operations' => $fleetOpsData
            ];
        }

        // Sort by efficiency score descending
        usort($report, function ($a, $b) {
            return $b['efficiency_score'] <=> $a['efficiency_score'];
        });

        return [
            'period' => [
                'start' => $startDate ?? 'N/A',
                'end' => $endDate ?? 'N/A'
            ],
            'total_routes' => count($report),
            'routes' => $report
        ];
    }

    /**
     * Calculate route efficiency score
     *
     * @param SchoolRoute $route
     * @param int $assignmentCount
     * @return float
     */
    protected function calculateRouteEfficiency($route, $assignmentCount)
    {
        if ($assignmentCount === 0) {
            return 0;
        }

        // Factors: utilization (40%), distance efficiency (30%), time efficiency (30%)
        $utilizationScore = ($assignmentCount / $route->capacity) * 40;

        $distanceEfficiency = $route->estimated_distance > 0
            ? min(($assignmentCount / $route->estimated_distance) * 30, 30)
            : 0;

        $timeEfficiency = $route->estimated_duration > 0
            ? min((60 / $route->estimated_duration) * 30, 30)
            : 0;

        return round($utilizationScore + $distanceEfficiency + $timeEfficiency, 1);
    }

    /**
     * Generate student attendance report
     *
     * @param string $companyUuid
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getStudentAttendanceReport($companyUuid, $startDate, $endDate)
    {
        $students = Student::where('company_uuid', $companyUuid)
            ->where('is_active', true)
            ->get();

        $report = [];

        foreach ($students as $student) {
            $attendance = Attendance::where('student_uuid', $student->uuid)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $total = $attendance->count();
            $present = $attendance->where('present', true)->count();
            $absent = $attendance->where('present', false)->count();

            $report[] = [
                'student_id' => $student->uuid,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'grade' => $student->grade,
                'school' => $student->school,
                'total_days' => $total,
                'present_days' => $present,
                'absent_days' => $absent,
                'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0
            ];
        }

        // Sort by attendance rate ascending (show problem students first)
        usort($report, function ($a, $b) {
            return $a['attendance_rate'] <=> $b['attendance_rate'];
        });

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'total_students' => count($report),
            'students' => $report
        ];
    }

    /**
     * Get FleetOps data for a specific route
     *
     * @param SchoolRoute $route
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    protected function getRouteFleetOpsData($route, $startDate = null, $endDate = null)
    {
        try {
            // Get buses assigned to this route
            $busUuids = $route->busAssignments->pluck('bus_uuid')->unique()->filter();

            if ($busUuids->isEmpty()) {
                return [
                    'fuel_efficiency' => null,
                    'maintenance_cost' => 0,
                    'total_fuel_cost' => 0
                ];
            }

            // Get fuel data for buses on this route
            $fuelQuery = FuelReport::whereIn('vehicle_uuid', $busUuids)
                ->where('status', 'approved');

            if ($startDate) {
                $fuelQuery->where('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $fuelQuery->where('created_at', '<=', $endDate);
            }

            $fuelData = $fuelQuery->selectRaw('SUM(volume) as total_volume, SUM(amount) as total_cost, COUNT(*) as report_count')
                ->first();

            // Get maintenance data for buses on this route
            $maintenanceQuery = Maintenance::whereHas('maintainable', function ($query) use ($busUuids) {
                $query->whereIn('uuid', $busUuids);
            });

            if ($startDate) {
                $maintenanceQuery->where('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $maintenanceQuery->where('created_at', '<=', $endDate);
            }

            $maintenanceData = $maintenanceQuery->selectRaw('SUM(cost) as total_cost, COUNT(*) as maintenance_count')
                ->first();

            // Calculate fuel efficiency if we have distance data
            $fuelEfficiency = null;
            if ($fuelData->total_volume > 0 && $route->estimated_distance) {
                $fuelEfficiency = round($route->estimated_distance / $fuelData->total_volume, 2);
            }

            return [
                'fuel_efficiency' => $fuelEfficiency, // miles per gallon
                'total_fuel_volume' => round($fuelData->total_volume ?? 0, 2),
                'total_fuel_cost' => round($fuelData->total_cost ?? 0, 2),
                'fuel_reports_count' => $fuelData->report_count ?? 0,
                'maintenance_cost' => round($maintenanceData->total_cost ?? 0, 2),
                'maintenance_count' => $maintenanceData->maintenance_count ?? 0
            ];
        } catch (\Exception $e) {
            // Return empty data if FleetOps integration fails
            return [
                'fuel_efficiency' => null,
                'total_fuel_volume' => 0,
                'total_fuel_cost' => 0,
                'fuel_reports_count' => 0,
                'maintenance_cost' => 0,
                'maintenance_count' => 0
            ];
        }
    }

    /**
     * Get FleetOps statistics for dashboard
     *
     * @param string $companyUuid
     * @return array
     */
    protected function getFleetOpsStats($companyUuid)
    {
        try {
            // Get fuel consumption for this month
            $monthlyFuel = FuelReport::where('company_uuid', $companyUuid)
                ->where('status', 'approved')
                ->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth()
                ])
                ->selectRaw('SUM(volume) as total_volume, SUM(amount) as total_cost')
                ->first();

            // Get maintenance stats for this month
            $monthlyMaintenance = Maintenance::whereHas('maintainable', function ($query) use ($companyUuid) {
                $query->where('company_uuid', $companyUuid);
            })
                ->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth()
                ])
                ->selectRaw('COUNT(*) as total_maintenances, SUM(cost) as total_cost')
                ->first();

            // Get overdue maintenance count
            $overdueMaintenance = Maintenance::whereHas('maintainable', function ($query) use ($companyUuid) {
                $query->where('company_uuid', $companyUuid);
            })
                ->where('status', '!=', 'done')
                ->where('scheduled_at', '<', now())
                ->count();

            return [
                'fuel_consumption' => [
                    'monthly_volume' => round($monthlyFuel->total_volume ?? 0, 2),
                    'monthly_cost' => round($monthlyFuel->total_cost ?? 0, 2)
                ],
                'maintenance' => [
                    'monthly_count' => $monthlyMaintenance->total_maintenances ?? 0,
                    'monthly_cost' => round($monthlyMaintenance->total_cost ?? 0, 2),
                    'overdue_count' => $overdueMaintenance
                ]
            ];
        } catch (\Exception $e) {
            // Return empty stats if FleetOps data is not available
            return [
                'fuel_consumption' => [
                    'monthly_volume' => 0,
                    'monthly_cost' => 0
                ],
                'maintenance' => [
                    'monthly_count' => 0,
                    'monthly_cost' => 0,
                    'overdue_count' => 0
                ]
            ];
        }
    }
}
