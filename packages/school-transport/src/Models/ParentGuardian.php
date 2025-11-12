<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParentGuardian extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_parent_guardians';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_uuid',
        'user_uuid',
        'relationship',
        'is_primary_contact',
        'can_receive_notifications',
        'can_pickup_student',
        'phone',
        'alternate_phone',
        'email',
        'address',
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
        'is_primary_contact' => 'boolean',
        'can_receive_notifications' => 'boolean',
        'can_pickup_student' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Get the student this guardian is associated with.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_uuid');
    }

    /**
     * Get the user account associated with this guardian.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'user_uuid');
    }

    /**
     * Get the communications sent to this guardian.
     */
    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'recipient_uuid');
    }

    /**
     * Get the company that this guardian belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\Company::class);
    }

    /**
     * Get the user who created this guardian.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'created_by_uuid');
    }

    /**
     * Get the user who last updated this guardian.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\User::class, 'updated_by_uuid');
    }

    /**
     * Scope to filter primary contacts.
     */
    public function scopePrimaryContacts($query)
    {
        return $query->where('is_primary_contact', true);
    }

    /**
     * Scope to filter guardians who can receive notifications.
     */
    public function scopeCanReceiveNotifications($query)
    {
        return $query->where('can_receive_notifications', true);
    }

    /**
     * Scope to filter guardians who can pickup students.
     */
    public function scopeCanPickupStudents($query)
    {
        return $query->where('can_pickup_student', true);
    }

    /**
     * Scope to filter by relationship type.
     */
    public function scopeByRelationship($query, $relationship)
    {
        return $query->where('relationship', $relationship);
    }

    /**
     * Get the relationship display name.
     */
    public function getRelationshipDisplayAttribute(): string
    {
        return match($this->relationship) {
            'mother' => 'Mother',
            'father' => 'Father',
            'guardian' => 'Guardian',
            'grandparent' => 'Grandparent',
            'other' => 'Other',
            default => ucfirst($this->relationship ?? 'Unknown')
        };
    }

    /**
     * Get the full name from user or provided info.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        // If no user account, we might need to store name separately
        // For now, return relationship
        return $this->relationship_display;
    }

    /**
     * Check if this guardian is the primary contact.
     */
    public function isPrimaryContact(): bool
    {
        return $this->is_primary_contact;
    }

    /**
     * Check if this guardian can receive notifications.
     */
    public function canReceiveNotifications(): bool
    {
        return $this->can_receive_notifications;
    }

    /**
     * Check if this guardian can pickup the student.
     */
    public function canPickupStudent(): bool
    {
        return $this->can_pickup_student;
    }
}