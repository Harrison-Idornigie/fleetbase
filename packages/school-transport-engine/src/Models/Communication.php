<?php

namespace Fleetbase\SchoolTransportEngine\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicid;
use Fleetbase\Traits\TracksApiCredential;
use Fleetbase\Traits\HasApiModelBehavior;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    use HasUuid,
        HasPublicid,
        TracksApiCredential,
        HasApiModelBehavior;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'school_transport_communications';

    /**
     * The type of public Id to generate
     *
     * @var string
     */
    protected $publicIdType = 'comm';

    /**
     * These attributes that can be queried
     *
     * @var array
     */
    protected $searchableColumns = [
        'title',
        'message',
        'type',
        'priority',
        'status'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'title',
        'message',
        'recipients',
        'delivery_channels',
        'priority',
        'route_uuid',
        'student_uuid',
        'status',
        'scheduled_at',
        'sent_at',
        'delivery_status',
        'template_data',
        'requires_acknowledgment',
        'acknowledgments',
        'created_by_uuid',
        'meta',
        'company_uuid'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'recipients' => 'array',
        'delivery_channels' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivery_status' => 'array',
        'template_data' => 'array',
        'requires_acknowledgment' => 'boolean',
        'acknowledgments' => 'array',
        'meta' => 'array'
    ];

    /**
     * Dynamic attributes that are appended to object
     *
     * @var array
     */
    protected $appends = [
        'is_scheduled',
        'is_sent',
        'delivery_rate',
        'acknowledgment_rate',
        'formatted_message'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Check if communication is scheduled
     */
    public function getIsScheduledAttribute(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_at && $this->scheduled_at->isFuture();
    }

    /**
     * Check if communication has been sent
     */
    public function getIsSentAttribute(): bool
    {
        return in_array($this->status, ['sent', 'delivered']);
    }

    /**
     * Get delivery success rate
     */
    public function getDeliveryRateAttribute(): float
    {
        if (!$this->delivery_status || empty($this->delivery_status)) {
            return 0;
        }

        $total = count($this->delivery_status);
        $delivered = collect($this->delivery_status)->where('status', 'delivered')->count();

        return $total > 0 ? round(($delivered / $total) * 100, 2) : 0;
    }

    /**
     * Get acknowledgment rate
     */
    public function getAcknowledmentRateAttribute(): float
    {
        if (!$this->requires_acknowledgment || !$this->acknowledgments) {
            return 0;
        }

        $total = count($this->recipients ?? []);
        $acknowledged = count($this->acknowledgments);

        return $total > 0 ? round(($acknowledged / $total) * 100, 2) : 0;
    }

    /**
     * Get formatted message with template variables replaced
     */
    public function getFormattedMessageAttribute(): string
    {
        $message = $this->message;

        if ($this->template_data) {
            foreach ($this->template_data as $key => $value) {
                $message = str_replace("{{$key}}", $value, $message);
            }
        }

        return $message;
    }

    /**
     * Get the route this communication is about
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SchoolRoute::class, 'route_uuid');
    }

    /**
     * Get the student this communication is about
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_uuid');
    }

    /**
     * Scope to filter by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter pending communications
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'scheduled']);
    }

    /**
     * Scope to filter sent communications
     */
    public function scopeSent($query)
    {
        return $query->whereIn('status', ['sent', 'delivered']);
    }

    /**
     * Scope to filter high priority communications
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    /**
     * Scope to filter communications for a specific route
     */
    public function scopeForRoute($query, $routeId)
    {
        return $query->where('route_uuid', $routeId);
    }

    /**
     * Scope to filter communications for a specific student
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_uuid', $studentId);
    }

    /**
     * Scope to filter communications requiring acknowledgment
     */
    public function scopeRequiringAcknowledgment($query)
    {
        return $query->where('requires_acknowledgment', true);
    }

    /**
     * Send the communication immediately
     */
    public function send(): bool
    {
        if ($this->status !== 'draft' && $this->status !== 'scheduled') {
            return false;
        }

        // Here you would integrate with actual sending services
        // For now, we'll just update the status
        $this->status = 'sent';
        $this->sent_at = now();

        // Mock delivery status
        $deliveryStatus = [];
        foreach ($this->recipients as $recipient) {
            $deliveryStatus[] = [
                'recipient' => $recipient,
                'status' => 'delivered',
                'sent_at' => now()->toISOString(),
                'delivered_at' => now()->addSeconds(rand(1, 30))->toISOString()
            ];
        }

        $this->delivery_status = $deliveryStatus;

        return $this->save();
    }

    /**
     * Schedule the communication
     */
    public function schedule(\DateTime $scheduledAt): bool
    {
        $this->status = 'scheduled';
        $this->scheduled_at = $scheduledAt;

        return $this->save();
    }

    /**
     * Mark as acknowledged by a recipient
     */
    public function acknowledge(string $recipientId, ?array $data = null): bool
    {
        if (!$this->requires_acknowledgment) {
            return false;
        }

        $acknowledgments = $this->acknowledgments ?: [];

        $acknowledgments[$recipientId] = [
            'acknowledged_at' => now()->toISOString(),
            'data' => $data
        ];

        $this->acknowledgments = $acknowledgments;

        return $this->save();
    }

    /**
     * Check if a specific recipient has acknowledged
     */
    public function isAcknowledgedBy(string $recipientId): bool
    {
        return isset($this->acknowledgments[$recipientId]);
    }

    /**
     * Get unacknowledged recipients
     */
    public function getUnacknowledgedRecipients(): array
    {
        if (!$this->requires_acknowledgment || !$this->recipients) {
            return [];
        }

        return array_diff($this->recipients, array_keys($this->acknowledgments ?: []));
    }

    /**
     * Apply message template
     */
    public function applyTemplate(array $data): void
    {
        $this->template_data = array_merge($this->template_data ?: [], $data);

        // Apply common templates based on type
        $templates = config('school-transport.communications.notification_templates', []);

        if (isset($templates[$this->type])) {
            $template = $templates[$this->type];

            foreach ($data as $key => $value) {
                $template = str_replace("{{$key}}", $value, $template);
            }

            $this->message = $template;
        }
    }

    /**
     * Create emergency alert
     */
    public static function createEmergencyAlert(
        string $title,
        string $message,
        array $recipients = ['all'],
        ?string $routeId = null
    ): self {
        return static::create([
            'type' => 'emergency',
            'title' => $title,
            'message' => $message,
            'recipients' => $recipients,
            'delivery_channels' => ['email', 'sms', 'app_notification'],
            'priority' => 'urgent',
            'route_uuid' => $routeId,
            'status' => 'draft',
            'requires_acknowledgment' => true,
            'company_uuid' => session('company')
        ]);
    }

    /**
     * Create route delay notification
     */
    public static function createDelayNotification(
        string $routeId,
        int $delayMinutes,
        ?string $reason = null
    ): self {
        $route = SchoolRoute::find($routeId);

        $communication = static::create([
            'type' => 'notification',
            'title' => "Bus Delay - Route {$route->route_name}",
            'message' => config('school-transport.communications.notification_templates.bus_delay'),
            'recipients' => ['route_parents'], // This would be resolved to actual parent IDs
            'delivery_channels' => ['email', 'sms'],
            'priority' => 'high',
            'route_uuid' => $routeId,
            'status' => 'draft',
            'template_data' => [
                'route_name' => $route->route_name,
                'delay_minutes' => $delayMinutes,
                'reason' => $reason
            ],
            'company_uuid' => session('company')
        ]);

        $communication->applyTemplate($communication->template_data);

        return $communication;
    }
}
