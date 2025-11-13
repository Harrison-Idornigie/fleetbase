<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\TrackingLog;
use Fleetbase\SchoolTransportEngine\Models\Trip;
use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\FleetOps\Models\Position;
use Illuminate\Support\Facades\Log;

class TrackingService
{
    /**
     * Record tracking location for a bus
     *
     * @param string $busUuid
     * @param array $locationData
     * @return TrackingLog
     */
    public function recordLocation(string $busUuid, array $locationData): TrackingLog
    {
        try {
            $bus = Bus::where('company_uuid', session('company'))
                ->where('uuid', $busUuid)
                ->firstOrFail();

            $trackingData = array_merge([
                'company_uuid' => session('company'),
                'bus_uuid' => $busUuid,
                'driver_uuid' => $bus->driver_uuid,
                'timestamp' => now(),
            ], $locationData);

            $trackingLog = TrackingLog::create($trackingData);

            // Create FleetOps Position record for integration
            if (config('school-transport.fleetops_integration', true)) {
                $this->createFleetOpsPosition($trackingLog);
            }

            return $trackingLog;
        } catch (\Exception $e) {
            Log::error('Failed to record tracking location: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get tracking history for a bus
     *
     * @param string $busUuid
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTrackingHistory(string $busUuid, array $filters = [])
    {
        $query = TrackingLog::where('company_uuid', session('company'))
            ->where('bus_uuid', $busUuid);

        if (isset($filters['start_time']) && isset($filters['end_time'])) {
            $query->whereBetween('timestamp', [$filters['start_time'], $filters['end_time']]);
        }

        if (isset($filters['trip'])) {
            $query->where('trip_uuid', $filters['trip']);
        }

        return $query->orderBy('timestamp', 'desc')->get();
    }

    /**
     * Get latest location for a bus
     *
     * @param string $busUuid
     * @return TrackingLog|null
     */
    public function getLatestLocation(string $busUuid): ?TrackingLog
    {
        return TrackingLog::where('company_uuid', session('company'))
            ->where('bus_uuid', $busUuid)
            ->latest('timestamp')
            ->first();
    }

    /**
     * Get all active bus locations
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveBusLocations()
    {
        // Get latest location for each active bus
        return TrackingLog::where('company_uuid', session('company'))
            ->whereHas('bus', function ($query) {
                $query->where('is_active', true);
            })
            ->whereHas('trip', function ($query) {
                $query->where('status', 'in_progress');
            })
            ->where('timestamp', '>=', now()->subMinutes(15))
            ->with(['bus', 'driver', 'trip'])
            ->get()
            ->groupBy('bus_uuid')
            ->map(function ($logs) {
                return $logs->sortByDesc('timestamp')->first();
            })
            ->values();
    }

    /**
     * Get trip route tracking
     *
     * @param string $tripUuid
     * @return array
     */
    public function getTripTracking(string $tripUuid): array
    {
        $trip = Trip::where('company_uuid', session('company'))
            ->where('uuid', $tripUuid)
            ->with(['route.stops', 'bus', 'driver'])
            ->firstOrFail();

        $trackingLogs = TrackingLog::where('trip_uuid', $tripUuid)
            ->orderBy('timestamp')
            ->get();

        return [
            'trip' => $trip,
            'tracking_logs' => $trackingLogs,
            'current_location' => $trackingLogs->last(),
            'total_distance' => $this->calculateTotalDistance($trackingLogs),
            'average_speed' => $this->calculateAverageSpeed($trackingLogs),
            'stops_completed' => $this->calculateStopsCompleted($trip, $trackingLogs),
        ];
    }

    /**
     * Get geofence alerts
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getGeofenceAlerts(array $filters = [])
    {
        $query = TrackingLog::where('company_uuid', session('company'))
            ->where('event_type', 'geofence_alert');

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('timestamp', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['bus'])) {
            $query->where('bus_uuid', $filters['bus']);
        }

        return $query->with(['bus', 'driver', 'trip'])->latest('timestamp')->get();
    }

    /**
     * Create FleetOps Position record
     *
     * @param TrackingLog $trackingLog
     * @return void
     */
    protected function createFleetOpsPosition(TrackingLog $trackingLog): void
    {
        try {
            if (!$trackingLog->latitude || !$trackingLog->longitude) {
                return;
            }

            Position::create([
                'company_uuid' => $trackingLog->company_uuid,
                'subject_uuid' => $trackingLog->bus_uuid,
                'subject_type' => 'vehicle',
                'coordinates' => [
                    'latitude' => $trackingLog->latitude,
                    'longitude' => $trackingLog->longitude,
                ],
                'altitude' => $trackingLog->altitude,
                'speed' => $trackingLog->speed,
                'heading' => $trackingLog->heading,
                'created_at' => $trackingLog->timestamp,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create FleetOps Position: ' . $e->getMessage());
        }
    }

    /**
     * Calculate total distance from tracking logs
     *
     * @param \Illuminate\Database\Eloquent\Collection $trackingLogs
     * @return float
     */
    protected function calculateTotalDistance($trackingLogs): float
    {
        $distance = 0;
        $previous = null;

        foreach ($trackingLogs as $log) {
            if ($previous && $log->latitude && $log->longitude && $previous->latitude && $previous->longitude) {
                $distance += $this->haversineDistance(
                    $previous->latitude,
                    $previous->longitude,
                    $log->latitude,
                    $log->longitude
                );
            }
            $previous = $log;
        }

        return round($distance, 2);
    }

    /**
     * Calculate average speed from tracking logs
     *
     * @param \Illuminate\Database\Eloquent\Collection $trackingLogs
     * @return float
     */
    protected function calculateAverageSpeed($trackingLogs): float
    {
        $logsWithSpeed = $trackingLogs->whereNotNull('speed');

        if ($logsWithSpeed->count() === 0) {
            return 0;
        }

        return round($logsWithSpeed->avg('speed'), 2);
    }

    /**
     * Calculate stops completed
     *
     * @param Trip $trip
     * @param \Illuminate\Database\Eloquent\Collection $trackingLogs
     * @return int
     */
    protected function calculateStopsCompleted(Trip $trip, $trackingLogs): int
    {
        // This would need to check if bus came within geofence of each stop
        // Simplified implementation
        return 0;
    }

    /**
     * Calculate distance between two points using Haversine formula
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in kilometers
     */
    protected function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
