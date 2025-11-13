<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Driver;
use Fleetbase\SchoolTransportEngine\Models\Trip;
use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\FleetOps\Models\Driver as FleetOpsDriver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriverService
{
    /**
     * Get all drivers with optional filtering
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDrivers(array $filters = [])
    {
        $query = Driver::where('company_uuid', session('company'));

        if (isset($filters['status'])) {
            $query->where('current_status', $filters['status']);
        }

        if (isset($filters['active'])) {
            $query->where('is_active', $filters['active']);
        }

        if (isset($filters['available'])) {
            $query->where('current_status', 'available');
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('driver_id', 'like', "%{$search}%")
                    ->orWhere('license_number', 'like', "%{$search}%");
            });
        }

        if (isset($filters['license_expiring'])) {
            $daysThreshold = $filters['license_expiring'];
            $query->where('license_expiry', '<=', now()->addDays($daysThreshold))
                ->where('license_expiry', '>=', now());
        }

        return $query->with(['user', 'currentBus', 'trips'])->get();
    }

    /**
     * Create a new driver
     *
     * @param array $data
     * @return Driver
     */
    public function createDriver(array $data): Driver
    {
        DB::beginTransaction();
        try {
            $data['company_uuid'] = $data['company_uuid'] ?? session('company');
            $data['created_by_uuid'] = $data['created_by_uuid'] ?? auth()->id();

            $driver = Driver::create($data);

            // Create corresponding FleetOps driver record if integration is enabled
            if (config('school-transport.fleetops_integration', true)) {
                $this->syncWithFleetOpsDriver($driver);
            }

            DB::commit();
            return $driver->load(['user', 'currentBus']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create driver: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing driver
     *
     * @param string $uuid
     * @param array $data
     * @return Driver
     */
    public function updateDriver(string $uuid, array $data): Driver
    {
        DB::beginTransaction();
        try {
            $driver = Driver::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            $data['updated_by_uuid'] = $data['updated_by_uuid'] ?? auth()->id();
            $driver->update($data);

            // Sync with FleetOps driver if integration is enabled
            if (config('school-transport.fleetops_integration', true)) {
                $this->syncWithFleetOpsDriver($driver);
            }

            DB::commit();
            return $driver->fresh(['user', 'currentBus', 'trips']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update driver: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a driver
     *
     * @param string $uuid
     * @return bool
     */
    public function deleteDriver(string $uuid): bool
    {
        DB::beginTransaction();
        try {
            $driver = Driver::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            // Check if driver has active trips
            if ($driver->trips()->whereIn('status', ['scheduled', 'in_progress'])->exists()) {
                throw new \Exception('Cannot delete driver with active trips');
            }

            $driver->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete driver: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get driver profile with complete information
     *
     * @param string $uuid
     * @return Driver
     */
    public function getDriverProfile(string $uuid): Driver
    {
        return Driver::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->with([
                'user',
                'currentBus.route',
                'trips' => function ($query) {
                    $query->latest()->limit(20);
                },
                'buses',
            ])
            ->firstOrFail();
    }

    /**
     * Get driver statistics
     *
     * @param string $uuid
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getDriverStats(string $uuid, ?string $startDate = null, ?string $endDate = null): array
    {
        $driver = Driver::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $query = Trip::where('driver_uuid', $driver->uuid);

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } else {
            $query->where('date', '>=', now()->subDays(30));
        }

        $trips = $query->get();

        return [
            'driver' => [
                'uuid' => $driver->uuid,
                'name' => $driver->full_name,
                'driver_id' => $driver->driver_id,
            ],
            'total_trips' => $trips->count(),
            'completed_trips' => $trips->where('status', 'completed')->count(),
            'cancelled_trips' => $trips->where('status', 'cancelled')->count(),
            'in_progress_trips' => $trips->where('status', 'in_progress')->count(),
            'total_distance' => round($trips->sum('distance'), 2),
            'total_duration' => round($trips->sum('duration'), 2),
            'on_time_percentage' => $this->calculateOnTimePercentage($trips),
            'period' => [
                'start' => $startDate ?? now()->subDays(30)->toDateString(),
                'end' => $endDate ?? now()->toDateString(),
            ],
        ];
    }

    /**
     * Assign driver to a bus
     *
     * @param string $driverUuid
     * @param string $busUuid
     * @return Driver
     */
    public function assignToBus(string $driverUuid, string $busUuid): Driver
    {
        DB::beginTransaction();
        try {
            $driver = Driver::where('company_uuid', session('company'))
                ->where('uuid', $driverUuid)
                ->firstOrFail();

            $bus = Bus::where('company_uuid', session('company'))
                ->where('uuid', $busUuid)
                ->firstOrFail();

            // Unassign driver from current bus if any
            if ($driver->currentBus) {
                $driver->currentBus->update(['driver_uuid' => null]);
            }

            // Assign to new bus
            $bus->update(['driver_uuid' => $driver->uuid]);
            $driver->update(['current_bus_uuid' => $bus->uuid]);

            DB::commit();
            return $driver->fresh(['currentBus']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign driver to bus: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update driver status
     *
     * @param string $uuid
     * @param string $status
     * @return Driver
     */
    public function updateStatus(string $uuid, string $status): Driver
    {
        $driver = Driver::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $driver->update(['current_status' => $status]);

        return $driver;
    }

    /**
     * Get drivers with expiring licenses
     *
     * @param int $daysThreshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDriversWithExpiringLicenses(int $daysThreshold = 30)
    {
        return Driver::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->where('license_expiry', '<=', now()->addDays($daysThreshold))
            ->where('license_expiry', '>=', now())
            ->orderBy('license_expiry')
            ->get();
    }

    /**
     * Get available drivers (not assigned to active trips)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableDrivers()
    {
        return Driver::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->where('current_status', 'available')
            ->whereDoesntHave('trips', function ($query) {
                $query->where('status', 'in_progress');
            })
            ->get();
    }

    /**
     * Sync driver with FleetOps driver record
     *
     * @param Driver $driver
     * @return void
     */
    protected function syncWithFleetOpsDriver(Driver $driver): void
    {
        try {
            // Check if FleetOps driver exists
            $fleetOpsDriver = FleetOpsDriver::where('company_uuid', $driver->company_uuid)
                ->where('user_uuid', $driver->user_uuid)
                ->first();

            $driverData = [
                'company_uuid' => $driver->company_uuid,
                'user_uuid' => $driver->user_uuid,
                'name' => $driver->full_name,
                'phone' => $driver->phone,
                'drivers_license_number' => $driver->license_number,
                'vehicle_uuid' => $driver->current_bus_uuid,
                'status' => $driver->is_active ? 'active' : 'inactive',
            ];

            if ($fleetOpsDriver) {
                $fleetOpsDriver->update($driverData);
            } else {
                FleetOpsDriver::create($driverData);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync with FleetOps driver: ' . $e->getMessage());
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
