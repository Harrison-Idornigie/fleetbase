<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingLog extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_tracking_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'bus_uuid',
        'trip_uuid',
        'driver_uuid',
        'latitude',
        'longitude',
        'speed_kmh',
        'heading',
        'altitude',
        'accuracy',
        'location_name',
        'odometer_km',
        'fuel_level_percent',
        'engine_status',
        'gps_timestamp',
        'device_timestamp',
        'battery_level',
        'network_signal',
        'temperature_celsius',
        'metadata',
        'company_uuid',
        'created_by_uuid'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'speed_kmh' => 'decimal:2',
        'heading' => 'decimal:2',
        'altitude' => 'decimal:2',
        'accuracy' => 'decimal:2',
        'odometer_km' => 'decimal:2',
        'fuel_level_percent' => 'decimal:2',
        'battery_level' => 'decimal:2',
        'network_signal' => 'integer',
        'temperature_celsius' => 'decimal:1',
        'gps_timestamp' => 'datetime',
        'device_timestamp' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the bus for this tracking log.
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'bus_uuid');
    }

    /**
     * Get the trip for this tracking log.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_uuid');
    }

    /**
     * Get the driver for this tracking log.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_uuid');
    }

    /**
     * Get the company that owns this tracking log.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\Company::class);
    }

    /**
     * Get the user who created this tracking log.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'created_by_uuid');
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('gps_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by bus.
     */
    public function scopeForBus($query, $busUuid)
    {
        return $query->where('bus_uuid', $busUuid);
    }

    /**
     * Scope to filter by trip.
     */
    public function scopeForTrip($query, $tripUuid)
    {
        return $query->where('trip_uuid', $tripUuid);
    }

    /**
     * Scope to filter by driver.
     */
    public function scopeForDriver($query, $driverUuid)
    {
        return $query->where('driver_uuid', $driverUuid);
    }

    /**
     * Get the coordinates as an array.
     */
    public function getCoordinatesAttribute(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude
        ];
    }

    /**
     * Get the location as a point string.
     */
    public function getLocationPointAttribute(): string
    {
        return "POINT({$this->longitude} {$this->latitude})";
    }

    /**
     * Check if the tracking data is valid.
     */
    public function isValid(): bool
    {
        return !is_null($this->latitude) &&
               !is_null($this->longitude) &&
               $this->latitude >= -90 && $this->latitude <= 90 &&
               $this->longitude >= -180 && $this->longitude <= 180;
    }

    /**
     * Get the speed in mph.
     */
    public function getSpeedMphAttribute(): float
    {
        return round($this->speed_kmh * 0.621371, 2);
    }

    /**
     * Get the engine status display name.
     */
    public function getEngineStatusDisplayAttribute(): string
    {
        return match($this->engine_status) {
            'on' => 'Running',
            'off' => 'Off',
            'idle' => 'Idle',
            default => ucfirst($this->engine_status ?? 'Unknown')
        };
    }
}