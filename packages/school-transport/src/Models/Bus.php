<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * School Transport Bus Model
 * 
 * Extends FleetOps Vehicle to inherit:
 * - Maintenance scheduling and tracking
 * - Fuel reporting and management
 * - Equipment assignments
 * - Work order integration
 * - Cost center and budget tracking
 * - Warranty management
 * - Advanced spatial/location features
 * 
 * Adds school-specific features:
 * - Student capacity and occupancy tracking
 * - Route assignments
 * - Education compliance requirements
 */
class Bus extends Vehicle
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'vehicles';

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'school_bus';

    /**
     * School-specific attributes added to parent Vehicle fillable.
     * Parent Vehicle provides: make, model, year, plate_number, vin, status,
     * fuel_type, odometer, location, warranty, equipment, maintenances, etc.
     *
     * @var array
     */
    protected $fillable = [
        // Parent Vehicle fields (inherited)
        'company_uuid',
        'vendor_uuid',
        'category_uuid',
        'warranty_uuid',
        'photo_uuid',
        'name',
        'make',
        'model',
        'year',
        'color',
        'plate_number',
        'vin',
        'status',
        'fuel_type',
        'odometer',
        'location',
        'meta',

        // School Transport specific fields
        'bus_number',
        'capacity',
        'current_occupancy',
        'route_uuid',
        'insurance_expiry',
        'registration_expiry',
        'is_active',
    ];

    /**
     * The attributes that should be cast to native types.
     * Merges with parent Vehicle casts.
     *
     * @var array
     */
    protected $casts = [
        'capacity' => 'integer',
        'current_occupancy' => 'integer',
        'is_active' => 'boolean',
        'insurance_expiry' => 'date',
        'registration_expiry' => 'date',
        // Parent Vehicle provides: location => Point, meta => Json, etc.
    ];

    /**
     * Boot the model and set default values for school buses.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bus) {
            // Set vehicle type to school_bus if not set
            if (empty($bus->type)) {
                $bus->type = 'school_bus';
            }

            // Set default usage type
            if (empty($bus->usage_type)) {
                $bus->usage_type = 'student_transport';
            }

            // Set bus number as name if name not provided
            if (empty($bus->name) && !empty($bus->bus_number)) {
                $bus->name = 'Bus ' . $bus->bus_number;
            }
        });
    }

    /**
     * Get the assignments for this bus.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(BusAssignment::class, 'bus_uuid', 'uuid');
    }

    /**
     * Get the current driver assigned to this bus.
     * Overrides parent Vehicle driver relationship to use school transport driver.
     */
    public function schoolDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_uuid', 'uuid');
    }

    /**
     * Get the route this bus is assigned to.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SchoolRoute::class, 'route_uuid', 'uuid');
    }

    /**
     * Get the trips for this bus.
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'bus_uuid', 'uuid');
    }

    /**
     * Get the tracking logs for this bus.
     */
    public function trackingLogs(): HasMany
    {
        return $this->hasMany(TrackingLog::class, 'bus_uuid', 'uuid');
    }

    /**
     * Get FleetOps positions (inherits from Vehicle).
     * This provides detailed route playback capabilities.
     */
    public function positions(): HasMany
    {
        return $this->hasMany(\Fleetbase\FleetOps\Models\Position::class, 'subject_uuid', 'uuid')
            ->where('subject_type', static::class);
    }

    /**
     * Get route playback data for a specific date range.
     * Combines FleetOps positions with student attendance events.
     */
    public function getRoutePlayback(\DateTime $startDate, \DateTime $endDate, array $filters = []): array
    {
        // Get FleetOps position data
        $positions = $this->positions()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        // Get student attendance events for the same period with optional filtering
        $attendanceQuery = \Fleetbase\SchoolTransportEngine\Models\Attendance::whereHas('assignment', function ($query) {
            $query->whereHas('route', function ($subQuery) {
                $subQuery->where('vehicle_uuid', $this->uuid);
            });
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
            'bus_uuid' => $this->uuid,
            'bus_number' => $this->bus_number,
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
     */
    private function calculateRouteMetrics($positions): array
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
     */
    private function haversineDistance($lat1, $lon1, $lat2, $lon2): float
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

    /**
     * Scope to filter active buses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter school buses only.
     */
    public function scopeSchoolBuses($query)
    {
        return $query->where('type', 'school_bus');
    }

    /**
     * Scope to filter available buses.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_active', true);
    }

    /**
     * Scope to filter buses by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if bus is at capacity.
     */
    public function isAtCapacity(): bool
    {
        return $this->current_occupancy >= $this->capacity;
    }

    /**
     * Get available seats.
     */
    public function getAvailableSeatsAttribute(): int
    {
        return max(0, $this->capacity - ($this->current_occupancy ?? 0));
    }

    /**
     * Get the bus display name.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->bus_number && $this->plate_number) {
            return $this->bus_number . ' (' . $this->plate_number . ')';
        }

        // Fallback to parent display name if available
        return $this->name ?? parent::getDisplayNameAttribute();
    }

    /**
     * Get the status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'available' => 'Available',
            'in_route' => 'In Route',
            'maintenance' => 'Under Maintenance',
            'out_of_service' => 'Out of Service',
            default => ucfirst($this->status ?? 'Unknown')
        };
    }

    /**
     * Check if bus needs maintenance.
     * Enhanced to use FleetOps maintenance system.
     */
    public function needsMaintenance(): bool
    {
        // Check if there are overdue maintenances from FleetOps
        $hasOverdueMaintenance = $this->maintenances()
            ->where('status', '!=', 'done')
            ->where('scheduled_at', '<', now())
            ->exists();

        if ($hasOverdueMaintenance) {
            return true;
        }

        // Fallback: check for maintenance status
        return $this->status === 'maintenance';
    }

    /**
     * Check if insurance is expired.
     */
    public function insuranceExpired(): bool
    {
        return $this->insurance_expiry && $this->insurance_expiry->isPast();
    }

    /**
     * Check if registration is expired.
     */
    public function registrationExpired(): bool
    {
        return $this->registration_expiry && $this->registration_expiry->isPast();
    }

    /**
     * Get school-specific metadata.
     * Stores capacity, route, and other school fields in meta.
     */
    public function getSchoolMetaAttribute(): array
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        return array_merge($meta, [
            'bus_number' => $this->bus_number,
            'capacity' => $this->capacity,
            'current_occupancy' => $this->current_occupancy,
            'route_uuid' => $this->route_uuid,
            'is_active' => $this->is_active,
            'insurance_expiry' => $this->insurance_expiry?->format('Y-m-d'),
            'registration_expiry' => $this->registration_expiry?->format('Y-m-d'),
        ]);
    }

    /**
     * Create a fuel report for this bus.
     * Leverages FleetOps FuelReport model.
     */
    public function recordFuelReport(array $data): \Fleetbase\FleetOps\Models\FuelReport
    {
        return \Fleetbase\FleetOps\Models\FuelReport::create([
            'company_uuid' => $this->company_uuid,
            'vehicle_uuid' => $this->uuid,
            'driver_uuid' => $data['driver_uuid'] ?? $this->driver_uuid,
            'reported_by_uuid' => $data['reported_by_uuid'] ?? auth()->id(),
            'odometer' => $data['odometer'] ?? $this->odometer,
            'location' => $data['location'] ?? $this->location,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'volume' => $data['volume'],
            'metric_unit' => $data['metric_unit'] ?? 'liters',
            'report' => $data['report'] ?? null,
            'status' => $data['status'] ?? 'pending',
        ]);
    }

    /**
     * Schedule maintenance for this bus.
     * Leverages FleetOps Maintenance model.
     */
    public function scheduleMaintenance(array $data): \Fleetbase\FleetOps\Models\Maintenance
    {
        return \Fleetbase\FleetOps\Models\Maintenance::create([
            'company_uuid' => $this->company_uuid,
            'maintainable_type' => static::class,
            'maintainable_uuid' => $this->uuid,
            'type' => $data['type'] ?? 'scheduled',
            'status' => $data['status'] ?? 'open',
            'priority' => $data['priority'] ?? 'normal',
            'scheduled_at' => $data['scheduled_at'],
            'odometer' => $data['odometer'] ?? $this->odometer,
            'summary' => $data['summary'],
            'notes' => $data['notes'] ?? null,
            'estimated_downtime_hours' => $data['estimated_downtime_hours'] ?? null,
            'created_by_uuid' => auth()->id(),
        ]);
    }
}
