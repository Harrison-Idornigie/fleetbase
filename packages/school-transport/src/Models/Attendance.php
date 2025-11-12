<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicid;
use Fleetbase\Traits\TracksApiCredential;
use Fleetbase\Traits\HasApiModelBehavior;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasUuid,
        HasPublicid,
        TracksApiCredential,
        HasApiModelBehavior;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_attendance';

    /**
     * The type of public Id to generate
     *
     * @var string
     */
    protected $publicIdType = 'attend';

    /**
     * These attributes that can be queried
     *
     * @var array
     */
    protected $searchableColumns = [
        'event_type',
        'location',
        'notes',
        'status'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_uuid',
        'route_uuid',
        'assignment_uuid',
        'date',
        'session',
        'event_type',
        'scheduled_time',
        'actual_time',
        'present',
        'notes',
        'recorded_by_uuid',
        'location',
        'coordinates',
        'status',
        'parent_notification',
        'meta',
        'company_uuid'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'scheduled_time' => 'datetime',
        'actual_time' => 'datetime',
        'present' => 'boolean',
        'coordinates' => 'array',
        'parent_notification' => 'array',
        'meta' => 'array'
    ];

    /**
     * Dynamic attributes that are appended to object
     *
     * @var array
     */
    protected $appends = [
        'delay_minutes',
        'is_on_time',
        'is_late',
        'formatted_time'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Calculate delay in minutes
     */
    public function getDelayMinutesAttribute(): ?int
    {
        if (!$this->scheduled_time || !$this->actual_time) {
            return null;
        }

        return $this->actual_time->diffInMinutes($this->scheduled_time, false);
    }

    /**
     * Check if student was on time
     */
    public function getIsOnTimeAttribute(): bool
    {
        $delay = $this->delay_minutes;
        return $delay !== null && $delay >= -2 && $delay <= 5; // 2 min early to 5 min late
    }

    /**
     * Check if student was late
     */
    public function getIsLateAttribute(): bool
    {
        $delay = $this->delay_minutes;
        return $delay !== null && $delay > 5;
    }

    /**
     * Get formatted time display
     */
    public function getFormattedTimeAttribute(): string
    {
        if (!$this->actual_time) {
            return $this->scheduled_time ? $this->scheduled_time->format('g:i A') : 'Not recorded';
        }

        return $this->actual_time->format('g:i A');
    }

    /**
     * Get the student for this attendance record
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_uuid');
    }

    /**
     * Get the route for this attendance record
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SchoolRoute::class, 'route_uuid');
    }

    /**
     * Get the assignment for this attendance record
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(BusAssignment::class, 'assignment_uuid');
    }

    /**
     * Scope to filter by date
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeForDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by student
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_uuid', $studentId);
    }

    /**
     * Scope to filter by route
     */
    public function scopeForRoute($query, $routeId)
    {
        return $query->where('route_uuid', $routeId);
    }

    /**
     * Scope to filter by session
     */
    public function scopeForSession($query, string $session)
    {
        return $query->where('session', $session);
    }

    /**
     * Scope to filter by event type
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter present students
     */
    public function scopePresent($query)
    {
        return $query->where('present', true);
    }

    /**
     * Scope to filter absent students
     */
    public function scopeAbsent($query)
    {
        return $query->where('present', false);
    }

    /**
     * Scope to filter late students
     */
    public function scopeLate($query)
    {
        return $query->whereRaw('actual_time > DATE_ADD(scheduled_time, INTERVAL 5 MINUTE)');
    }

    /**
     * Scope to filter no-show events
     */
    public function scopeNoShow($query)
    {
        return $query->where('event_type', 'no_show');
    }

    /**
     * Mark student as present
     */
    public function markPresent(?string $location = null, ?array $coordinates = null): bool
    {
        $this->present = true;
        $this->actual_time = now();
        $this->location = $location;
        $this->coordinates = $coordinates;
        $this->status = 'completed';

        return $this->save();
    }

    /**
     * Mark student as absent/no-show
     */
    public function markAbsent(string $reason = 'no_show'): bool
    {
        $this->present = false;
        $this->event_type = $reason;
        $this->status = 'missed';
        $this->notes = $this->notes ? $this->notes . "; {$reason}" : $reason;

        return $this->save();
    }

    /**
     * Send parent notification about attendance
     */
    public function sendParentNotification(string $type = 'absence'): bool
    {
        if (!$this->student || !$this->student->parent_email) {
            return false;
        }

        $notification = [
            'type' => $type,
            'sent_at' => now()->toISOString(),
            'recipient' => $this->student->parent_email,
            'message' => $this->generateNotificationMessage($type)
        ];

        // Here you would integrate with actual notification service

        $notifications = $this->parent_notification ?: [];
        $notifications[] = $notification;

        $this->parent_notification = $notifications;

        return $this->save();
    }

    /**
     * Generate notification message
     */
    protected function generateNotificationMessage(string $type): string
    {
        $studentName = $this->student->full_name;
        $routeName = $this->route->route_name;
        $date = $this->date->format('M j, Y');
        $session = ucfirst($this->session);

        switch ($type) {
            case 'absence':
                return "{$studentName} was not present at their {$session} pickup for route {$routeName} on {$date}.";
            case 'late':
                $delayMinutes = $this->delay_minutes;
                return "{$studentName} was {$delayMinutes} minutes late for their {$session} pickup on route {$routeName} on {$date}.";
            case 'early':
                return "{$studentName} was picked up early for their {$session} pickup on route {$routeName} on {$date}.";
            default:
                return "Attendance update for {$studentName} on route {$routeName} on {$date}.";
        }
    }

    /**
     * Get attendance summary for a date range
     */
    public static function getSummaryForDateRange(string $startDate, string $endDate, ?string $routeId = null): array
    {
        $query = static::forDateRange($startDate, $endDate);

        if ($routeId) {
            $query->forRoute($routeId);
        }

        $records = $query->get();

        return [
            'total_scheduled' => $records->count(),
            'total_present' => $records->where('present', true)->count(),
            'total_absent' => $records->where('present', false)->count(),
            'attendance_rate' => $records->count() > 0 ?
                round(($records->where('present', true)->count() / $records->count()) * 100, 2) : 0,
            'late_count' => $records->filter(function ($record) {
                return $record->is_late;
            })->count(),
            'no_show_count' => $records->where('event_type', 'no_show')->count()
        ];
    }

    /**
     * Get student attendance pattern
     */
    public static function getStudentPattern(string $studentId, int $days = 30): array
    {
        $startDate = now()->subDays($days)->toDateString();
        $endDate = now()->toDateString();

        $records = static::forStudent($studentId)
            ->forDateRange($startDate, $endDate)
            ->orderBy('date', 'desc')
            ->get();

        $pattern = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->toDateString();
            $dayRecord = $records->where('date', $date)->first();

            $pattern[] = [
                'date' => $date,
                'present' => $dayRecord ? $dayRecord->present : null,
                'event_type' => $dayRecord ? $dayRecord->event_type : null,
                'delay_minutes' => $dayRecord ? $dayRecord->delay_minutes : null
            ];
        }

        return $pattern;
    }

    /**
     * Check if attendance affects safety compliance
     */
    public function affectsSafetyCompliance(): bool
    {
        // If student has special needs and was marked absent without proper notification
        return $this->student &&
            $this->student->has_special_needs &&
            !$this->present &&
            empty($this->parent_notification);
    }
}
