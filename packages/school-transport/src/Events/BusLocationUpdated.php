<?php

namespace Fleetbase\SchoolTransportEngine\Events;

use Fleetbase\SchoolTransportEngine\Models\TrackingLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $trackingLog;
    public $busUuid;
    public $tripUuid;
    public $companyUuid;

    /**
     * Create a new event instance.
     *
     * @param TrackingLog $trackingLog
     */
    public function __construct(TrackingLog $trackingLog)
    {
        $this->trackingLog = $trackingLog;
        $this->busUuid = $trackingLog->bus_uuid;
        $this->tripUuid = $trackingLog->trip_uuid;
        $this->companyUuid = $trackingLog->company_uuid;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return [
            new Channel("company.{$this->companyUuid}.school-transport"),
            new Channel("company.{$this->companyUuid}.bus.{$this->busUuid}"),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'type' => 'bus_location_updated',
            'tracking_log' => [
                'uuid' => $this->trackingLog->uuid,
                'public_id' => $this->trackingLog->public_id,
                'bus_uuid' => $this->trackingLog->bus_uuid,
                'trip_uuid' => $this->trackingLog->trip_uuid,
                'driver_uuid' => $this->trackingLog->driver_uuid,
                'latitude' => $this->trackingLog->latitude,
                'longitude' => $this->trackingLog->longitude,
                'altitude' => $this->trackingLog->altitude,
                'speed' => $this->trackingLog->speed,
                'heading' => $this->trackingLog->heading,
                'accuracy' => $this->trackingLog->accuracy,
                'timestamp' => $this->trackingLog->timestamp?->toIso8601String(),
                'event_type' => $this->trackingLog->event_type,
                'status' => $this->trackingLog->status,
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
        return 'bus.location.updated';
    }
}
