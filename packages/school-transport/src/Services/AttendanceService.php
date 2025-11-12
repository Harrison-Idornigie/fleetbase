<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Fleetbase\SchoolTransportEngine\Models\BusAssignment;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Record student pickup
     *
     * @param string $studentUuid
     * @param string $routeUuid
     * @param array $data
     * @return array
     */
    public function recordPickup($studentUuid, $routeUuid, $data = [])
    {
        try {
            $assignment = BusAssignment::where('student_uuid', $studentUuid)
                ->where('route_uuid', $routeUuid)
                ->where('status', 'active')
                ->first();

            if (!$assignment) {
                return [
                    'success' => false,
                    'message' => 'No active assignment found'
                ];
            }

            $attendance = Attendance::create([
                'company_uuid' => $assignment->company_uuid,
                'student_uuid' => $studentUuid,
                'route_uuid' => $routeUuid,
                'assignment_uuid' => $assignment->uuid,
                'date' => $data['date'] ?? now()->toDateString(),
                'session' => $data['session'] ?? 'morning',
                'event_type' => 'pickup',
                'scheduled_time' => $assignment->pickup_time,
                'actual_time' => $data['actual_time'] ?? now()->toTimeString(),
                'present' => true,
                'location' => $data['location'] ?? $assignment->pickup_stop,
                'coordinates' => $data['coordinates'] ?? $assignment->pickup_coordinates,
                'recorded_by_uuid' => $data['recorded_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'completed'
            ]);

            return [
                'success' => true,
                'message' => 'Pickup recorded successfully',
                'attendance' => $attendance
            ];
        } catch (\Exception $e) {
            Log::error('Failed to record pickup: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to record pickup: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Record student dropoff
     *
     * @param string $studentUuid
     * @param string $routeUuid
     * @param array $data
     * @return array
     */
    public function recordDropoff($studentUuid, $routeUuid, $data = [])
    {
        try {
            $assignment = BusAssignment::where('student_uuid', $studentUuid)
                ->where('route_uuid', $routeUuid)
                ->where('status', 'active')
                ->first();

            if (!$assignment) {
                return [
                    'success' => false,
                    'message' => 'No active assignment found'
                ];
            }

            $attendance = Attendance::create([
                'company_uuid' => $assignment->company_uuid,
                'student_uuid' => $studentUuid,
                'route_uuid' => $routeUuid,
                'assignment_uuid' => $assignment->uuid,
                'date' => $data['date'] ?? now()->toDateString(),
                'session' => $data['session'] ?? 'afternoon',
                'event_type' => 'dropoff',
                'scheduled_time' => $assignment->dropoff_time,
                'actual_time' => $data['actual_time'] ?? now()->toTimeString(),
                'present' => true,
                'location' => $data['location'] ?? $assignment->dropoff_stop,
                'coordinates' => $data['coordinates'] ?? $assignment->dropoff_coordinates,
                'recorded_by_uuid' => $data['recorded_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'completed'
            ]);

            return [
                'success' => true,
                'message' => 'Dropoff recorded successfully',
                'attendance' => $attendance
            ];
        } catch (\Exception $e) {
            Log::error('Failed to record dropoff: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to record dropoff: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Record student absence
     *
     * @param string $studentUuid
     * @param string $routeUuid
     * @param array $data
     * @return array
     */
    public function recordAbsence($studentUuid, $routeUuid, $data = [])
    {
        try {
            $assignment = BusAssignment::where('student_uuid', $studentUuid)
                ->where('route_uuid', $routeUuid)
                ->where('status', 'active')
                ->first();

            if (!$assignment) {
                return [
                    'success' => false,
                    'message' => 'No active assignment found'
                ];
            }

            $attendance = Attendance::create([
                'company_uuid' => $assignment->company_uuid,
                'student_uuid' => $studentUuid,
                'route_uuid' => $routeUuid,
                'assignment_uuid' => $assignment->uuid,
                'date' => $data['date'] ?? now()->toDateString(),
                'session' => $data['session'] ?? 'morning',
                'event_type' => $data['event_type'] ?? 'no_show',
                'scheduled_time' => $assignment->pickup_time,
                'actual_time' => null,
                'present' => false,
                'location' => null,
                'coordinates' => null,
                'recorded_by_uuid' => $data['recorded_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'completed'
            ]);

            return [
                'success' => true,
                'message' => 'Absence recorded successfully',
                'attendance' => $attendance
            ];
        } catch (\Exception $e) {
            Log::error('Failed to record absence: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to record absence: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get attendance statistics for a student
     *
     * @param string $studentUuid
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getStudentAttendanceStats($studentUuid, $startDate = null, $endDate = null)
    {
        $query = Attendance::where('student_uuid', $studentUuid);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $total = $query->count();
        $present = $query->where('present', true)->count();
        $absent = $query->where('present', false)->count();

        return [
            'total_days' => $total,
            'present_days' => $present,
            'absent_days' => $absent,
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0
        ];
    }

    /**
     * Get attendance statistics for a route
     *
     * @param string $routeUuid
     * @param string $date
     * @return array
     */
    public function getRouteAttendanceStats($routeUuid, $date = null)
    {
        $date = $date ?? now()->toDateString();

        $query = Attendance::where('route_uuid', $routeUuid)
            ->where('date', $date);

        $total = $query->count();
        $present = $query->where('present', true)->count();
        $absent = $query->where('present', false)->count();

        return [
            'date' => $date,
            'total_students' => $total,
            'present' => $present,
            'absent' => $absent,
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0
        ];
    }

    /**
     * Get daily attendance report
     *
     * @param string $companyUuid
     * @param string $date
     * @return array
     */
    public function getDailyAttendanceReport($companyUuid, $date = null)
    {
        $date = $date ?? now()->toDateString();

        $attendance = Attendance::where('company_uuid', $companyUuid)
            ->where('date', $date)
            ->with(['student', 'route'])
            ->get();

        $stats = [
            'total' => $attendance->count(),
            'present' => $attendance->where('present', true)->count(),
            'absent' => $attendance->where('present', false)->count(),
            'by_route' => []
        ];

        // Group by route
        $byRoute = $attendance->groupBy('route_uuid');
        foreach ($byRoute as $routeUuid => $records) {
            $route = $records->first()->route;
            $stats['by_route'][] = [
                'route_id' => $routeUuid,
                'route_name' => $route->route_name ?? 'Unknown',
                'total' => $records->count(),
                'present' => $records->where('present', true)->count(),
                'absent' => $records->where('present', false)->count()
            ];
        }

        return [
            'date' => $date,
            'stats' => $stats,
            'records' => $attendance
        ];
    }

    /**
     * Check if student was picked up today
     *
     * @param string $studentUuid
     * @return bool
     */
    public function wasPickedUpToday($studentUuid)
    {
        return Attendance::where('student_uuid', $studentUuid)
            ->where('date', now()->toDateString())
            ->where('event_type', 'pickup')
            ->where('present', true)
            ->exists();
    }

    /**
     * Check if student was dropped off today
     *
     * @param string $studentUuid
     * @return bool
     */
    public function wasDroppedOffToday($studentUuid)
    {
        return Attendance::where('student_uuid', $studentUuid)
            ->where('date', now()->toDateString())
            ->where('event_type', 'dropoff')
            ->where('present', true)
            ->exists();
    }
}
