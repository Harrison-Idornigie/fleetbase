<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\BusAssignment;
use Fleetbase\SchoolTransportEngine\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentService
{
    /**
     * Get all students with optional filtering
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStudents(array $filters = [])
    {
        $query = Student::where('company_uuid', session('company'));

        if (isset($filters['school'])) {
            $query->where('school_uuid', $filters['school']);
        }

        if (isset($filters['grade'])) {
            $query->where('grade', $filters['grade']);
        }

        if (isset($filters['active'])) {
            $query->where('is_active', $filters['active']);
        }

        if (isset($filters['special_needs'])) {
            $query->where('special_needs', true);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        return $query->with(['school', 'assignments', 'guardians'])->get();
    }

    /**
     * Create a new student
     *
     * @param array $data
     * @return Student
     */
    public function createStudent(array $data): Student
    {
        DB::beginTransaction();
        try {
            $data['company_uuid'] = $data['company_uuid'] ?? session('company');
            $data['created_by_uuid'] = $data['created_by_uuid'] ?? auth()->id();

            $student = Student::create($data);

            // If guardians data is provided, attach them
            if (isset($data['guardians']) && is_array($data['guardians'])) {
                foreach ($data['guardians'] as $guardianData) {
                    $student->guardians()->attach($guardianData['uuid'] ?? $guardianData, [
                        'relationship' => $guardianData['relationship'] ?? 'parent',
                        'is_primary' => $guardianData['is_primary'] ?? false,
                    ]);
                }
            }

            DB::commit();
            return $student->load(['school', 'guardians']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create student: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing student
     *
     * @param string $uuid
     * @param array $data
     * @return Student
     */
    public function updateStudent(string $uuid, array $data): Student
    {
        DB::beginTransaction();
        try {
            $student = Student::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            $data['updated_by_uuid'] = $data['updated_by_uuid'] ?? auth()->id();
            $student->update($data);

            // Update guardians if provided
            if (isset($data['guardians']) && is_array($data['guardians'])) {
                $student->guardians()->detach();
                foreach ($data['guardians'] as $guardianData) {
                    $student->guardians()->attach($guardianData['uuid'] ?? $guardianData, [
                        'relationship' => $guardianData['relationship'] ?? 'parent',
                        'is_primary' => $guardianData['is_primary'] ?? false,
                    ]);
                }
            }

            DB::commit();
            return $student->fresh(['school', 'guardians']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update student: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a student
     *
     * @param string $uuid
     * @return bool
     */
    public function deleteStudent(string $uuid): bool
    {
        DB::beginTransaction();
        try {
            $student = Student::where('company_uuid', session('company'))
                ->where('uuid', $uuid)
                ->firstOrFail();

            // Check if student has active assignments
            if ($student->assignments()->where('status', 'active')->exists()) {
                throw new \Exception('Cannot delete student with active bus assignments');
            }

            $student->guardians()->detach();
            $student->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete student: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get student profile with complete information
     *
     * @param string $uuid
     * @return Student
     */
    public function getStudentProfile(string $uuid): Student
    {
        return Student::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->with([
                'school',
                'guardians',
                'assignments.route',
                'assignments.bus',
                'attendance' => function ($query) {
                    $query->latest()->limit(30);
                }
            ])
            ->firstOrFail();
    }

    /**
     * Get student attendance statistics
     *
     * @param string $uuid
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getStudentAttendanceStats(string $uuid, ?string $startDate = null, ?string $endDate = null): array
    {
        $student = Student::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $query = Attendance::where('student_uuid', $student->uuid);

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } else {
            $query->where('date', '>=', now()->subDays(30));
        }

        $attendance = $query->get();

        return [
            'student' => [
                'uuid' => $student->uuid,
                'name' => $student->full_name,
                'student_id' => $student->student_id,
            ],
            'total_days' => $attendance->count(),
            'present_days' => $attendance->where('present', true)->count(),
            'absent_days' => $attendance->where('present', false)->count(),
            'pickups' => $attendance->where('event_type', 'pickup')->count(),
            'dropoffs' => $attendance->where('event_type', 'dropoff')->count(),
            'no_shows' => $attendance->where('event_type', 'no_show')->count(),
            'attendance_rate' => $attendance->count() > 0
                ? round(($attendance->where('present', true)->count() / $attendance->count()) * 100, 2)
                : 0,
            'period' => [
                'start' => $startDate ?? now()->subDays(30)->toDateString(),
                'end' => $endDate ?? now()->toDateString(),
            ],
        ];
    }

    /**
     * Bulk import students
     *
     * @param array $students
     * @return array
     */
    public function bulkImportStudents(array $students): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($students as $index => $studentData) {
            try {
                $this->createStudent($studentData);
                $imported++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $index + 1,
                    'data' => $studentData,
                    'error' => $e->getMessage(),
                ];
                Log::error("Failed to import student at row {$index}: " . $e->getMessage());
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Assign student to a route
     *
     * @param string $studentUuid
     * @param string $routeUuid
     * @param array $additionalData
     * @return BusAssignment
     */
    public function assignToRoute(string $studentUuid, string $routeUuid, array $additionalData = []): BusAssignment
    {
        $student = Student::where('company_uuid', session('company'))
            ->where('uuid', $studentUuid)
            ->firstOrFail();

        $assignmentData = array_merge([
            'company_uuid' => session('company'),
            'student_uuid' => $studentUuid,
            'route_uuid' => $routeUuid,
            'status' => 'active',
            'created_by_uuid' => auth()->id(),
        ], $additionalData);

        return BusAssignment::create($assignmentData);
    }
}
