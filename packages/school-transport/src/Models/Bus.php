<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bus extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_buses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'bus_number',
        'license_plate',
        'make',
        'model',
        'year',
        'capacity',
        'current_occupancy',
        'status',
        'gps_device_id',
        'driver_uuid',
        'route_uuid',
        'last_maintenance_date',
        'next_maintenance_date',
        'insurance_expiry',
        'registration_expiry',
        'fuel_type',
        'mileage',
        'color',
        'features',
        'is_active',
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
        'capacity' => 'integer',
        'current_occupancy' => 'integer',
        'mileage' => 'integer',
        'year' => 'integer',
        'features' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_maintenance_date' => 'date',
        'next_maintenance_date' => 'date',
        'insurance_expiry' => 'date',
        'registration_expiry' => 'date'
    ];

    /**
     * Get the assignments for this bus.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(BusAssignment::class, 'bus_uuid');
    }

    /**
     * Get the current driver assigned to this bus.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_uuid');
    }

    /**
     * Get the route this bus is assigned to.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SchoolRoute::class, 'route_uuid');
    }

    /**
     * Get the trips for this bus.
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'bus_uuid');
    }

    /**
     * Get the tracking logs for this bus.
     */
    public function trackingLogs(): HasMany
    {
        return $this->hasMany(TrackingLog::class, 'bus_uuid');
    }

    /**
     * Get the company that owns this bus.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\Company::class);
    }

    /**
     * Get the user who created this bus.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'created_by_uuid');
    }

    /**
     * Get the user who last updated this bus.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'updated_by_uuid');
    }

    /**
     * Scope to filter active buses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter available buses.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
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
        return max(0, $this->capacity - $this->current_occupancy);
    }

    /**
     * Get the bus display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->bus_number . ' (' . $this->license_plate . ')';
    }

    /**
     * Get the status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'available' => 'Available',
            'in_route' => 'In Route',
            'maintenance' => 'Under Maintenance',
            'out_of_service' => 'Out of Service',
            default => ucfirst($this->status ?? 'Unknown')
        };
    }

    /**
     * Check if bus needs maintenance.
     */
    public function needsMaintenance(): bool
    {
        return $this->next_maintenance_date && $this->next_maintenance_date->isPast();
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
}