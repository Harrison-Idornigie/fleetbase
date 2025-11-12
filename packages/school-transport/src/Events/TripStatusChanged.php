<?php

namespace Fleetbase\SchoolTransportEngine\Events;

use Fleetbase\SchoolTransportEngine\Models\Trip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $trip;
    public $oldStatus;
    public $newStatus;

    /**
     * Create a new event instance.
     *
     * @param Trip $trip
     * @param string $oldStatus
     * @param string $newStatus
     */
    public function __construct(Trip $trip, string $oldStatus, string $newStatus)
    {
        $this->trip = $trip;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return [
            new Channel("company.{$this->trip->company_uuid}.school-transport"),
            new Channel("company.{$this->trip->company_uuid}.trip.{$this->trip->uuid}"),
            new Channel("company.{$this->trip->company_uuid}.route.{$this->trip->route_uuid}"),
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
            'type' => 'trip_status_changed',
            'trip' => [
                'uuid' => $this->trip->uuid,
                'public_id' => $this->trip->public_id,
                'route_uuid' => $this->trip->route_uuid,
                'bus_uuid' => $this->trip->bus_uuid,
                'driver_uuid' => $this->trip->driver_uuid,
                'trip_type' => $this->trip->trip_type,
                'status' => $this->trip->status,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
                'total_students' => $this->trip->total_students,
                'students_checked_in' => $this->trip->students_checked_in,
                'students_checked_out' => $this->trip->students_checked_out,
                'total_stops' => $this->trip->total_stops,
                'completed_stops' => $this->trip->completed_stops,
                'current_location' => $this->trip->current_location,
                'scheduled_start_time' => $this->trip->scheduled_start_time?->toIso8601String(),
                'actual_start_time' => $this->trip->actual_start_time?->toIso8601String(),
                'delay_minutes' => $this->trip->delay_minutes,
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
        return 'trip.status.changed';
    }
}
