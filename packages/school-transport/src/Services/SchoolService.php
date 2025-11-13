<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\School;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SchoolService
{
    /**
     * Get all schools with optional filtering
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSchools(array $filters = [])
    {
        $query = School::where('company_uuid', session('company'));

        if (isset($filters['school_type'])) {
            $query->where('school_type', $filters['school_type']);
        }

        if (isset($filters['active'])) {
            $query->where('is_active', $filters['active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        return $query->with(['routes', 'students'])->get();
    }

    /**
     * Create a new school
     *
     * @param array $data
     * @return School
     */
    public function createSchool(array $data): School
    {
        DB::beginTransaction();
        try {
            $data['company_uuid'] = $data['company_uuid'] ?? session('company');
            $data['created_by_uuid'] = $data['created_by_uuid'] ?? auth()->id();

            $school = School::create($data);

            DB::commit();
            return $school;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create school: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing school
     *
     * @param string $uuid
     * @param array $data
     * @return School
     */
    public function updateSchool(string $uuid, array $data): School
    {
        DB::beginTransaction();
        try {
            $school = School::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            $data['updated_by_uuid'] = $data['updated_by_uuid'] ?? auth()->id();
            $school->update($data);

            DB::commit();
            return $school->fresh(['routes', 'students']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update school: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a school
     *
     * @param string $uuid
     * @return bool
     */
    public function deleteSchool(string $uuid): bool
    {
        DB::beginTransaction();
        try {
            $school = School::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            // Check if school has active students or routes
            if ($school->students()->where('is_active', true)->exists()) {
                throw new \Exception('Cannot delete school with active students');
            }

            if ($school->routes()->where('is_active', true)->exists()) {
                throw new \Exception('Cannot delete school with active routes');
            }

            $school->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete school: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get school details with related data
     *
     * @param string $uuid
     * @return School
     */
    public function getSchoolDetails(string $uuid): School
    {
        return School::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->with([
                'students' => function ($query) {
                    $query->where('is_active', true);
                },
                'routes' => function ($query) {
                    $query->where('is_active', true)->with(['bus', 'driver']);
                },
            ])
            ->firstOrFail();
    }

    /**
     * Get school statistics
     *
     * @param string $uuid
     * @return array
     */
    public function getSchoolStatistics(string $uuid): array
    {
        $school = School::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $students = $school->students;
        $routes = $school->routes;

        return [
            'school' => [
                'uuid' => $school->uuid,
                'name' => $school->name,
                'code' => $school->code,
            ],
            'total_students' => $students->count(),
            'active_students' => $students->where('is_active', true)->count(),
            'students_by_grade' => $students->groupBy('grade')->map->count(),
            'special_needs_students' => $students->where('special_needs', true)->count(),
            'total_routes' => $routes->count(),
            'active_routes' => $routes->where('is_active', true)->count(),
            'routes_by_type' => $routes->groupBy('route_type')->map->count(),
            'total_bus_capacity' => $routes->sum('capacity'),
        ];
    }

    /**
     * Get schools by service area
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSchoolsByArea(float $latitude, float $longitude, float $radiusKm = 50)
    {
        // This would use spatial queries in a production implementation
        // Simplified version for now
        return School::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->get()
            ->filter(function ($school) use ($latitude, $longitude, $radiusKm) {
                // Calculate distance and filter
                // In production, use MySQL spatial queries
                return true;
            });
    }

    /**
     * Get school enrollment capacity
     *
     * @param string $uuid
     * @return array
     */
    public function getEnrollmentCapacity(string $uuid): array
    {
        $school = School::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->with(['students', 'routes'])
            ->firstOrFail();

        $totalCapacity = $school->routes->sum('capacity');
        $currentEnrollment = $school->students()->where('is_active', true)->count();

        return [
            'school' => [
                'uuid' => $school->uuid,
                'name' => $school->name,
            ],
            'total_capacity' => $totalCapacity,
            'current_enrollment' => $currentEnrollment,
            'available_capacity' => max(0, $totalCapacity - $currentEnrollment),
            'utilization_percentage' => $totalCapacity > 0
                ? round(($currentEnrollment / $totalCapacity) * 100, 2)
                : 0,
            'over_capacity' => $currentEnrollment > $totalCapacity,
        ];
    }

    /**
     * Bulk import schools
     *
     * @param array $schools
     * @return array
     */
    public function bulkImportSchools(array $schools): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($schools as $index => $schoolData) {
            try {
                $this->createSchool($schoolData);
                $imported++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $index + 1,
                    'data' => $schoolData,
                    'error' => $e->getMessage(),
                ];
                Log::error("Failed to import school at row {$index}: " . $e->getMessage());
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
