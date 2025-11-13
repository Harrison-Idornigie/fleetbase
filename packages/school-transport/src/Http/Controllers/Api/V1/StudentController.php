<?php

namespace Fleetbase\SchoolTransport\Http\Controllers\Api\V1;

use Fleetbase\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    /**
     * List all students.
     */
    public function query(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_uuid' => 'nullable|uuid',
            'status' => 'nullable|in:active,inactive,suspended',
            'grade_level' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $query = Student::where('company_uuid', $request->user()->company_uuid);

        if ($request->has('school_uuid')) {
            $query->where('school_uuid', $request->school_uuid);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        $students = $query->paginate($request->get('limit', 25));

        return response()->json($students);
    }

    /**
     * Find a specific student.
     */
    public function find(Request $request, $id)
    {
        $student = Student::where('company_uuid', $request->user()->company_uuid)
            ->where('uuid', $id)
            ->with(['school', 'parents', 'busAssignments.bus', 'busAssignments.driver'])
            ->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        return response()->json($student);
    }

    /**
     * Create a new student.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_uuid' => 'required|uuid|exists:school_transport_schools,uuid',
            'student_id' => 'required|string|unique:school_transport_students,student_id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'grade_level' => 'required|string|max:50',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:20',
            'emergency_contact_relationship' => 'required|string|max:100',
            'pickup_address' => 'required|string',
            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',
            'dropoff_address' => 'required|string',
            'dropoff_latitude' => 'required|numeric|between:-90,90',
            'dropoff_longitude' => 'required|numeric|between:-180,180',
            'status' => 'nullable|in:active,inactive,suspended',
            'transportation_eligibility' => 'nullable|boolean',
            'special_needs' => 'nullable|boolean',
            'medical_conditions' => 'nullable|string',
            'allergies' => 'nullable|string',
            'special_instructions' => 'nullable|string',
            'photo_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $student = Student::create([
            'company_uuid' => $request->user()->company_uuid,
            'created_by_uuid' => $request->user()->uuid,
            'updated_by_uuid' => $request->user()->uuid,
            ...$request->all()
        ]);

        return response()->json($student, 201);
    }

    /**
     * Update an existing student.
     */
    public function update(Request $request, $id)
    {
        $student = Student::where('company_uuid', $request->user()->company_uuid)
            ->where('uuid', $id)
            ->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'school_uuid' => 'nullable|uuid|exists:school_transport_schools,uuid',
            'student_id' => 'nullable|string|unique:school_transport_students,student_id,' . $student->uuid . ',uuid',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'grade_level' => 'nullable|string|max:50',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'pickup_address' => 'nullable|string',
            'pickup_latitude' => 'nullable|numeric|between:-90,90',
            'pickup_longitude' => 'nullable|numeric|between:-180,180',
            'dropoff_address' => 'nullable|string',
            'dropoff_latitude' => 'nullable|numeric|between:-90,90',
            'dropoff_longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'nullable|in:active,inactive,suspended',
            'transportation_eligibility' => 'nullable|boolean',
            'special_needs' => 'nullable|boolean',
            'medical_conditions' => 'nullable|string',
            'allergies' => 'nullable|string',
            'special_instructions' => 'nullable|string',
            'photo_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $student->update([
            'updated_by_uuid' => $request->user()->uuid,
            ...$request->all()
        ]);

        return response()->json($student);
    }

    /**
     * Delete a student.
     */
    public function delete(Request $request, $id)
    {
        $student = Student::where('company_uuid', $request->user()->company_uuid)
            ->where('uuid', $id)
            ->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $student->delete();

        return response()->json(['message' => 'Student deleted successfully']);
    }
}
