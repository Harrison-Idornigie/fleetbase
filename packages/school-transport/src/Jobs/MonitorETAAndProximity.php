<?php

namespace Fleetbase\SchoolTransportEngine\Jobs;

use Fleetbase\SchoolTransportEngine\Models\Trip;
use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\SchoolTransportEngine\Services\ETACalculationService;
use Fleetbase\SchoolTransportEngine\Services\SmsNotificationService;
use Fleetbase\SchoolTransportEngine\Services\EmailNotificationService;
use Fleetbase\SchoolTransportEngine\Events\ETAUpdated;
use Fleetbase\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorETAAndProximity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $maxExceptions = 3;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info('Starting ETA and proximity monitoring job');

            $etaService = app(ETACalculationService::class);
            $smsService = app(SmsNotificationService::class);
            $emailService = app(EmailNotificationService::class);

            // Get all active trips
            $activeTrips = Trip::with(['bus', 'route', 'assignments.student.parent'])
                ->where('status', 'in_progress')
                ->where('started_at', '>=', now()->subHours(6)) // Only check trips started in last 6 hours
                ->get();

            Log::info("Found {$activeTrips->count()} active trips to monitor");

            foreach ($activeTrips as $trip) {
                $this->processTrip($trip, $etaService, $smsService, $emailService);
            }

            Log::info('ETA and proximity monitoring job completed');
        } catch (\Exception $e) {
            Log::error('ETA monitoring job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Process a single trip for ETA monitoring and proximity alerts
     *
     * @param Trip $trip
     * @param ETACalculationService $etaService
     * @param SmsNotificationService $smsService
     * @param EmailNotificationService $emailService
     * @return void
     */
    protected function processTrip(Trip $trip, ETACalculationService $etaService, SmsNotificationService $smsService, EmailNotificationService $emailService): void
    {
        try {
            if (!$trip->bus || !$trip->route || !$trip->route->stops) {
                Log::warning("Trip {$trip->uuid} missing bus, route, or stops");
                return;
            }

            // Check if ETA monitoring is enabled for this company
            $etaMonitoringEnabled = Setting::lookupCompany($trip->company_uuid, 'school_transport.eta_monitoring_enabled', true);
            if (!$etaMonitoringEnabled) {
                return;
            }

            $companyUuid = $trip->company_uuid;

            // Get ETA notification settings
            $etaThresholdMinutes = Setting::lookupCompany($companyUuid, 'school_transport.eta_notification_threshold', 10);
            $proximityThresholdKm = Setting::lookupCompany($companyUuid, 'school_transport.proximity_threshold_km', 0.5);
            $enableSmsNotifications = Setting::lookupCompany($companyUuid, 'school_transport.enable_sms_notifications', true);
            $enableEmailNotifications = Setting::lookupCompany($companyUuid, 'school_transport.enable_email_notifications', true);

            Log::info("Processing trip {$trip->uuid} for bus {$trip->bus->uuid}");

            // Calculate ETAs for all stops on the route
            $routeETAs = $etaService->calculateRouteETAs($trip, [
                'provider' => Setting::lookupCompany($companyUuid, 'school_transport.eta_provider', 'osrm') // Free OSRM as default
            ]);

            if (!$routeETAs['success']) {
                Log::warning("Failed to calculate ETAs for trip {$trip->uuid}: {$routeETAs['error']}");
                return;
            }

            // Process each stop
            foreach ($routeETAs['etas'] as $stopETA) {
                $this->processStopETA(
                    $trip,
                    $stopETA,
                    $etaThresholdMinutes,
                    $proximityThresholdKm,
                    $enableSmsNotifications,
                    $enableEmailNotifications,
                    $smsService,
                    $emailService,
                    $etaService
                );
            }

            // Broadcast the updated ETAs
            event(new ETAUpdated([
                'trip_id' => $trip->uuid,
                'route_id' => $trip->route->uuid,
                'bus_id' => $trip->bus->uuid,
                'company_uuid' => $companyUuid,
                'etas' => $routeETAs['etas'],
                'current_location' => $routeETAs['current_location'],
                'calculated_at' => now()->toISOString()
            ]));
        } catch (\Exception $e) {
            Log::error("Error processing trip {$trip->uuid}", [
                'error' => $e->getMessage(),
                'trip_id' => $trip->uuid
            ]);
        }
    }

    /**
     * Process ETA for a specific stop and send notifications if needed
     *
     * @param Trip $trip
     * @param array $stopETA
     * @param int $etaThresholdMinutes
     * @param float $proximityThresholdKm
     * @param bool $enableSmsNotifications
     * @param bool $enableEmailNotifications
     * @param SmsNotificationService $smsService
     * @param EmailNotificationService $emailService
     * @param ETACalculationService $etaService
     * @return void
     */
    protected function processStopETA(
        Trip $trip,
        array $stopETA,
        int $etaThresholdMinutes,
        float $proximityThresholdKm,
        bool $enableSmsNotifications,
        bool $enableEmailNotifications,
        SmsNotificationService $smsService,
        EmailNotificationService $emailService,
        ETACalculationService $etaService
    ): void {
        $etaMinutes = $stopETA['eta_minutes'];
        $stopId = $stopETA['stop_id'];
        $stopName = $stopETA['stop_name'];
        $stopCoordinates = $stopETA['coordinates'];

        // Check if bus is near the stop
        $isNearStop = $etaService->isBusNearStop($trip->bus, $stopCoordinates, $proximityThresholdKm);

        // Check if we should send arrival notifications
        $shouldNotify = ($etaMinutes <= $etaThresholdMinutes && $etaMinutes > 0) || $isNearStop;

        if (!$shouldNotify) {
            return;
        }

        // Find students assigned to this stop
        $studentsAtStop = $trip->assignments()
            ->with(['student.parent'])
            ->whereHas('student', function ($query) use ($stopId) {
                $query->where(function ($q) use ($stopId) {
                    $q->where('pickup_stop_id', $stopId)
                        ->orWhere('dropoff_stop_id', $stopId);
                });
            })
            ->get();

        if ($studentsAtStop->isEmpty()) {
            Log::info("No students found for stop {$stopId} on trip {$trip->uuid}");
            return;
        }

        // Check if we've already sent notifications for this stop recently
        $notificationKey = "eta_notification_{$trip->uuid}_{$stopId}";
        $lastNotificationTime = cache($notificationKey);

        if ($lastNotificationTime && now()->diffInMinutes($lastNotificationTime) < 5) {
            return; // Don't spam notifications
        }

        Log::info("Sending arrival notifications for stop {$stopName}", [
            'trip_id' => $trip->uuid,
            'stop_id' => $stopId,
            'eta_minutes' => $etaMinutes,
            'is_near_stop' => $isNearStop,
            'student_count' => $studentsAtStop->count()
        ]);

        // Send notifications to parents
        foreach ($studentsAtStop as $assignment) {
            if (!$assignment->student || !$assignment->student->parent) {
                continue;
            }

            $student = $assignment->student;
            $parent = $student->parent;

            try {
                $message = $this->buildNotificationMessage($trip, $student, $stopName, $etaMinutes, $isNearStop);

                // Send SMS notification
                if ($enableSmsNotifications && $parent->phone) {
                    $smsService->sendArrivalNotification($parent->phone, $message, [
                        'student_name' => $student->name,
                        'bus_number' => $trip->bus->bus_number ?? 'N/A',
                        'stop_name' => $stopName,
                        'eta_minutes' => $etaMinutes,
                        'is_arriving' => $isNearStop
                    ]);
                }

                // Send email notification
                if ($enableEmailNotifications && $parent->email) {
                    $emailService->sendArrivalNotification($parent->email, $message, [
                        'student_name' => $student->name,
                        'bus_number' => $trip->bus->bus_number ?? 'N/A',
                        'stop_name' => $stopName,
                        'eta_minutes' => $etaMinutes,
                        'is_arriving' => $isNearStop,
                        'route_name' => $trip->route->route_name ?? 'Unknown Route'
                    ]);
                }

                Log::info("Sent arrival notifications to parent", [
                    'parent_id' => $parent->uuid,
                    'student_name' => $student->name,
                    'stop_name' => $stopName,
                    'eta_minutes' => $etaMinutes
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send notification to parent", [
                    'parent_id' => $parent->uuid,
                    'student_id' => $student->uuid,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Cache that we've sent notifications for this stop
        cache([$notificationKey => now()], now()->addMinutes(10));
    }

    /**
     * Build notification message
     *
     * @param Trip $trip
     * @param $student
     * @param string $stopName
     * @param float $etaMinutes
     * @param bool $isNearStop
     * @return string
     */
    protected function buildNotificationMessage(Trip $trip, $student, string $stopName, float $etaMinutes, bool $isNearStop): string
    {
        $busNumber = $trip->bus->bus_number ?? 'School Bus';
        $studentName = $student->name;

        if ($isNearStop) {
            return "🚌 {$busNumber} is arriving NOW at {$stopName} for {$studentName}. Please be ready!";
        }

        if ($etaMinutes <= 1) {
            return "🚌 {$busNumber} will arrive at {$stopName} in less than 1 minute for {$studentName}. Please be ready!";
        }

        if ($etaMinutes <= 5) {
            return "🚌 {$busNumber} will arrive at {$stopName} in {$etaMinutes} minutes for {$studentName}. Please be ready!";
        }

        return "🚌 {$busNumber} will arrive at {$stopName} in approximately {$etaMinutes} minutes for {$studentName}.";
    }
}
