<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alert extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_alerts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'alert_type',
        'severity',
        'title',
        'message',
        'bus_uuid',
        'trip_uuid',
        'driver_uuid',
        'student_uuid',
        'route_uuid',
        'location',
        'coordinates',
        'status',
        'acknowledged_at',
        'acknowledged_by_uuid',
        'resolved_at',
        'resolved_by_uuid',
        'resolution_notes',
        'metadata',
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
        'coordinates' => 'array',
        'location' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the bus associated with this alert.
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'bus_uuid');
    }

    /**
     * Get the trip associated with this alert.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_uuid');
    }

    /**
     * Get the driver associated with this alert.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_uuid');
    }

    /**
     * Get the student associated with this alert.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_uuid');
    }

    /**
     * Get the route associated with this alert.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SchoolRoute::class, 'route_uuid');
    }

    /**
     * Get the user who acknowledged this alert.
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'acknowledged_by_uuid');
    }

    /**
     * Get the user who resolved this alert.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'resolved_by_uuid');
    }

    /**
     * Get the company that owns this alert.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\Company::class);
    }

    /**
     * Get the user who created this alert.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'created_by_uuid');
    }

    /**
     * Get the user who last updated this alert.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'updated_by_uuid');
    }

    /**
     * Scope to filter alerts by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('alert_type', $type);
    }

    /**
     * Scope to filter alerts by severity.
     */
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to filter alerts by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter unresolved alerts.
     */
    public function scopeUnresolved($query)
    {
        return $query->where('status', '!=', 'resolved');
    }

    /**
     * Scope to filter active alerts.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'acknowledged']);
    }

    /**
     * Scope to filter alerts by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get the severity display name.
     */
    public function getSeverityDisplayAttribute(): string
    {
        return match($this->severity) {
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
            default => ucfirst($this->severity ?? 'Unknown')
        };
    }

    /**
     * Get the alert type display name.
     */
    public function getAlertTypeDisplayAttribute(): string
    {
        return match($this->alert_type) {
            'delay' => 'Trip Delay',
            'emergency' => 'Emergency',
            'maintenance' => 'Maintenance Required',
            'behavior' => 'Student Behavior',
            'attendance' => 'Attendance Issue',
            'location' => 'Location Alert',
            'system' => 'System Alert',
            default => ucfirst(str_replace('_', ' ', $this->alert_type ?? 'Unknown'))
        };
    }

    /**
     * Get the status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'acknowledged' => 'Acknowledged',
            'investigating' => 'Investigating',
            'resolved' => 'Resolved',
            'dismissed' => 'Dismissed',
            default => ucfirst($this->status ?? 'Unknown')
        };
    }

    /**
     * Check if alert is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'acknowledged', 'investigating']);
    }

    /**
     * Check if alert is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    /**
     * Acknowledge the alert.
     */
    public function acknowledge($userId = null): bool
    {
        $this->status = 'acknowledged';
        $this->acknowledged_at = now();
        $this->acknowledged_by_uuid = $userId ?? auth()->id();

        return $this->save();
    }

    /**
     * Resolve the alert.
     */
    public function resolve($userId = null, $notes = null): bool
    {
        $this->status = 'resolved';
        $this->resolved_at = now();
        $this->resolved_by_uuid = $userId ?? auth()->id();
        $this->resolution_notes = $notes;

        return $this->save();
    }

    /**
     * Get the time to resolution.
     */
    public function getTimeToResolutionAttribute(): ?int
    {
        if ($this->resolved_at && $this->created_at) {
            return $this->created_at->diffInMinutes($this->resolved_at);
        }

        return null;
    }
}