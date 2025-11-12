<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\SchoolTransportEngine\Models\BusAssignment;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Fleetbase\SchoolTransportEngine\Models\Communication;
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

        return [
            'total_students' => $totalStudents,
            'total_routes' => $totalRoutes,
            'active_assignments' => $activeAssignments,
            'today_attendance' => [
                'total' => $todayAttendance,
                'present' => $todayPresent,
                'absent' => $todayAttendance - $todayPresent,
                'rate' => $attendanceRate
            ]
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

            $report[] = [
                'route_id' => $route->uuid,
                'route_name' => $route->route_name,
                'route_number' => $route->route_number,
                'capacity' => $route->capacity,
                'assigned_students' => $assignmentCount,
                'utilization_percentage' => $utilization,
                'estimated_distance' => $route->estimated_distance,
                'estimated_duration' => $route->estimated_duration,
                'efficiency_score' => $this->calculateRouteEfficiency($route, $assignmentCount)
            ];
        }

        // Sort by efficiency score descending
        usort($report, function($a, $b) {
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
        usort($report, function($a, $b) {
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
}

