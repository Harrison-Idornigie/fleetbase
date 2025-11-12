<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Stop extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_stops';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'route_uuid',
        'stop_name',
        'address',
        'latitude',
        'longitude',
        'sequence_order',
        'estimated_arrival_time',
        'estimated_departure_time',
        'actual_arrival_time',
        'actual_departure_time',
        'stop_type',
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
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'sequence_order' => 'integer',
        'estimated_arrival_time' => 'datetime',
        'estimated_departure_time' => 'datetime',
        'actual_arrival_time' => 'datetime',
        'actual_departure_time' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Get the route this stop belongs to.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SchoolRoute::class, 'route_uuid');
    }

    /**
     * Get the students assigned to this stop.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'school_transport_stop_students', 'stop_uuid', 'student_uuid')
                    ->withPivot('pickup_time', 'dropoff_time', 'sequence_order')
                    ->withTimestamps();
    }

    /**
     * Get the company that owns this stop.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\Company::class);
    }

    /**
     * Get the user who created this stop.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'created_by_uuid');
    }

    /**
     * Get the user who last updated this stop.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'updated_by_uuid');
    }

    /**
     * Scope to filter active stops.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter stops by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('stop_type', $type);
    }

    /**
     * Scope to order stops by sequence.
     */
    public function scopeInSequence($query)
    {
        return $query->orderBy('sequence_order');
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
     * Get the stop type display name.
     */
    public function getStopTypeDisplayAttribute(): string
    {
        return match($this->stop_type) {
            'pickup' => 'Pickup Point',
            'dropoff' => 'Drop-off Point',
            'both' => 'Pickup & Drop-off',
            'school' => 'School',
            default => ucfirst($this->stop_type ?? 'Unknown')
        };
    }

    /**
     * Check if stop is for pickup.
     */
    public function isPickupStop(): bool
    {
        return in_array($this->stop_type, ['pickup', 'both']);
    }

    /**
     * Check if stop is for dropoff.
     */
    public function isDropoffStop(): bool
    {
        return in_array($this->stop_type, ['dropoff', 'both']);
    }

    /**
     * Check if stop is a school.
     */
    public function isSchoolStop(): bool
    {
        return $this->stop_type === 'school';
    }

    /**
     * Get the number of students at this stop.
     */
    public function getStudentCountAttribute(): int
    {
        return $this->students()->count();
    }

    /**
     * Calculate distance to another stop (in kilometers).
     */
    public function distanceTo(Stop $otherStop): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($otherStop->latitude - $this->latitude);
        $lonDelta = deg2rad($otherStop->longitude - $this->longitude);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($otherStop->latitude)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}