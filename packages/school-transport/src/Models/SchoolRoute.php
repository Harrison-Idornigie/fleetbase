<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicid;
use Fleetbase\Traits\TracksApiCredential;
use Fleetbase\Traits\HasApiModelBehavior;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolRoute extends Model
{
    use HasUuid,
        HasPublicid,
        TracksApiCredential,
        HasApiModelBehavior,
        SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_routes';

    /**
     * The type of public Id to generate
     *
     * @var string
     */
    protected $publicIdType = 'route';

    /**
     * These attributes that can be queried
     *
     * @var array
     */
    protected $searchableColumns = [
        'route_name',
        'route_number',
        'school',
        'description'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'route_name',
        'route_number',
        'description',
        'school',
        'route_type',
        'start_time',
        'end_time',
        'estimated_duration',
        'estimated_distance',
        'stops',
        'waypoints',
        'vehicle_uuid',
        'driver_uuid',
        'capacity',
        'wheelchair_accessible',
        'is_active',
        'status',
        'days_of_week',
        'effective_date',
        'end_date',
        'special_instructions',
        'meta',
        'company_uuid'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'estimated_duration' => 'integer',
        'estimated_distance' => 'decimal:2',
        'stops' => 'array',
        'waypoints' => 'array',
        'capacity' => 'integer',
        'wheelchair_accessible' => 'boolean',
        'is_active' => 'boolean',
        'days_of_week' => 'array',
        'effective_date' => 'date',
        'end_date' => 'date',
        'special_instructions' => 'array',
        'meta' => 'array'
    ];

    /**
     * Dynamic attributes that are appended to object
     *
     * @var array
     */
    protected $appends = [
        'assigned_students_count',
        'available_capacity',
        'utilization_percentage',
        'is_current',
        'next_stop'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Get the number of assigned students
     */
    public function getAssignedStudentsCountAttribute(): int
    {
        return $this->busAssignments()->where('status', 'active')->count();
    }

    /**
     * Get the available capacity
     */
    public function getAvailableCapacityAttribute(): int
    {
        return max(0, $this->capacity - $this->assigned_students_count);
    }

    /**
     * Get the utilization percentage
     */
    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->capacity <= 0) {
            return 0;
        }

        return round(($this->assigned_students_count / $this->capacity) * 100, 2);
    }

    /**
     * Check if route is currently active
     */
    public function getIsCurrentAttribute(): bool
    {
        $today = now()->toDateString();

        return $this->is_active &&
            $this->status === 'active' &&
            ($this->effective_date === null || $this->effective_date <= $today) &&
            ($this->end_date === null || $this->end_date >= $today);
    }

    /**
     * Get the next stop information
     */
    public function getNextStopAttribute(): ?array
    {
        if (!$this->stops || empty($this->stops)) {
            return null;
        }

        $currentTime = now()->format('H:i');

        foreach ($this->stops as $stop) {
            if (isset($stop['time']) && $stop['time'] > $currentTime) {
                return $stop;
            }
        }

        return null;
    }

    /**
     * Get the bus assignments for this route
     */
    public function busAssignments(): HasMany
    {
        return $this->hasMany(BusAssignment::class, 'route_uuid');
    }

    /**
     * Get active bus assignments
     */
    public function activeBusAssignments(): HasMany
    {
        return $this->hasMany(BusAssignment::class, 'route_uuid')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    /**
     * Get students assigned to this route
     */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            BusAssignment::class,
            'route_uuid', // Foreign key on bus_assignments table
            'id', // Foreign key on students table  
            'id', // Local key on routes table
            'student_uuid' // Local key on bus_assignments table
        )->where('school_transport_bus_assignments.status', 'active');
    }

    /**
     * Get attendance records for this route
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class, 'route_uuid');
    }

    /**
     * Get communications for this route
     */
    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'route_uuid');
    }

    /**
     * Scope to filter by school
     */
    public function scopeForSchool($query, string $school)
    {
        return $query->where('school', $school);
    }

    /**
     * Scope to filter by route type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('route_type', $type);
    }

    /**
     * Scope to filter active routes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'active');
    }

    /**
     * Scope to filter routes that are wheelchair accessible
     */
    public function scopeWheelchairAccessible($query)
    {
        return $query->where('wheelchair_accessible', true);
    }

    /**
     * Scope to filter routes by day of week
     */
    public function scopeForDay($query, string $day)
    {
        return $query->whereJsonContains('days_of_week', $day);
    }

    /**
     * Check if route operates on a specific day
     */
    public function operatesOnDay(string $day): bool
    {
        return in_array(strtolower($day), $this->days_of_week ?? []);
    }

    /**
     * Check if route operates today
     */
    public function operatesToday(): bool
    {
        $today = strtolower(now()->format('l'));
        return $this->operatesOnDay($today);
    }

    /**
     * Get route duration in readable format
     */
    public function getFormattedDuration(): string
    {
        if (!$this->estimated_duration) {
            return 'Unknown';
        }

        $hours = floor($this->estimated_duration / 60);
        $minutes = $this->estimated_duration % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Optimize route stops order
     */
    public function optimizeStops(): array
    {
        // This would integrate with a routing service like Google Maps API
        // For now, return stops as-is
        return $this->stops ?? [];
    }

    /**
     * Calculate estimated arrival time for a specific stop
     */
    public function calculateArrivalTime(int $stopIndex): ?string
    {
        if (!isset($this->stops[$stopIndex])) {
            return null;
        }

        // Simple calculation - in reality would use traffic data
        $minutesFromStart = $stopIndex * 5; // 5 minutes per stop estimate

        return now()->parse($this->start_time)
            ->addMinutes($minutesFromStart)
            ->format('H:i');
    }

    /**
     * Check if route is overutilized
     */
    public function isOverutilized(): bool
    {
        return $this->utilization_percentage > 90;
    }

    /**
     * Get route efficiency score
     */
    public function getEfficiencyScore(): float
    {
        // Basic efficiency calculation based on utilization and distance
        $utilizationScore = min($this->utilization_percentage / 80, 1.0); // Target 80% utilization
        $distanceScore = $this->estimated_distance ? min(20 / $this->estimated_distance, 1.0) : 0.5;

        return round(($utilizationScore + $distanceScore) / 2 * 100, 1);
    }
}
