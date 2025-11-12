<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Illuminate\Support\Collection;

/**
 * Route Playback Service
 *
 * Handles route playback functionality for school buses,
 * combining FleetOps GPS positions with student attendance events.
 */
class RoutePlaybackService
{
    /**
     * Get route playback data for a bus within a date range.
     *
     * @param Bus $bus
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param array $filters
     * @return array
     */
    public function getPlayback(Bus $bus, \DateTime $startDate, \DateTime $endDate, array $filters = []): array
    {
        // Get FleetOps position data
        $positions = $bus->positions()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        // Get student attendance events for the same period with optional filtering
        $attendanceQuery = Attendance::whereHas('assignment', function ($query) use ($bus) {
            $query->where('bus_uuid', $bus->uuid);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['student', 'assignment.route']);

        // Apply student filter if specified
        if (!empty($filters['student_uuid'])) {
            $attendanceQuery->where('student_uuid', $filters['student_uuid']);
        }

        // Apply trip filter if specified
        if (!empty($filters['trip_uuid'])) {
            $attendanceQuery->whereHas('assignment', function ($query) use ($filters) {
                $query->where('trip_uuid', $filters['trip_uuid']);
            });
        }

        $attendanceEvents = $attendanceQuery->orderBy('actual_time')->get();

        // Combine positions with attendance events
        $playbackData = [];

        foreach ($positions as $position) {
            $playbackData[] = [
                'type' => 'position',
                'timestamp' => $position->created_at,
                'coordinates' => [
                    'latitude' => $position->coordinates->getLat(),
                    'longitude' => $position->coordinates->getLng()
                ],
                'speed' => $position->speed ?? 0,
                'heading' => $position->heading ?? 0,
                'order_uuid' => $position->order_uuid,
                'destination_uuid' => $position->destination_uuid,
            ];
        }

        foreach ($attendanceEvents as $event) {
            $playbackData[] = [
                'type' => 'student_event',
                'event_type' => $event->event_type, // pickup, dropoff
                'timestamp' => $event->created_at,
                'actual_time' => $event->actual_time,
                'student' => [
                    'uuid' => $event->student->uuid,
                    'name' => $event->student->full_name,
                    'grade' => $event->student->grade,
                ],
                'location' => $event->location,
                'coordinates' => $event->coordinates,
                'present' => $event->present,
                'notes' => $event->notes,
                'stop_name' => $event->assignment->route->stops[$event->location] ?? $event->location,
            ];
        }

        // Sort by timestamp
        usort($playbackData, function ($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        // Track students on board at each point
        $onBoardStudents = [];
        foreach ($playbackData as &$point) {
            if ($point['type'] === 'student_event' && $point['present']) {
                if ($point['event_type'] === 'pickup') {
                    // Add student to on-board list
                    $onBoardStudents[$point['student']['uuid']] = [
                        'uuid' => $point['student']['uuid'],
                        'name' => $point['student']['name'],
                        'grade' => $point['student']['grade'],
                        'boarded_at' => $point['timestamp'],
                    ];
                } elseif ($point['event_type'] === 'dropoff') {
                    // Remove student from on-board list
                    unset($onBoardStudents[$point['student']['uuid']]);
                }
            }

            // Add snapshot of students currently on board
            $point['on_board_students'] = array_values($onBoardStudents);
            $point['on_board_count'] = count($onBoardStudents);
        }
        unset($point); // Break reference

        // Calculate comprehensive metrics
        $metrics = $this->calculateRouteMetrics($positions);

        return [
            'bus_uuid' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'positions' => $positions->map(function ($position) {
                return [
                    'latitude' => $position->coordinates->getLat(),
                    'longitude' => $position->coordinates->getLng(),
                    'speed' => $position->speed ?? 0,
                    'heading' => $position->heading ?? 0,
                    'created_at' => $position->created_at->toISOString(),
                    'altitude' => $position->altitude ?? 0,
                ];
            })->toArray(),
            'student_events' => $attendanceEvents->map(function ($event) {
                return [
                    'event_type' => $event->event_type,
                    'student' => [
                        'uuid' => $event->student->uuid,
                        'first_name' => $event->student->first_name,
                        'last_name' => $event->student->last_name,
                        'grade' => $event->student->grade,
                    ],
                    'latitude' => $event->latitude ?? 0,
                    'longitude' => $event->longitude ?? 0,
                    'location_name' => $event->location_name,
                    'created_at' => $event->created_at->toISOString(),
                    'actual_time' => $event->actual_time?->toISOString(),
                ];
            })->toArray(),
            'metrics' => $metrics,
            'date_range' => [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end' => $endDate->format('Y-m-d H:i:s')
            ],
            'total_positions' => count($positions),
            'total_student_events' => count($attendanceEvents),
            'playback_data' => $playbackData
        ];
    }

    /**
     * Calculate comprehensive route metrics from positions.
     *
     * @param Collection $positions
     * @return array
     */
    private function calculateRouteMetrics(Collection $positions): array
    {
        if ($positions->isEmpty()) {
            return [
                'total_distance_miles' => 0,
                'total_distance_km' => 0,
                'duration_minutes' => 0,
                'duration_seconds' => 0,
                'max_speed_mph' => 0,
                'max_speed_kmh' => 0,
                'avg_speed_mph' => 0,
                'avg_speed_kmh' => 0,
                'speeding_events' => 0,
                'idle_time_minutes' => 0,
                'moving_time_minutes' => 0,
            ];
        }

        $totalDistance = 0; // in miles
        $totalTime = 0; // in seconds
        $speeds = [];
        $speedingEvents = 0;
        $idleTime = 0;
        $movingTime = 0;
        $speedLimit = 35; // Default speed limit in mph

        $previousPosition = null;

        foreach ($positions as $position) {
            if ($previousPosition) {
                // Calculate distance using Haversine formula
                $distance = $this->haversineDistance(
                    $previousPosition->coordinates->getLat(),
                    $previousPosition->coordinates->getLng(),
                    $position->coordinates->getLat(),
                    $position->coordinates->getLng()
                );
                $totalDistance += $distance;

                // Calculate time difference
                $timeDiff = $position->created_at->diffInSeconds($previousPosition->created_at);
                $totalTime += $timeDiff;

                // Track speeds
                $speed = $position->speed ?? 0;
                if ($speed > 0) {
                    $speeds[] = $speed;
                    $movingTime += $timeDiff;

                    // Check for speeding
                    if ($speed > $speedLimit) {
                        $speedingEvents++;
                    }
                } else {
                    $idleTime += $timeDiff;
                }
            }

            $previousPosition = $position;
        }

        $avgSpeed = !empty($speeds) ? array_sum($speeds) / count($speeds) : 0;
        $maxSpeed = !empty($speeds) ? max($speeds) : 0;

        return [
            'total_distance_miles' => round($totalDistance, 2),
            'total_distance_km' => round($totalDistance * 1.60934, 2),
            'duration_minutes' => round($totalTime / 60, 1),
            'duration_seconds' => $totalTime,
            'max_speed_mph' => round($maxSpeed, 1),
            'max_speed_kmh' => round($maxSpeed * 1.60934, 1),
            'avg_speed_mph' => round($avgSpeed, 1),
            'avg_speed_kmh' => round($avgSpeed * 1.60934, 1),
            'speeding_events' => $speedingEvents,
            'idle_time_minutes' => round($idleTime / 60, 1),
            'moving_time_minutes' => round($movingTime / 60, 1),
        ];
    }

    /**
     * Calculate distance between two points using Haversine formula.
     * Returns distance in miles.
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 3959; // Earth's radius in miles

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
