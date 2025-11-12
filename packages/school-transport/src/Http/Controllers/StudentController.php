<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\BusAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StudentController extends FleetbaseController
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
    public $resource = 'student';

    /**
     * Display a listing of students.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by school
                if ($request->filled('school')) {
                    $query->forSchool($request->input('school'));
                }

                // Filter by grade
                if ($request->filled('grade')) {
                    $query->forGrade($request->input('grade'));
                }

                // Filter by active status
                if ($request->filled('active')) {
                    $query->where('is_active', $request->boolean('active'));
                }

                // Filter students with special needs
                if ($request->boolean('special_needs')) {
                    $query->withSpecialNeeds();
                }

                // Include relationships
                $query->with(['busAssignments.route', 'attendanceRecords']);
            },
            // Transform function
            function (&$students) {
                return $students->map(function ($student) {
                    return [
                        'id' => $student->uuid,
                        'public_id' => $student->public_id,
                        'student_id' => $student->student_id,
                        'full_name' => $student->full_name,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'grade' => $student->grade,
                        'school' => $student->school,
                        'age' => $student->age,
                        'parent_name' => $student->parent_name,
                        'parent_phone' => $student->parent_phone,
                        'parent_email' => $student->parent_email,
                        'has_special_needs' => $student->has_special_needs,
                        'special_needs' => $student->special_needs,
                        'is_active' => $student->is_active,
                        'active_assignments' => $student->active_assignments,
                        'current_route' => $student->getCurrentRoute(),
                        'created_at' => $student->created_at,
                        'updated_at' => $student->updated_at
                    ];
                });
            }
        );
    }

    /**
     * Store a newly created student.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'student_id' => 'required|string|unique:school_transport_students,student_id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'grade' => 'required|string',
            'school' => 'required|string',
            'home_address' => 'required|string',
            'parent_name' => 'required|string|max:255',
            'parent_phone' => 'required|string',
            'parent_email' => 'nullable|email',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'special_needs' => 'nullable|array',
            'medical_info' => 'nullable|array',
            'emergency_contact_name' => 'nullable|string',
            'emergency_contact_phone' => 'nullable|string'
        ]);

        $student = Student::create([
            'student_id' => $request->input('student_id'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'grade' => $request->input('grade'),
            'school' => $request->input('school'),
            'date_of_birth' => $request->input('date_of_birth'),
            'gender' => $request->input('gender'),
            'home_address' => $request->input('home_address'),
            'parent_name' => $request->input('parent_name'),
            'parent_phone' => $request->input('parent_phone'),
            'parent_email' => $request->input('parent_email'),
            'emergency_contact_name' => $request->input('emergency_contact_name'),
            'emergency_contact_phone' => $request->input('emergency_contact_phone'),
            'special_needs' => $request->input('special_needs'),
            'medical_info' => $request->input('medical_info'),
            'pickup_location' => $request->input('pickup_location'),
            'dropoff_location' => $request->input('dropoff_location'),
            'company_uuid' => session('company')
        ]);

        return response()->json([
            'student' => $student->load('busAssignments')
        ], 201);
    }

    /**
     * Display the specified student.
     */
    public function show(string $id): JsonResponse
    {
        $student = Student::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['busAssignments.route', 'attendanceRecords', 'communications'])
            ->firstOrFail();

        return response()->json([
            'student' => [
                'id' => $student->uuid,
                'public_id' => $student->public_id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'grade' => $student->grade,
                'school' => $student->school,
                'date_of_birth' => $student->date_of_birth,
                'age' => $student->age,
                'gender' => $student->gender,
                'home_address' => $student->home_address,
                'pickup_location' => $student->pickup_location,
                'dropoff_location' => $student->dropoff_location,
                'parent_name' => $student->parent_name,
                'parent_phone' => $student->parent_phone,
                'parent_email' => $student->parent_email,
                'emergency_contact_name' => $student->emergency_contact_name,
                'emergency_contact_phone' => $student->emergency_contact_phone,
                'has_special_needs' => $student->has_special_needs,
                'special_needs' => $student->special_needs,
                'medical_info' => $student->medical_info,
                'is_active' => $student->is_active,
                'photo_url' => $student->photo_url,
                'bus_assignments' => $student->busAssignments,
                'attendance_records' => $student->attendanceRecords->take(30), // Last 30 records
                'created_at' => $student->created_at,
                'updated_at' => $student->updated_at
            ]
        ]);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $student = Student::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'student_id' => 'sometimes|string|unique:school_transport_students,student_id,' . $student->id . ',id',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'grade' => 'sometimes|string',
            'school' => 'sometimes|string',
            'home_address' => 'sometimes|string',
            'parent_name' => 'sometimes|string|max:255',
            'parent_phone' => 'sometimes|string',
            'parent_email' => 'nullable|email',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'special_needs' => 'nullable|array',
            'medical_info' => 'nullable|array'
        ]);

        $student->update($request->only([
            'student_id',
            'first_name',
            'last_name',
            'grade',
            'school',
            'date_of_birth',
            'gender',
            'home_address',
            'parent_name',
            'parent_phone',
            'parent_email',
            'emergency_contact_name',
            'emergency_contact_phone',
            'special_needs',
            'medical_info',
            'pickup_location',
            'dropoff_location',
            'is_active'
        ]));

        return response()->json([
            'student' => $student->fresh()->load('busAssignments')
        ]);
    }

    /**
     * Remove the specified student.
     */
    public function destroy(string $id): JsonResponse
    {
        $student = Student::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Deactivate instead of deleting if there are active assignments
        if ($student->activeBusAssignments()->exists()) {
            $student->update(['is_active' => false]);

            // Deactivate assignments
            $student->activeBusAssignments()->each(function ($assignment) {
                $assignment->deactivate();
            });

            return response()->json([
                'message' => 'Student deactivated successfully (had active bus assignments)'
            ]);
        }

        $student->delete();

        return response()->json([
            'message' => 'Student deleted successfully'
        ]);
    }

    /**
     * Bulk import students.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $this->validate($request, [
            'students' => 'required|array|max:1000',
            'students.*.student_id' => 'required|string',
            'students.*.first_name' => 'required|string',
            'students.*.last_name' => 'required|string',
            'students.*.grade' => 'required|string',
            'students.*.school' => 'required|string',
            'students.*.home_address' => 'required|string',
            'students.*.parent_name' => 'required|string',
            'students.*.parent_phone' => 'required|string'
        ]);

        $imported = [];
        $errors = [];

        DB::transaction(function () use ($request, &$imported, &$errors) {
            foreach ($request->input('students') as $index => $studentData) {
                try {
                    // Check for duplicate student_id
                    if (Student::where('student_id', $studentData['student_id'])->exists()) {
                        $errors[] = [
                            'row' => $index + 1,
                            'student_id' => $studentData['student_id'],
                            'error' => 'Student ID already exists'
                        ];
                        continue;
                    }

                    $student = Student::create(array_merge($studentData, [
                        'company_uuid' => session('company')
                    ]));

                    $imported[] = $student;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'student_id' => $studentData['student_id'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        return response()->json([
            'imported_count' => count($imported),
            'error_count' => count($errors),
            'errors' => $errors,
            'students' => $imported
        ]);
    }

    /**
     * Get student assignments.
     */
    public function assignments(string $id): JsonResponse
    {
        $student = Student::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $assignments = $student->busAssignments()
            ->with(['route', 'attendanceRecords'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'assignments' => $assignments
        ]);
    }

    /**
     * Get dashboard statistics.
     */
    public function dashboardStats(): JsonResponse
    {
        $companyUuid = session('company');

        $stats = [
            'total_students' => Student::where('company_uuid', $companyUuid)->count(),
            'active_students' => Student::where('company_uuid', $companyUuid)->active()->count(),
            'students_with_special_needs' => Student::where('company_uuid', $companyUuid)->withSpecialNeeds()->count(),
            'students_by_school' => Student::where('company_uuid', $companyUuid)
                ->active()
                ->groupBy('school')
                ->selectRaw('school, count(*) as count')
                ->get(),
            'students_by_grade' => Student::where('company_uuid', $companyUuid)
                ->active()
                ->groupBy('grade')
                ->selectRaw('grade, count(*) as count')
                ->orderBy('grade')
                ->get(),
            'recent_additions' => Student::where('company_uuid', $companyUuid)
                ->whereBetween('created_at', [now()->subDays(30), now()])
                ->count()
        ];

        return response()->json($stats);
    }
}
