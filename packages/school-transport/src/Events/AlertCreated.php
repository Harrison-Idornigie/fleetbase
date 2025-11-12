<?php

namespace Fleetbase\SchoolTransportEngine\Events;

use Fleetbase\SchoolTransportEngine\Models\Alert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;

    /**
     * Create a new event instance.
     *
     * @param Alert $alert
     */
    public function __construct(Alert $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        $channels = [
            new Channel("company.{$this->alert->company_uuid}.school-transport"),
            new Channel("company.{$this->alert->company_uuid}.alerts"),
        ];

        // Add specific entity channels
        if ($this->alert->trip_uuid) {
            $channels[] = new Channel("company.{$this->alert->company_uuid}.trip.{$this->alert->trip_uuid}");
        }
        if ($this->alert->bus_uuid) {
            $channels[] = new Channel("company.{$this->alert->company_uuid}.bus.{$this->alert->bus_uuid}");
        }
        if ($this->alert->route_uuid) {
            $channels[] = new Channel("company.{$this->alert->company_uuid}.route.{$this->alert->route_uuid}");
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'type' => 'alert_created',
            'alert' => [
                'uuid' => $this->alert->uuid,
                'public_id' => $this->alert->public_id,
                'trip_uuid' => $this->alert->trip_uuid,
                'bus_uuid' => $this->alert->bus_uuid,
                'driver_uuid' => $this->alert->driver_uuid,
                'student_uuid' => $this->alert->student_uuid,
                'route_uuid' => $this->alert->route_uuid,
                'alert_type' => $this->alert->alert_type,
                'severity' => $this->alert->severity,
                'title' => $this->alert->title,
                'description' => $this->alert->description,
                'status' => $this->alert->status,
                'location' => $this->alert->location,
                'occurred_at' => $this->alert->occurred_at?->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'alert.created';
    }
}
