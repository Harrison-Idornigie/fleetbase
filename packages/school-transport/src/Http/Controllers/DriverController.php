<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\Driver;
use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\SchoolTransportEngine\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DriverController extends FleetbaseController
{
    /**
     * The namespace for this controller
     *
     * @var string
     */
    public string $namespace = '\Fleetbase\SchoolTransportEngine';

    /**
     * The resource to query
     *
     * @var string
     */
    public $resource = 'driver';

    /**
     * Display a listing of drivers.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by status
                if ($request->filled('status')) {
                    $query->byStatus($request->input('status'));
                }

                // Filter by active status
                if ($request->filled('active')) {
                    $query->where('is_active', $request->boolean('active'));
                }

                // Search by name or driver ID
                if ($request->filled('search')) {
                    $search = $request->input('search');
                    $query->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('driver_id', 'like', "%{$search}%");
                    });
                }

                // Include relationships
                $query->with(['user', 'buses', 'currentBus']);
            },
            // Transform function
            function (&$drivers) {
                return $drivers->map(function ($driver) {
                    return [
                        'id' => $driver->uuid,
                        'public_id' => $driver->public_id,
                        'driver_id' => $driver->driver_id,
                        'first_name' => $driver->first_name,
                        'last_name' => $driver->last_name,
                        'full_name' => $driver->full_name,
                        'phone' => $driver->phone,
                        'email' => $driver->email,
                        'license_number' => $driver->license_number,
                        'license_expiry' => $driver->license_expiry,
                        'license_expired' => $driver->licenseExpired(),
                        'license_class' => $driver->license_class,
                        'date_of_birth' => $driver->date_of_birth,
                        'age' => $driver->age,
                        'address' => $driver->address,
                        'emergency_contact_name' => $driver->emergency_contact_name,
                        'emergency_contact_phone' => $driver->emergency_contact_phone,
                        'years_experience' => $driver->years_experience,
                        'certifications' => $driver->certifications,
                        'current_status' => $driver->current_status,
                        'status_display' => $driver->status_display,
                        'last_location' => $driver->last_location,
                        'last_location_updated_at' => $driver->last_location_updated_at,
                        'is_assigned' => $driver->isAssigned(),
                        'current_bus' => $driver->currentBus,
                        'is_active' => $driver->is_active,
                        'created_at' => $driver->created_at,
                        'updated_at' => $driver->updated_at
                    ];
                });
            }
        );
    }

    /**
     * Store a newly created driver.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'driver_id' => 'required|string|max:50|unique:school_transport_drivers,driver_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:school_transport_drivers,email',
            'license_number' => 'required|string|max:50|unique:school_transport_drivers,license_number',
            'license_expiry' => 'required|date|after:today',
            'license_class' => 'nullable|string|max:10',
            'date_of_birth' => 'required|date|before:today',
            'address' => 'required|string',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:20',
            'years_experience' => 'nullable|integer|min:0|max:50',
            'certifications' => 'nullable|array'
        ]);

        $driver = Driver::create([
            'driver_id' => $request->input('driver_id'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'license_number' => $request->input('license_number'),
            'license_expiry' => $request->input('license_expiry'),
            'license_class' => $request->input('license_class'),
            'date_of_birth' => $request->input('date_of_birth'),
            'address' => $request->input('address'),
            'emergency_contact_name' => $request->input('emergency_contact_name'),
            'emergency_contact_phone' => $request->input('emergency_contact_phone'),
            'years_experience' => $request->input('years_experience'),
            'certifications' => $request->input('certifications', []),
            'company_uuid' => session('company')
        ]);

        return response()->json([
            'driver' => $driver->load(['user', 'buses'])
        ], 201);
    }

    /**
     * Display the specified driver.
     */
    public function show(string $id): JsonResponse
    {
        $driver = Driver::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['user', 'buses.assignments', 'trips' => function ($query) {
                $query->latest()->take(10);
            }])
            ->firstOrFail();

        return response()->json([
            'driver' => [
                'id' => $driver->uuid,
                'public_id' => $driver->public_id,
                'driver_id' => $driver->driver_id,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'full_name' => $driver->full_name,
                'phone' => $driver->phone,
                'email' => $driver->email,
                'license_number' => $driver->license_number,
                'license_expiry' => $driver->license_expiry,
                'license_expired' => $driver->licenseExpired(),
                'license_class' => $driver->license_class,
                'date_of_birth' => $driver->date_of_birth,
                'age' => $driver->age,
                'address' => $driver->address,
                'emergency_contact_name' => $driver->emergency_contact_name,
                'emergency_contact_phone' => $driver->emergency_contact_phone,
                'years_experience' => $driver->years_experience,
                'certifications' => $driver->certifications,
                'current_status' => $driver->current_status,
                'status_display' => $driver->status_display,
                'last_location' => $driver->last_location,
                'last_location_updated_at' => $driver->last_location_updated_at,
                'is_assigned' => $driver->isAssigned(),
                'current_bus' => $driver->currentBus,
                'assigned_buses' => $driver->buses,
                'recent_trips' => $driver->trips,
                'is_active' => $driver->is_active,
                'created_at' => $driver->created_at,
                'updated_at' => $driver->updated_at
            ]
        ]);
    }

    /**
     * Update the specified driver.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $driver = Driver::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'driver_id' => 'sometimes|string|max:50|unique:school_transport_drivers,driver_id,' . $driver->id . ',id',
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255|unique:school_transport_drivers,email,' . $driver->id . ',id',
            'license_number' => 'sometimes|string|max:50|unique:school_transport_drivers,license_number,' . $driver->id . ',id',
            'license_expiry' => 'sometimes|date',
            'license_class' => 'nullable|string|max:10',
            'date_of_birth' => 'sometimes|date|before:today',
            'address' => 'sometimes|string',
            'emergency_contact_name' => 'sometimes|string|max:255',
            'emergency_contact_phone' => 'sometimes|string|max:20',
            'years_experience' => 'nullable|integer|min:0|max:50',
            'certifications' => 'nullable|array',
            'current_status' => 'sometimes|in:available,on_route,off_duty,on_break',
            'is_active' => 'sometimes|boolean'
        ]);

        $driver->update($request->only([
            'driver_id',
            'first_name',
            'last_name',
            'phone',
            'email',
            'license_number',
            'license_expiry',
            'license_class',
            'date_of_birth',
            'address',
            'emergency_contact_name',
            'emergency_contact_phone',
            'years_experience',
            'certifications',
            'current_status',
            'is_active'
        ]));

        return response()->json([
            'driver' => $driver->fresh()->load(['user', 'buses'])
        ]);
    }

    /**
     * Remove the specified driver.
     */
    public function destroy(string $id): JsonResponse
    {
        $driver = Driver::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Check if driver has active assignments
        if ($driver->buses()->exists()) {
            return response()->json([
                'error' => 'Cannot delete driver with active bus assignments'
            ], 422);
        }

        $driver->delete();

        return response()->json([
            'message' => 'Driver deleted successfully'
        ]);
    }

    /**
     * Update driver location.
     */
    public function updateLocation(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_name' => 'nullable|string|max:255'
        ]);

        $driver = Driver::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $driver->update([
            'last_location' => [
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'location_name' => $request->input('location_name')
            ],
            'last_location_updated_at' => now()
        ]);

        return response()->json([
            'driver' => $driver->fresh(),
            'message' => 'Location updated successfully'
        ]);
    }

    /**
     * Update driver status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'status' => 'required|in:available,on_route,off_duty,on_break'
        ]);

        $driver = Driver::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $driver->update([
            'current_status' => $request->input('status')
        ]);

        return response()->json([
            'driver' => $driver->fresh(),
            'message' => 'Status updated successfully'
        ]);
    }

    /**
     * Assign bus to driver.
     */
    public function assignBus(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'bus_uuid' => 'required|exists:school_transport_buses,uuid'
        ]);

        $driver = Driver::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $bus = Bus::where('uuid', $request->input('bus_uuid'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Check if bus is already assigned to another driver
        if ($bus->driver_uuid && $bus->driver_uuid !== $driver->uuid) {
            return response()->json([
                'error' => 'Bus is already assigned to another driver'
            ], 422);
        }

        $bus->update([
            'driver_uuid' => $driver->uuid,
            'status' => 'available'
        ]);

        return response()->json([
            'driver' => $driver->fresh()->load('buses'),
            'bus' => $bus->fresh(),
            'message' => 'Bus assigned successfully'
        ]);
    }

    /**
     * Unassign bus from driver.
     */
    public function unassignBus(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'bus_uuid' => 'required|exists:school_transport_buses,uuid'
        ]);

        $driver = Driver::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $bus = Bus::where('uuid', $request->input('bus_uuid'))
            ->where('driver_uuid', $driver->uuid)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $bus->update([
            'driver_uuid' => null,
            'status' => 'available'
        ]);

        return response()->json([
            'driver' => $driver->fresh()->load('buses'),
            'message' => 'Bus unassigned successfully'
        ]);
    }

    /**
     * Get driver performance statistics.
     */
    public function performance(string $id): JsonResponse
    {
        $driver = Driver::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $stats = [
            'total_trips' => $driver->trips()->count(),
            'completed_trips' => $driver->trips()->byStatus('completed')->count(),
            'delayed_trips' => $driver->trips()->delayed()->count(),
            'average_trip_duration' => $driver->trips()->completed()->avg('actual_duration_minutes'),
            'total_distance' => $driver->trips()->completed()->sum('distance_km'),
            'average_rating' => 4.5, // This would come from a rating system
            'safety_incidents' => 0, // This would come from incident tracking
            'recent_trips' => $driver->trips()->latest()->take(5)->get()
        ];

        return response()->json($stats);
    }

    /**
     * Get dashboard statistics for drivers.
     */
    public function dashboardStats(): JsonResponse
    {
        $companyUuid = session('company');

        $stats = [
            'total_drivers' => Driver::where('company_uuid', $companyUuid)->count(),
            'active_drivers' => Driver::where('company_uuid', $companyUuid)->active()->count(),
            'available_drivers' => Driver::where('company_uuid', $companyUuid)->available()->count(),
            'drivers_on_route' => Driver::where('company_uuid', $companyUuid)->byStatus('on_route')->count(),
            'drivers_with_expired_licenses' => Driver::where('company_uuid', $companyUuid)->get()->filter(function ($driver) {
                return $driver->licenseExpired();
            })->count(),
            'drivers_by_status' => Driver::where('company_uuid', $companyUuid)
                ->groupBy('current_status')
                ->selectRaw('current_status, count(*) as count')
                ->get(),
            'average_experience_years' => Driver::where('company_uuid', $companyUuid)->avg('years_experience')
        ];

        return response()->json($stats);
    }
}
