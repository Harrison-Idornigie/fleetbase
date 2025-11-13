<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Alert;
use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\SchoolTransportEngine\Models\Driver;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\Trip;
use Illuminate\Support\Facades\Log;

class AlertService
{
    /**
     * Get all alerts with optional filtering
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAlerts(array $filters = [])
    {
        $query = Alert::where('company_uuid', session('company'));

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['resolved']) && $filters['resolved']) {
            $query->whereNotNull('resolved_at');
        } elseif (isset($filters['resolved']) && !$filters['resolved']) {
            $query->whereNull('resolved_at');
        }

        return $query->with(['bus', 'driver', 'student', 'trip'])->latest('created_at')->get();
    }

    /**
     * Create a new alert
     *
     * @param array $data
     * @return Alert
     */
    public function createAlert(array $data): Alert
    {
        try {
            $data['company_uuid'] = $data['company_uuid'] ?? session('company');
            $data['status'] = $data['status'] ?? 'active';

            $alert = Alert::create($data);

            // Send notifications for high severity alerts
            if (in_array($data['severity'], ['high', 'critical'])) {
                $this->notifyHighSeverityAlert($alert);
            }

            return $alert;
        } catch (\Exception $e) {
            Log::error('Failed to create alert: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Resolve an alert
     *
     * @param string $uuid
     * @param string|null $resolution
     * @return Alert
     */
    public function resolveAlert(string $uuid, ?string $resolution = null): Alert
    {
        $alert = Alert::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_uuid' => auth()->id(),
            'resolution' => $resolution,
        ]);

        return $alert;
    }

    /**
     * Acknowledge an alert
     *
     * @param string $uuid
     * @return Alert
     */
    public function acknowledgeAlert(string $uuid): Alert
    {
        $alert = Alert::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by_uuid' => auth()->id(),
        ]);

        return $alert;
    }

    /**
     * Get active alerts by severity
     *
     * @return array
     */
    public function getActiveAlertsBySeverity(): array
    {
        $alerts = Alert::where('company_uuid', session('company'))
            ->whereNull('resolved_at')
            ->get();

        return [
            'critical' => $alerts->where('severity', 'critical')->values(),
            'high' => $alerts->where('severity', 'high')->values(),
            'medium' => $alerts->where('severity', 'medium')->values(),
            'low' => $alerts->where('severity', 'low')->values(),
        ];
    }

    /**
     * Get alerts for a bus
     *
     * @param string $busUuid
     * @param bool $activeOnly
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBusAlerts(string $busUuid, bool $activeOnly = true)
    {
        $query = Alert::where('company_uuid', session('company'))
            ->where('bus_uuid', $busUuid);

        if ($activeOnly) {
            $query->whereNull('resolved_at');
        }

        return $query->latest('created_at')->get();
    }

    /**
     * Get alerts for a driver
     *
     * @param string $driverUuid
     * @param bool $activeOnly
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDriverAlerts(string $driverUuid, bool $activeOnly = true)
    {
        $query = Alert::where('company_uuid', session('company'))
            ->where('driver_uuid', $driverUuid);

        if ($activeOnly) {
            $query->whereNull('resolved_at');
        }

        return $query->latest('created_at')->get();
    }

    /**
     * Get alert statistics
     *
     * @param array $filters
     * @return array
     */
    public function getAlertStatistics(array $filters = []): array
    {
        $query = Alert::where('company_uuid', session('company'));

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        }

        $alerts = $query->get();

        $activeAlerts = $alerts->whereNull('resolved_at');
        $resolvedAlerts = $alerts->whereNotNull('resolved_at');

        return [
            'total_alerts' => $alerts->count(),
            'active_alerts' => $activeAlerts->count(),
            'resolved_alerts' => $resolvedAlerts->count(),
            'by_type' => $alerts->groupBy('type')->map->count(),
            'by_severity' => $alerts->groupBy('severity')->map->count(),
            'critical_active' => $activeAlerts->where('severity', 'critical')->count(),
            'high_active' => $activeAlerts->where('severity', 'high')->count(),
            'average_resolution_time' => $this->calculateAverageResolutionTime($resolvedAlerts),
        ];
    }

    /**
     * Create geofence violation alert
     *
     * @param string $busUuid
     * @param array $location
     * @return Alert
     */
    public function createGeofenceAlert(string $busUuid, array $location): Alert
    {
        return $this->createAlert([
            'type' => 'geofence_violation',
            'severity' => 'medium',
            'bus_uuid' => $busUuid,
            'title' => 'Bus left designated route area',
            'message' => 'Vehicle has departed from the designated route geofence',
            'data' => [
                'location' => $location,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create speeding alert
     *
     * @param string $busUuid
     * @param float $speed
     * @param float $speedLimit
     * @return Alert
     */
    public function createSpeedingAlert(string $busUuid, float $speed, float $speedLimit): Alert
    {
        return $this->createAlert([
            'type' => 'speeding',
            'severity' => $speed > $speedLimit * 1.2 ? 'high' : 'medium',
            'bus_uuid' => $busUuid,
            'title' => 'Speed limit exceeded',
            'message' => "Vehicle speed ({$speed} mph) exceeds limit ({$speedLimit} mph)",
            'data' => [
                'speed' => $speed,
                'speed_limit' => $speedLimit,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Notify about high severity alert
     *
     * @param Alert $alert
     * @return void
     */
    protected function notifyHighSeverityAlert(Alert $alert): void
    {
        try {
            // This would integrate with notification service
            // For now, just log
            Log::warning('High severity alert created', [
                'alert_type' => $alert->type,
                'severity' => $alert->severity,
                'message' => $alert->message,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send alert notification: ' . $e->getMessage());
        }
    }

    /**
     * Calculate average resolution time for alerts
     *
     * @param \Illuminate\Database\Eloquent\Collection $alerts
     * @return float Minutes
     */
    protected function calculateAverageResolutionTime($alerts): float
    {
        if ($alerts->count() === 0) {
            return 0;
        }

        $totalMinutes = $alerts->sum(function ($alert) {
            return $alert->created_at->diffInMinutes($alert->resolved_at);
        });

        return round($totalMinutes / $alerts->count(), 2);
    }
}
