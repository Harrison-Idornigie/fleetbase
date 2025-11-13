<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\SchoolTransportEngine\Models\Trip;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\FleetOps\Models\FuelReport;
use Fleetbase\FleetOps\Models\Issue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BusService
{
    /**
     * Get all buses with optional filtering
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBuses(array $filters = [])
    {
        $query = Bus::where('company_uuid', session('company'))
            ->whereNotNull('bus_number'); // Filter for school buses

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['active'])) {
            $query->where('is_active', $filters['active']);
        }

        if (isset($filters['route'])) {
            $query->where('route_uuid', $filters['route']);
        }

        if (isset($filters['capacity_min'])) {
            $query->where('capacity', '>=', $filters['capacity_min']);
        }

        if (isset($filters['wheelchair_accessible'])) {
            $query->where('wheelchair_accessible', $filters['wheelchair_accessible']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('bus_number', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        return $query->with(['driver', 'route', 'maintenances', 'fuelReports'])->get();
    }

    /**
     * Create a new bus (extending FleetOps Vehicle)
     *
     * @param array $data
     * @return Bus
     */
    public function createBus(array $data): Bus
    {
        DB::beginTransaction();
        try {
            $data['company_uuid'] = $data['company_uuid'] ?? session('company');
            $data['type'] = 'bus'; // Ensure vehicle type is set to bus

            // Create the bus (which extends Vehicle)
            $bus = Bus::create($data);

            // Initialize FleetOps integrations if needed
            if (isset($data['warranty_info'])) {
                // Handle warranty creation via FleetOps
                $this->attachWarranty($bus, $data['warranty_info']);
            }

            DB::commit();
            return $bus->load(['driver', 'route', 'warranty']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create bus: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing bus
     *
     * @param string $uuid
     * @param array $data
     * @return Bus
     */
    public function updateBus(string $uuid, array $data): Bus
    {
        DB::beginTransaction();
        try {
            $bus = Bus::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            $bus->update($data);

            // Update warranty if provided
            if (isset($data['warranty_info'])) {
                $this->updateWarranty($bus, $data['warranty_info']);
            }

            DB::commit();
            return $bus->fresh(['driver', 'route', 'warranty', 'maintenances']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update bus: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a bus
     *
     * @param string $uuid
     * @return bool
     */
    public function deleteBus(string $uuid): bool
    {
        DB::beginTransaction();
        try {
            $bus = Bus::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            // Check if bus has active assignments
            if ($bus->trips()->where('status', 'active')->exists()) {
                throw new \Exception('Cannot delete bus with active trips');
            }

            $bus->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete bus: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get bus details with FleetOps data
     *
     * @param string $uuid
     * @return Bus
     */
    public function getBusDetails(string $uuid): Bus
    {
        return Bus::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->with([
                'driver',
                'route',
                'trips' => function ($query) {
                    $query->latest()->limit(10);
                },
                'maintenances' => function ($query) {
                    $query->latest()->limit(10);
                },
                'fuelReports' => function ($query) {
                    $query->latest()->limit(10);
                },
                'issues' => function ($query) {
                    $query->where('status', '!=', 'resolved')->latest();
                },
                'warranty',
            ])
            ->firstOrFail();
    }

    /**
     * Get bus maintenance schedule from FleetOps
     *
     * @param string $uuid
     * @return array
     */
    public function getMaintenanceSchedule(string $uuid): array
    {
        $bus = Bus::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $maintenances = $bus->maintenances()
            ->orderBy('scheduled_at', 'desc')
            ->get();

        $upcoming = $maintenances->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now());

        $overdue = $maintenances->where('status', 'scheduled')
            ->where('scheduled_at', '<', now());

        return [
            'bus' => [
                'uuid' => $bus->uuid,
                'bus_number' => $bus->bus_number,
                'make' => $bus->make,
                'model' => $bus->model,
            ],
            'upcoming' => $upcoming->values(),
            'overdue' => $overdue->values(),
            'completed' => $maintenances->where('status', 'completed')->take(10)->values(),
            'statistics' => [
                'total_maintenances' => $maintenances->count(),
                'upcoming_count' => $upcoming->count(),
                'overdue_count' => $overdue->count(),
                'last_maintenance' => $maintenances->where('status', 'completed')->first()?->completed_at,
            ],
        ];
    }

    /**
     * Get bus fuel reports from FleetOps
     *
     * @param string $uuid
     * @param array $filters
     * @return array
     */
    public function getFuelReports(string $uuid, array $filters = []): array
    {
        $bus = Bus::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $query = FuelReport::where('vehicle_uuid', $bus->uuid)
            ->where('company_uuid', session('company'));

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }

        $reports = $query->orderBy('created_at', 'desc')->get();

        return [
            'bus' => [
                'uuid' => $bus->uuid,
                'bus_number' => $bus->bus_number,
            ],
            'reports' => $reports,
            'summary' => [
                'total_reports' => $reports->count(),
                'total_volume' => $reports->sum('volume'),
                'total_cost' => $reports->sum('amount'),
                'average_cost_per_fill' => $reports->count() > 0 ? $reports->avg('amount') : 0,
                'average_volume_per_fill' => $reports->count() > 0 ? $reports->avg('volume') : 0,
            ],
        ];
    }

    /**
     * Report a bus issue (FleetOps Issue)
     *
     * @param string $uuid
     * @param array $issueData
     * @return Issue
     */
    public function reportIssue(string $uuid, array $issueData): Issue
    {
        $bus = Bus::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $issueData['vehicle_uuid'] = $bus->uuid;
        $issueData['company_uuid'] = session('company');
        $issueData['reported_by_uuid'] = $issueData['reported_by_uuid'] ?? auth()->id();
        $issueData['status'] = $issueData['status'] ?? 'reported';

        return Issue::create($issueData);
    }

    /**
     * Get bus issues from FleetOps
     *
     * @param string $uuid
     * @param bool $activeOnly
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getIssues(string $uuid, bool $activeOnly = true)
    {
        $bus = Bus::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $query = Issue::where('vehicle_uuid', $bus->uuid)
            ->where('company_uuid', session('company'));

        if ($activeOnly) {
            $query->where('status', '!=', 'resolved');
        }

        return $query->latest()->get();
    }

    /**
     * Attach warranty to bus
     *
     * @param Bus $bus
     * @param array $warrantyData
     * @return void
     */
    protected function attachWarranty(Bus $bus, array $warrantyData): void
    {
        // Implementation would integrate with FleetOps warranty system
        // For now, just store in meta
        $bus->update([
            'meta' => array_merge($bus->meta ?? [], [
                'warranty' => $warrantyData,
            ]),
        ]);
    }

    /**
     * Update warranty information
     *
     * @param Bus $bus
     * @param array $warrantyData
     * @return void
     */
    protected function updateWarranty(Bus $bus, array $warrantyData): void
    {
        $meta = $bus->meta ?? [];
        $meta['warranty'] = $warrantyData;
        $bus->update(['meta' => $meta]);
    }

    /**
     * Get available buses (not assigned to active trips)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableBuses()
    {
        return Bus::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereDoesntHave('trips', function ($query) {
                $query->where('status', 'in_progress');
            })
            ->get();
    }

    /**
     * Assign bus to route
     *
     * @param string $busUuid
     * @param string $routeUuid
     * @return Bus
     */
    public function assignToRoute(string $busUuid, string $routeUuid): Bus
    {
        $bus = Bus::where('company_uuid', session('company'))
            ->where('uuid', $busUuid)
            ->firstOrFail();

        $bus->update(['route_uuid' => $routeUuid]);

        return $bus->fresh(['route']);
    }
}
