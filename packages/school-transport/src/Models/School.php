<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class School extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_schools';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'phone',
        'email',
        'principal_name',
        'school_type',
        'grade_levels',
        'total_students',
        'operating_hours',
        'timezone',
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
        'grade_levels' => 'array',
        'operating_hours' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'total_students' => 'integer'
    ];

    /**
     * Get the students for this school.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'school', 'uuid');
    }

    /**
     * Get the routes that serve this school.
     */
    public function routes(): HasMany
    {
        return $this->hasMany(SchoolRoute::class, 'school_uuid');
    }

    /**
     * Get the company that owns this school.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\Company::class);
    }

    /**
     * Get the user who created this school.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'created_by_uuid');
    }

    /**
     * Get the user who last updated this school.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'updated_by_uuid');
    }

    /**
     * Scope to filter active schools.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the full address string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->zip_code,
            $this->country
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get the school type display name.
     */
    public function getSchoolTypeDisplayAttribute(): string
    {
        return match($this->school_type) {
            'elementary' => 'Elementary School',
            'middle' => 'Middle School',
            'high' => 'High School',
            'k12' => 'K-12 School',
            default => ucfirst($this->school_type ?? 'Unknown')
        };
    }
}