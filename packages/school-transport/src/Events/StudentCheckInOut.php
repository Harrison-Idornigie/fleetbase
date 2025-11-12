<?php

namespace Fleetbase\SchoolTransportEngine\Events;

use Fleetbase\SchoolTransportEngine\Models\Trip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentCheckInOut implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $trip;
    public $studentUuid;
    public $action; // 'check_in' or 'check_out'
    public $stopUuid;
    public $timestamp;

    /**
     * Create a new event instance.
     *
     * @param Trip $trip
     * @param string $studentUuid
     * @param string $action
     * @param string|null $stopUuid
     */
    public function __construct(Trip $trip, string $studentUuid, string $action, ?string $stopUuid = null)
    {
        $this->trip = $trip;
        $this->studentUuid = $studentUuid;
        $this->action = $action;
        $this->stopUuid = $stopUuid;
        $this->timestamp = now();
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
            new Channel("company.{$this->trip->company_uuid}.student.{$this->studentUuid}"),
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
            'type' => 'student_check_in_out',
            'action' => $this->action,
            'student_uuid' => $this->studentUuid,
            'trip_uuid' => $this->trip->uuid,
            'stop_uuid' => $this->stopUuid,
            'trip' => [
                'uuid' => $this->trip->uuid,
                'public_id' => $this->trip->public_id,
                'route_uuid' => $this->trip->route_uuid,
                'bus_uuid' => $this->trip->bus_uuid,
                'status' => $this->trip->status,
                'students_checked_in' => $this->trip->students_checked_in,
                'students_checked_out' => $this->trip->students_checked_out,
                'total_students' => $this->trip->total_students,
            ],
            'timestamp' => $this->timestamp->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'student.check_in_out';
    }
}
