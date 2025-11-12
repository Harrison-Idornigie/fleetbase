<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicid;
use Fleetbase\Traits\TracksApiCredential;
use Fleetbase\Traits\HasApiModelBehavior;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusAssignment extends Model
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
    protected $table = 'school_transport_bus_assignments';

    /**
     * The type of public Id to generate
     *
     * @var string
     */
    protected $publicIdType = 'assignment';

    /**
     * These attributes that can be queried
     *
     * @var array
     */
    protected $searchableColumns = [
        'pickup_stop',
        'dropoff_stop',
        'assignment_type',
        'special_instructions'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_uuid',
        'route_uuid',
        'stop_sequence',
        'pickup_stop',
        'pickup_coordinates',
        'pickup_time',
        'dropoff_stop',
        'dropoff_coordinates',
        'dropoff_time',
        'assignment_type',
        'effective_date',
        'end_date',
        'requires_assistance',
        'special_instructions',
        'status',
        'attendance_tracking',
        'meta',
        'company_uuid'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'pickup_coordinates' => 'array',
        'dropoff_coordinates' => 'array',
        'pickup_time' => 'datetime:H:i',
        'dropoff_time' => 'datetime:H:i',
        'effective_date' => 'date',
        'end_date' => 'date',
        'requires_assistance' => 'boolean',
        'attendance_tracking' => 'array',
        'meta' => 'array'
    ];

    /**
     * Dynamic attributes that are appended to object
     *
     * @var array
     */
    protected $appends = [
        'is_active',
        'duration_in_days',
        'attendance_rate',
        'recent_attendance'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Check if assignment is currently active
     */
    public function getIsActiveAttribute(): bool
    {
        $today = now()->toDateString();

        return $this->status === 'active' &&
            $this->effective_date <= $today &&
            ($this->end_date === null || $this->end_date >= $today);
    }

    /**
     * Get assignment duration in days
     */
    public function getDurationInDaysAttribute(): int
    {
        $endDate = $this->end_date ?: now()->toDate();
        return $this->effective_date->diffInDays($endDate);
    }

    /**
     * Get attendance rate percentage
     */
    public function getAttendanceRateAttribute(): float
    {
        $totalDays = $this->attendanceRecords()->count();
        if ($totalDays === 0) {
            return 0;
        }

        $presentDays = $this->attendanceRecords()->where('present', true)->count();
        return round(($presentDays / $totalDays) * 100, 2);
    }

    /**
     * Get recent attendance (last 7 days)
     */
    public function getRecentAttendanceAttribute(): array
    {
        return $this->attendanceRecords()
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->orderBy('date', 'desc')
            ->limit(7)
            ->get()
            ->map(function ($record) {
                return [
                    'date' => $record->date,
                    'present' => $record->present,
                    'event_type' => $record->event_type,
                    'notes' => $record->notes
                ];
            })
            ->toArray();
    }

    /**
     * Get the student for this assignment
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_uuid');
    }

    /**
     * Get the route for this assignment
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SchoolRoute::class, 'route_uuid');
    }

    /**
     * Get attendance records for this assignment
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class, 'assignment_uuid');
    }

    /**
     * Scope to filter active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('effective_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    /**
     * Scope to filter by assignment type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('assignment_type', $type);
    }

    /**
     * Scope to filter assignments requiring assistance
     */
    public function scopeRequiringAssistance($query)
    {
        return $query->where('requires_assistance', true);
    }

    /**
     * Scope to filter by route
     */
    public function scopeForRoute($query, $routeId)
    {
        return $query->where('route_uuid', $routeId);
    }

    /**
     * Scope to filter by student
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_uuid', $studentId);
    }

    /**
     * Check if assignment overlaps with another assignment
     */
    public function overlapsWith(BusAssignment $other): bool
    {
        if ($this->student_uuid !== $other->student_uuid) {
            return false;
        }

        $thisStart = $this->effective_date;
        $thisEnd = $this->end_date ?: now()->addYears(10);
        $otherStart = $other->effective_date;
        $otherEnd = $other->end_date ?: now()->addYears(10);

        return $thisStart <= $otherEnd && $thisEnd >= $otherStart;
    }

    /**
     * Get the pickup location display name
     */
    public function getPickupLocationDisplay(): string
    {
        return $this->pickup_stop ?: "Stop #{$this->stop_sequence}";
    }

    /**
     * Get the dropoff location display name
     */
    public function getDropoffLocationDisplay(): string
    {
        return $this->dropoff_stop ?: $this->route->school ?? 'School';
    }

    /**
     * Calculate estimated pickup time based on route
     */
    public function calculateEstimatedPickupTime(): ?string
    {
        if ($this->pickup_time) {
            return $this->pickup_time;
        }

        if (!$this->route || !$this->route->start_time) {
            return null;
        }

        // Simple calculation: 5 minutes per stop
        $minutesFromStart = ($this->stop_sequence - 1) * 5;

        return now()->parse($this->route->start_time)
            ->addMinutes($minutesFromStart)
            ->format('H:i');
    }

    /**
     * Mark attendance for a specific date
     */
    public function markAttendance(string $date, bool $present, string $eventType = 'pickup', ?string $notes = null): Attendance
    {
        return Attendance::updateOrCreate([
            'student_uuid' => $this->student_uuid,
            'route_uuid' => $this->route_uuid,
            'assignment_uuid' => $this->id,
            'date' => $date,
            'session' => now()->parse($this->pickup_time)->format('H') < 12 ? 'morning' : 'afternoon',
            'event_type' => $eventType,
            'company_uuid' => $this->company_uuid
        ], [
            'present' => $present,
            'notes' => $notes,
            'actual_time' => now(),
            'status' => 'completed'
        ]);
    }

    /**
     * Check if student was present today
     */
    public function wasPresentToday(): ?bool
    {
        $todayAttendance = $this->attendanceRecords()
            ->where('date', now()->toDateString())
            ->first();

        return $todayAttendance ? $todayAttendance->present : null;
    }

    /**
     * Get upcoming pickup time
     */
    public function getUpcomingPickupTime(): ?string
    {
        if (!$this->route || !$this->route->operatesToday()) {
            return null;
        }

        return $this->calculateEstimatedPickupTime();
    }

    /**
     * Check if assignment is for a student with special needs
     */
    public function isForSpecialNeedsStudent(): bool
    {
        return $this->student && $this->student->has_special_needs;
    }

    /**
     * Extend assignment end date
     */
    public function extend(?string $newEndDate = null): void
    {
        $this->end_date = $newEndDate;
        $this->save();
    }

    /**
     * Deactivate assignment
     */
    public function deactivate(?string $endDate = null): void
    {
        $this->status = 'inactive';
        $this->end_date = $endDate ?: now()->toDateString();
        $this->save();
    }
}
