<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_drivers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'driver_id',
        'user_uuid',
        'first_name',
        'last_name',
        'phone',
        'email',
        'license_number',
        'license_expiry',
        'license_class',
        'date_of_birth',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'years_experience',
        'certifications',
        'current_status',
        'last_location',
        'last_location_updated_at',
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
        'license_expiry' => 'date',
        'date_of_birth' => 'date',
        'last_location_updated_at' => 'datetime',
        'years_experience' => 'integer',
        'certifications' => 'array',
        'last_location' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Get the buses assigned to this driver.
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class, 'driver_uuid');
    }

    /**
     * Get the current bus assigned to this driver.
     */
    public function currentBus(): HasOne
    {
        return $this->hasOne(Bus::class, 'driver_uuid')->where('is_active', true);
    }

    /**
     * Get the trips driven by this driver.
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'driver_uuid');
    }

    /**
     * Get the user account associated with this driver.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'user_uuid');
    }

    /**
     * Get the company that employs this driver.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\Company::class);
    }

    /**
     * Get the user who created this driver.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'created_by_uuid');
    }

    /**
     * Get the user who last updated this driver.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'updated_by_uuid');
    }

    /**
     * Scope to filter active drivers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter available drivers.
     */
    public function scopeAvailable($query)
    {
        return $query->where('current_status', 'available');
    }

    /**
     * Scope to filter drivers by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('current_status', $status);
    }

    /**
     * Get the full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the age attribute.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    /**
     * Get the status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->current_status) {
            'available' => 'Available',
            'on_route' => 'On Route',
            'off_duty' => 'Off Duty',
            'on_break' => 'On Break',
            default => ucfirst($this->current_status ?? 'Unknown')
        };
    }

    /**
     * Check if license is expired.
     */
    public function licenseExpired(): bool
    {
        return $this->license_expiry && $this->license_expiry->isPast();
    }

    /**
     * Check if driver is currently assigned to a bus.
     */
    public function isAssigned(): bool
    {
        return $this->buses()->active()->exists();
    }

    /**
     * Get current assignment.
     */
    public function getCurrentAssignment()
    {
        return $this->buses()->active()->with('route')->first();
    }
}