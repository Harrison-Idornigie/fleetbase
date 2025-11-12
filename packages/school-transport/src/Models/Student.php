<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicid;
use Fleetbase\Traits\TracksApiCredential;
use Fleetbase\Traits\HasApiModelBehavior;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
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
    protected $table = 'school_transport_students';

    /**
     * The type of public Id to generate
     *
     * @var string
     */
    protected $publicIdType = 'student';

    /**
     * These attributes that can be queried
     *
     * @var array
     */
    protected $searchableColumns = [
        'student_id',
        'first_name',
        'last_name',
        'school',
        'grade',
        'parent_name',
        'parent_email',
        'parent_phone'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_id',
        'first_name',
        'last_name',
        'grade',
        'school',
        'date_of_birth',
        'gender',
        'home_address',
        'home_coordinates',
        'parent_name',
        'parent_email',
        'parent_phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'special_needs',
        'medical_info',
        'pickup_location',
        'pickup_coordinates',
        'dropoff_location',
        'dropoff_coordinates',
        'is_active',
        'photo_url',
        'meta',
        'company_uuid'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'special_needs' => 'array',
        'medical_info' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
        'home_coordinates' => 'array',
        'pickup_coordinates' => 'array',
        'dropoff_coordinates' => 'array'
    ];

    /**
     * Dynamic attributes that are appended to object
     *
     * @var array
     */
    protected $appends = [
        'full_name',
        'age',
        'has_special_needs',
        'active_assignments'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Get the student's full name
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the student's age
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->diffInYears(now());
    }

    /**
     * Check if student has special needs
     */
    public function getHasSpecialNeedsAttribute(): bool
    {
        return !empty($this->special_needs);
    }

    /**
     * Get count of active assignments
     */
    public function getActiveAssignmentsAttribute(): int
    {
        return $this->busAssignments()->where('status', 'active')->count();
    }

    /**
     * Get the bus assignments for this student
     */
    public function busAssignments(): HasMany
    {
        return $this->hasMany(BusAssignment::class, 'student_uuid');
    }

    /**
     * Get active bus assignments
     */
    public function activeBusAssignments(): HasMany
    {
        return $this->hasMany(BusAssignment::class, 'student_uuid')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    /**
     * Get attendance records for this student
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_uuid');
    }

    /**
     * Get communications sent to this student/parent
     */
    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'student_uuid');
    }

    /**
     * Scope to filter by school
     */
    public function scopeForSchool($query, string $school)
    {
        return $query->where('school', $school);
    }

    /**
     * Scope to filter by grade
     */
    public function scopeForGrade($query, string $grade)
    {
        return $query->where('grade', $grade);
    }

    /**
     * Scope to filter active students
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter students with special needs
     */
    public function scopeWithSpecialNeeds($query)
    {
        return $query->whereNotNull('special_needs')
            ->where('special_needs', '!=', '[]');
    }

    /**
     * Get the current route for this student
     */
    public function getCurrentRoute()
    {
        $assignment = $this->activeBusAssignments()->first();
        return $assignment ? $assignment->route : null;
    }

    /**
     * Check if student requires wheelchair accessibility
     */
    public function requiresWheelchairAccess(): bool
    {
        if (!$this->special_needs) {
            return false;
        }

        return in_array('wheelchair_accessible', $this->special_needs);
    }

    /**
     * Get formatted address
     */
    public function getFormattedAddress(): string
    {
        return $this->pickup_location ?: $this->home_address;
    }
}
