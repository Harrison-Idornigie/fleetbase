<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\School;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SchoolController extends FleetbaseController
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
    public $resource = 'school';

    /**
     * Display a listing of schools.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by school type
                if ($request->filled('school_type')) {
                    $query->where('school_type', $request->input('school_type'));
                }

                // Filter by active status
                if ($request->filled('active')) {
                    $query->where('is_active', $request->boolean('active'));
                }

                // Search by name or code
                if ($request->filled('search')) {
                    $search = $request->input('search');
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
                }

                // Include relationships
                $query->with(['students', 'routes']);
            },
            // Transform function
            function (&$schools) {
                return $schools->map(function ($school) {
                    return [
                        'id' => $school->uuid,
                        'public_id' => $school->public_id,
                        'name' => $school->name,
                        'code' => $school->code,
                        'address' => $school->address,
                        'city' => $school->city,
                        'state' => $school->state,
                        'zip_code' => $school->zip_code,
                        'country' => $school->country,
                        'phone' => $school->phone,
                        'email' => $school->email,
                        'principal_name' => $school->principal_name,
                        'school_type' => $school->school_type,
                        'school_type_display' => $school->school_type_display,
                        'grade_levels' => $school->grade_levels,
                        'total_students' => $school->total_students,
                        'operating_hours' => $school->operating_hours,
                        'timezone' => $school->timezone,
                        'is_active' => $school->is_active,
                        'full_address' => $school->full_address,
                        'student_count' => $school->students->count(),
                        'route_count' => $school->routes->count(),
                        'created_at' => $school->created_at,
                        'updated_at' => $school->updated_at
                    ];
                });
            }
        );
    }

    /**
     * Store a newly created school.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:school_transport_schools,code',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'principal_name' => 'nullable|string|max:255',
            'school_type' => 'required|in:elementary,middle,high,k12',
            'grade_levels' => 'nullable|array',
            'total_students' => 'nullable|integer|min:0',
            'operating_hours' => 'nullable|array',
            'timezone' => 'nullable|string|max:50'
        ]);

        $school = School::create([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'zip_code' => $request->input('zip_code'),
            'country' => $request->input('country', 'USA'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'principal_name' => $request->input('principal_name'),
            'school_type' => $request->input('school_type'),
            'grade_levels' => $request->input('grade_levels'),
            'total_students' => $request->input('total_students'),
            'operating_hours' => $request->input('operating_hours'),
            'timezone' => $request->input('timezone', 'America/New_York'),
            'company_uuid' => session('company')
        ]);

        return response()->json([
            'school' => $school->load(['students', 'routes'])
        ], 201);
    }

    /**
     * Display the specified school.
     */
    public function show(string $id): JsonResponse
    {
        $school = School::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['students', 'routes.stops'])
            ->firstOrFail();

        return response()->json([
            'school' => [
                'id' => $school->uuid,
                'public_id' => $school->public_id,
                'name' => $school->name,
                'code' => $school->code,
                'address' => $school->address,
                'city' => $school->city,
                'state' => $school->state,
                'zip_code' => $school->zip_code,
                'country' => $school->country,
                'phone' => $school->phone,
                'email' => $school->email,
                'principal_name' => $school->principal_name,
                'school_type' => $school->school_type,
                'school_type_display' => $school->school_type_display,
                'grade_levels' => $school->grade_levels,
                'total_students' => $school->total_students,
                'operating_hours' => $school->operating_hours,
                'timezone' => $school->timezone,
                'is_active' => $school->is_active,
                'full_address' => $school->full_address,
                'students' => $school->students,
                'routes' => $school->routes,
                'created_at' => $school->created_at,
                'updated_at' => $school->updated_at
            ]
        ]);
    }

    /**
     * Update the specified school.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $school = School::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:school_transport_schools,code,' . $school->id . ',id',
            'address' => 'sometimes|string',
            'city' => 'sometimes|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'principal_name' => 'nullable|string|max:255',
            'school_type' => 'sometimes|in:elementary,middle,high,k12',
            'grade_levels' => 'nullable|array',
            'total_students' => 'nullable|integer|min:0',
            'operating_hours' => 'nullable|array',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean'
        ]);

        $school->update($request->only([
            'name',
            'code',
            'address',
            'city',
            'state',
            'zip_code',
            'country',
            'phone',
            'email',
            'principal_name',
            'school_type',
            'grade_levels',
            'total_students',
            'operating_hours',
            'timezone',
            'is_active'
        ]));

        return response()->json([
            'school' => $school->fresh()->load(['students', 'routes'])
        ]);
    }

    /**
     * Remove the specified school.
     */
    public function destroy(string $id): JsonResponse
    {
        $school = School::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        // Check if school has active students or routes
        if ($school->students()->exists() || $school->routes()->exists()) {
            return response()->json([
                'error' => 'Cannot delete school with active students or routes'
            ], 422);
        }

        $school->delete();

        return response()->json([
            'message' => 'School deleted successfully'
        ]);
    }

    /**
     * Get school statistics.
     */
    public function statistics(string $id): JsonResponse
    {
        $school = School::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $stats = [
            'total_students' => $school->students()->count(),
            'active_students' => $school->students()->active()->count(),
            'total_routes' => $school->routes()->count(),
            'active_routes' => $school->routes()->active()->count(),
            'students_by_grade' => $school->students()
                ->active()
                ->groupBy('grade')
                ->selectRaw('grade, count(*) as count')
                ->orderBy('grade')
                ->get(),
            'students_with_special_needs' => $school->students()
                ->whereNotNull('special_needs')
                ->count()
        ];

        return response()->json($stats);
    }

    /**
     * Get dashboard statistics for all schools.
     */
    public function dashboardStats(): JsonResponse
    {
        $companyUuid = session('company');

        $stats = [
            'total_schools' => School::where('company_uuid', $companyUuid)->count(),
            'active_schools' => School::where('company_uuid', $companyUuid)->active()->count(),
            'total_students' => Student::where('company_uuid', $companyUuid)->count(),
            'schools_by_type' => School::where('company_uuid', $companyUuid)
                ->active()
                ->groupBy('school_type')
                ->selectRaw('school_type, count(*) as count')
                ->get(),
            'recent_schools' => School::where('company_uuid', $companyUuid)
                ->whereBetween('created_at', [now()->subDays(30), now()])
                ->count()
        ];

        return response()->json($stats);
    }
}
