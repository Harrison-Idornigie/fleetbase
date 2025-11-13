<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Trip;
use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\SchoolTransportEngine\Models\Driver;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Payload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TripService
{
    /**
     * Get all trips with optional filtering
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTrips(array $filters = [])
    {
        $query = Trip::where('company_uuid', session('company'));

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['route'])) {
            $query->where('route_uuid', $filters['route']);
        }

        if (isset($filters['bus'])) {
            $query->where('bus_uuid', $filters['bus']);
        }

        if (isset($filters['driver'])) {
            $query->where('driver_uuid', $filters['driver']);
        }

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->with(['route', 'bus', 'driver', 'assignments'])->get();
    }

    /**
     * Create a new trip
     *
     * @param array $data
     * @return Trip
     */
    public function createTrip(array $data): Trip
    {
        DB::beginTransaction();
        try {
            $data['company_uuid'] = $data['company_uuid'] ?? session('company');
            $data['created_by_uuid'] = $data['created_by_uuid'] ?? auth()->id();

            $trip = Trip::create($data);

            // Create corresponding FleetOps Order for tracking integration
            if (config('school-transport.fleetops_integration', true)) {
                $this->createFleetOpsOrder($trip);
            }

            DB::commit();
            return $trip->load(['route', 'bus', 'driver']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create trip: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing trip
     *
     * @param string $uuid
     * @param array $data
     * @return Trip
     */
    public function updateTrip(string $uuid, array $data): Trip
    {
        DB::beginTransaction();
        try {
            $trip = Trip::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            $trip->update($data);

            // Update FleetOps Order if it exists
            if ($trip->fleetops_order_uuid && config('school-transport.fleetops_integration', true)) {
                $this->updateFleetOpsOrder($trip);
            }

            DB::commit();
            return $trip->fresh(['route', 'bus', 'driver', 'assignments']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update trip: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a trip
     *
     * @param string $uuid
     * @return bool
     */
    public function deleteTrip(string $uuid): bool
    {
        DB::beginTransaction();
        try {
            $trip = Trip::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            if (in_array($trip->status, ['in_progress', 'completed'])) {
                throw new \Exception('Cannot delete a trip that is in progress or completed');
            }

            // Delete associated FleetOps Order if exists
            if ($trip->fleetops_order_uuid) {
                $this->deleteFleetOpsOrder($trip);
            }

            $trip->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete trip: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Start a trip
     *
     * @param string $uuid
     * @return Trip
     */
    public function startTrip(string $uuid): Trip
    {
        DB::beginTransaction();
        try {
            $trip = Trip::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            if ($trip->status !== 'scheduled') {
                throw new \Exception('Only scheduled trips can be started');
            }

            $trip->update([
                'status' => 'in_progress',
                'actual_start_time' => now(),
            ]);

            // Update FleetOps Order status
            if ($trip->fleetops_order_uuid) {
                $this->updateFleetOpsOrderStatus($trip, 'dispatched');
            }

            DB::commit();
            return $trip->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to start trip: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Complete a trip
     *
     * @param string $uuid
     * @param array $completionData
     * @return Trip
     */
    public function completeTrip(string $uuid, array $completionData = []): Trip
    {
        DB::beginTransaction();
        try {
            $trip = Trip::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            if ($trip->status !== 'in_progress') {
                throw new \Exception('Only trips in progress can be completed');
            }

            $trip->update(array_merge([
                'status' => 'completed',
                'actual_end_time' => now(),
            ], $completionData));

            // Update FleetOps Order status
            if ($trip->fleetops_order_uuid) {
                $this->updateFleetOpsOrderStatus($trip, 'completed');
            }

            DB::commit();
            return $trip->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete trip: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel a trip
     *
     * @param string $uuid
     * @param string $reason
     * @return Trip
     */
    public function cancelTrip(string $uuid, string $reason): Trip
    {
        DB::beginTransaction();
        try {
            $trip = Trip::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            if (in_array($trip->status, ['completed', 'cancelled'])) {
                throw new \Exception('Cannot cancel a trip that is already completed or cancelled');
            }

            $trip->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            // Update FleetOps Order status
            if ($trip->fleetops_order_uuid) {
                $this->updateFleetOpsOrderStatus($trip, 'canceled');
            }

            DB::commit();
            return $trip->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel trip: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get trip details with tracking information
     *
     * @param string $uuid
     * @return Trip
     */
    public function getTripDetails(string $uuid): Trip
    {
        return Trip::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->with([
                'route.stops',
                'bus',
                'driver',
                'assignments.student',
                'attendance',
                'trackingLogs' => function ($query) {
                    $query->orderBy('timestamp', 'desc')->limit(100);
                },
            ])
            ->firstOrFail();
    }

    /**
     * Get active trips
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveTrips()
    {
        return Trip::where('company_uuid', session('company'))
            ->where('status', 'in_progress')
            ->with(['route', 'bus', 'driver', 'assignments'])
            ->get();
    }

    /**
     * Get trip statistics
     *
     * @param array $filters
     * @return array
     */
    public function getTripStatistics(array $filters = []): array
    {
        $query = Trip::where('company_uuid', session('company'));

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('date', [$filters['date_from'], $filters['date_to']]);
        } else {
            $query->where('date', '>=', now()->subDays(30));
        }

        $trips = $query->get();

        return [
            'total_trips' => $trips->count(),
            'completed_trips' => $trips->where('status', 'completed')->count(),
            'cancelled_trips' => $trips->where('status', 'cancelled')->count(),
            'in_progress_trips' => $trips->where('status', 'in_progress')->count(),
            'scheduled_trips' => $trips->where('status', 'scheduled')->count(),
            'total_distance' => round($trips->sum('distance'), 2),
            'total_duration' => round($trips->sum('duration'), 2),
            'average_distance' => $trips->count() > 0 ? round($trips->avg('distance'), 2) : 0,
            'average_duration' => $trips->count() > 0 ? round($trips->avg('duration'), 2) : 0,
            'on_time_percentage' => $this->calculateOnTimePercentage($trips),
        ];
    }

    /**
     * Create FleetOps Order for trip tracking
     *
     * @param Trip $trip
     * @return void
     */
    protected function createFleetOpsOrder(Trip $trip): void
    {
        try {
            $order = Order::create([
                'company_uuid' => $trip->company_uuid,
                'type' => 'school_transport',
                'status' => 'created',
                'driver_assigned_uuid' => $trip->driver_uuid,
                'vehicle_assigned_uuid' => $trip->bus_uuid,
                'scheduled_at' => $trip->scheduled_start_time,
                'meta' => [
                    'school_transport_trip_uuid' => $trip->uuid,
                    'route_uuid' => $trip->route_uuid,
                    'trip_type' => $trip->type,
                ],
            ]);

            $trip->update(['fleetops_order_uuid' => $order->uuid]);
        } catch (\Exception $e) {
            Log::warning('Failed to create FleetOps Order for trip: ' . $e->getMessage());
        }
    }

    /**
     * Update FleetOps Order
     *
     * @param Trip $trip
     * @return void
     */
    protected function updateFleetOpsOrder(Trip $trip): void
    {
        try {
            if ($order = Order::find($trip->fleetops_order_uuid)) {
                $order->update([
                    'driver_assigned_uuid' => $trip->driver_uuid,
                    'vehicle_assigned_uuid' => $trip->bus_uuid,
                    'scheduled_at' => $trip->scheduled_start_time,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to update FleetOps Order: ' . $e->getMessage());
        }
    }

    /**
     * Update FleetOps Order status
     *
     * @param Trip $trip
     * @param string $status
     * @return void
     */
    protected function updateFleetOpsOrderStatus(Trip $trip, string $status): void
    {
        try {
            if ($order = Order::find($trip->fleetops_order_uuid)) {
                $order->update(['status' => $status]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to update FleetOps Order status: ' . $e->getMessage());
        }
    }

    /**
     * Delete FleetOps Order
     *
     * @param Trip $trip
     * @return void
     */
    protected function deleteFleetOpsOrder(Trip $trip): void
    {
        try {
            if ($order = Order::find($trip->fleetops_order_uuid)) {
                $order->delete();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete FleetOps Order: ' . $e->getMessage());
        }
    }

    /**
     * Calculate on-time percentage for trips
     *
     * @param \Illuminate\Database\Eloquent\Collection $trips
     * @return float
     */
    protected function calculateOnTimePercentage($trips): float
    {
        $completedTrips = $trips->where('status', 'completed');

        if ($completedTrips->count() === 0) {
            return 0;
        }

        $onTimeTrips = $completedTrips->filter(function ($trip) {
            if (!$trip->actual_end_time || !$trip->scheduled_end_time) {
                return false;
            }
            return $trip->actual_end_time <= $trip->scheduled_end_time->addMinutes(5);
        });

        return round(($onTimeTrips->count() / $completedTrips->count()) * 100, 2);
    }
}
