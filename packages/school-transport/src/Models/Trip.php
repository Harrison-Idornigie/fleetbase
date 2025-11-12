<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Trip extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_trips';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'trip_id',
        'bus_uuid',
        'driver_uuid',
        'route_uuid',
        'trip_type',
        'scheduled_start_time',
        'scheduled_end_time',
        'actual_start_time',
        'actual_end_time',
        'status',
        'distance_km',
        'estimated_duration_minutes',
        'actual_duration_minutes',
        'fuel_consumed_liters',
        'weather_conditions',
        'traffic_conditions',
        'incidents',
        'notes',
        'is_delayed',
        'delay_reason',
        'delay_minutes',
        'company_uuid',
        'created_by_uuid',
        'updated_by_uuid'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'scheduled_start_time' => 'datetime',
        'scheduled_end_time' => 'datetime',
        'actual_start_time' => 'datetime',
        'actual_end_time' => 'datetime',
        'distance_km' => 'decimal:2',
        'estimated_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'fuel_consumed_liters' => 'decimal:2',
        'weather_conditions' => 'array',
        'traffic_conditions' => 'array',
        'incidents' => 'array',
        'is_delayed' => 'boolean',
        'delay_minutes' => 'integer'
    ];

    /**
     * Get the bus for this trip.
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'bus_uuid');
    }

    /**
     * Get the driver for this trip.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_uuid');
    }

    /**
     * Get the route for this trip.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SchoolRoute::class, 'route_uuid');
    }

    /**
     * Get the tracking logs for this trip.
     */
    public function trackingLogs(): HasMany
    {
        return $this->hasMany(TrackingLog::class, 'trip_uuid');
    }

    /**
     * Get the attendance records for this trip.
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class, 'trip_uuid');
    }

    /**
     * Get the alerts for this trip.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'trip_uuid');
    }

    /**
     * Get the company that owns this trip.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\Company::class);
    }

    /**
     * Get the user who created this trip.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'created_by_uuid');
    }

    /**
     * Get the user who last updated this trip.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'updated_by_uuid');
    }

    /**
     * Scope to filter trips by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter trips by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('trip_type', $type);
    }

    /**
     * Scope to filter delayed trips.
     */
    public function scopeDelayed($query)
    {
        return $query->where('is_delayed', true);
    }

    /**
     * Scope to filter trips by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_start_time', [$startDate, $endDate]);
    }

    /**
     * Get the status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'delayed' => 'Delayed',
            default => ucfirst($this->status ?? 'Unknown')
        };
    }

    /**
     * Get the trip type display name.
     */
    public function getTripTypeDisplayAttribute(): string
    {
        return match ($this->trip_type) {
            'morning_pickup' => 'Morning Pickup',
            'afternoon_dropoff' => 'Afternoon Dropoff',
            'special' => 'Special Trip',
            default => ucfirst(str_replace('_', ' ', $this->trip_type ?? 'Unknown'))
        };
    }

    /**
     * Check if trip is currently in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if trip is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if trip is delayed.
     */
    public function isDelayed(): bool
    {
        return $this->is_delayed || $this->status === 'delayed';
    }

    /**
     * Get the duration in minutes.
     */
    public function getDurationAttribute(): ?int
    {
        if ($this->actual_start_time && $this->actual_end_time) {
            return $this->actual_start_time->diffInMinutes($this->actual_end_time);
        }

        if ($this->scheduled_start_time && $this->scheduled_end_time) {
            return $this->scheduled_start_time->diffInMinutes($this->scheduled_end_time);
        }

        return $this->actual_duration_minutes ?? $this->estimated_duration_minutes;
    }

    /**
     * Get the delay status.
     */
    public function getDelayStatusAttribute(): string
    {
        if (!$this->is_delayed) {
            return 'On Time';
        }

        if ($this->delay_minutes <= 15) {
            return 'Minor Delay';
        } elseif ($this->delay_minutes <= 30) {
            return 'Moderate Delay';
        } else {
            return 'Major Delay';
        }
    }
}
