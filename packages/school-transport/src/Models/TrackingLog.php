<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Fleetbase\LaravelMysqlSpatial\Types\Point;

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
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When a tracking log is created, also create a FleetOps Position record
        static::created(function ($trackingLog) {
            if ($trackingLog->isValid() && $trackingLog->bus) {
                try {
                    \Fleetbase\FleetOps\Models\Position::create([
                        'company_uuid' => $trackingLog->company_uuid,
                        'subject_uuid' => $trackingLog->bus_uuid,
                        'subject_type' => \Fleetbase\SchoolTransportEngine\Models\Bus::class,
                        'coordinates' => new Point($trackingLog->latitude, $trackingLog->longitude),
                        'heading' => $trackingLog->heading,
                        'speed' => $trackingLog->speed_kmh,
                        'altitude' => $trackingLog->altitude,
                        'order_uuid' => $trackingLog->trip_uuid, // Link to trip
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to create FleetOps Position from TrackingLog: ' . $e->getMessage());
                }
            }
        });
    }

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
        'created_by_uuid',
        // ETA-related fields
        'eta_data',
        'proximity_alerts',
        'next_stop_eta_minutes',
        'next_stop_distance_km'
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
        'metadata' => 'array',
        // ETA-related casts
        'eta_data' => 'array',
        'proximity_alerts' => 'array',
        'next_stop_eta_minutes' => 'decimal:2',
        'next_stop_distance_km' => 'decimal:2'
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
        return match ($this->engine_status) {
            'on' => 'Running',
            'off' => 'Off',
            'idle' => 'Idle',
            default => ucfirst($this->engine_status ?? 'Unknown')
        };
    }

    /**
     * Set ETA data for this tracking log.
     *
     * @param array $etaData
     * @return void
     */
    public function setETAData(array $etaData): void
    {
        $this->eta_data = $etaData;
        $this->save();
    }

    /**
     * Get formatted ETA for next stop.
     *
     * @return string
     */
    public function getFormattedNextStopETA(): string
    {
        if (!$this->next_stop_eta_minutes) {
            return 'N/A';
        }

        $minutes = $this->next_stop_eta_minutes;

        if ($minutes < 1) {
            return 'Arriving now';
        } elseif ($minutes < 60) {
            return round($minutes) . ' min';
        } else {
            $hours = floor($minutes / 60);
            $mins = round($minutes % 60);
            return "{$hours}h {$mins}m";
        }
    }

    /**
     * Check if bus is approaching next stop.
     *
     * @param float $thresholdMinutes
     * @return bool
     */
    public function isApproachingNextStop(float $thresholdMinutes = 5): bool
    {
        return $this->next_stop_eta_minutes && $this->next_stop_eta_minutes <= $thresholdMinutes;
    }

    /**
     * Check if bus is very close to next stop.
     *
     * @param float $thresholdKm
     * @return bool
     */
    public function isNearNextStop(float $thresholdKm = 0.5): bool
    {
        return $this->next_stop_distance_km && $this->next_stop_distance_km <= $thresholdKm;
    }

    /**
     * Get proximity alert level.
     *
     * @return string|null
     */
    public function getProximityAlertLevel(): ?string
    {
        if ($this->isNearNextStop(0.2)) {
            return 'immediate';
        } elseif ($this->isNearNextStop(0.5)) {
            return 'very_close';
        } elseif ($this->isApproachingNextStop(2)) {
            return 'close';
        } elseif ($this->isApproachingNextStop(10)) {
            return 'approaching';
        }

        return null;
    }

    /**
     * Add proximity alert.
     *
     * @param string $alertType
     * @param array $alertData
     * @return void
     */
    public function addProximityAlert(string $alertType, array $alertData = []): void
    {
        $alerts = $this->proximity_alerts ?? [];
        $alerts[] = [
            'type' => $alertType,
            'data' => $alertData,
            'timestamp' => now()->toISOString()
        ];

        $this->proximity_alerts = $alerts;
        $this->save();
    }

    /**
     * Calculate ETA to destination using external service.
     *
     * @param array $destination
     * @param array $options
     * @return array|null
     */
    public function calculateETATo(array $destination, array $options = []): ?array
    {
        $etaService = app(\Fleetbase\SchoolTransportEngine\Services\ETACalculationService::class);

        try {
            $origin = [
                'lat' => $this->latitude,
                'lng' => $this->longitude
            ];

            return $etaService->calculateETAWithProvider(
                $options['provider'] ?? 'google',
                $origin,
                $destination,
                $options
            );
        } catch (\Exception $e) {
            \Log::error('Failed to calculate ETA from tracking log', [
                'tracking_log_id' => $this->uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Update next stop ETA information.
     *
     * @param array $stopCoordinates
     * @param array $options
     * @return bool
     */
    public function updateNextStopETA(array $stopCoordinates, array $options = []): bool
    {
        $eta = $this->calculateETATo($stopCoordinates, $options);

        if ($eta) {
            $this->next_stop_eta_minutes = $eta['duration_minutes'];
            $this->next_stop_distance_km = $eta['distance_km'];

            // Store full ETA data
            $this->setETAData([
                'destination' => $stopCoordinates,
                'eta_result' => $eta,
                'calculated_at' => now()->toISOString(),
                'provider' => $options['provider'] ?? 'google'
            ]);

            return true;
        }

        return false;
    }
}
