<?php

namespace Fleetbase\SchoolTransportEngine\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ETAUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $etaData;
    public $companyUuid;

    /**
     * Create a new event instance.
     *
     * @param array $etaData
     * @return void
     */
    public function __construct(array $etaData)
    {
        $this->etaData = $etaData;
        $this->companyUuid = $etaData['company_uuid'] ?? session('company');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        $channels = [
            new Channel("company.{$this->companyUuid}.school-transport"),
            new Channel("company.{$this->companyUuid}.eta-updates"),
        ];

        // Add specific channels based on ETA data
        if (isset($this->etaData['bus_id'])) {
            $channels[] = new Channel("company.{$this->companyUuid}.bus.{$this->etaData['bus_id']}.eta");
        }

        if (isset($this->etaData['route_id'])) {
            $channels[] = new Channel("company.{$this->companyUuid}.route.{$this->etaData['route_id']}.eta");
        }

        if (isset($this->etaData['trip_id'])) {
            $channels[] = new Channel("company.{$this->companyUuid}.trip.{$this->etaData['trip_id']}.eta");
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
            'type' => 'eta_updated',
            'eta_data' => $this->etaData,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the event name for broadcasting.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'eta.updated';
    }
}
