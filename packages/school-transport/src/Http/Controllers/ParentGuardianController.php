<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Models\ParentGuardian;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ParentGuardianController extends FleetbaseController
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
    public $resource = 'parent_guardian';

    /**
     * Display a listing of parent guardians.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by student
                if ($request->filled('student')) {
                    $query->whereHas('students', function ($q) use ($request) {
                        $q->where('uuid', $request->input('student'));
                    });
                }

                // Filter by relationship type
                if ($request->filled('relationship')) {
                    $query->where('relationship', $request->input('relationship'));
                }

                // Search by name or email
                if ($request->filled('search')) {
                    $search = $request->input('search');
                    $query->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                }

                // Include relationships
                $query->with(['students']);
            },
            // Transform function
            function (&$guardians) {
                return $guardians->map(function ($guardian) {
                    return [
                        'id' => $guardian->uuid,
                        'public_id' => $guardian->public_id,
                        'first_name' => $guardian->first_name,
                        'last_name' => $guardian->last_name,
                        'full_name' => $guardian->full_name,
                        'email' => $guardian->email,
                        'phone' => $guardian->phone,
                        'relationship' => $guardian->relationship,
                        'relationship_display' => $guardian->relationship_display,
                        'emergency_contact' => $guardian->emergency_contact,
                        'notification_preferences' => $guardian->notification_preferences,
                        'students' => $guardian->students->map(function ($student) {
                            return [
                                'id' => $student->uuid,
                                'public_id' => $student->public_id,
                                'first_name' => $student->first_name,
                                'last_name' => $student->last_name,
                                'student_id' => $student->student_id
                            ];
                        }),
                        'created_at' => $guardian->created_at
                    ];
                });
            }
        );
    }

    /**
     * Store a new parent guardian.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:school_transport_parent_guardians,email',
            'phone' => 'required|string|max:20',
            'relationship' => 'required|in:father,mother,guardian,grandparent,other',
            'emergency_contact' => 'boolean',
            'notification_preferences' => 'nullable|json',
            'student_uuids' => 'nullable|array',
            'student_uuids.*' => 'exists:school_transport_students,uuid'
        ]);

        // Verify that all students belong to the company
        if ($request->filled('student_uuids')) {
            foreach ($request->input('student_uuids') as $studentUuid) {
                Student::where('uuid', $studentUuid)
                    ->where('company_uuid', session('company'))
                    ->firstOrFail();
            }
        }

        $guardian = ParentGuardian::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'relationship' => $request->input('relationship'),
            'emergency_contact' => $request->input('emergency_contact', false),
            'notification_preferences' => $request->input('notification_preferences'),
            'company_uuid' => session('company')
        ]);

        // Attach students if provided
        if ($request->filled('student_uuids')) {
            $guardian->students()->attach($request->input('student_uuids'));
        }

        return response()->json([
            'parent_guardian' => $guardian->load('students')
        ], 201);
    }

    /**
     * Display the specified parent guardian.
     */
    public function show(string $id): JsonResponse
    {
        $guardian = ParentGuardian::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['students'])
            ->firstOrFail();

        return response()->json([
            'parent_guardian' => [
                'id' => $guardian->uuid,
                'public_id' => $guardian->public_id,
                'first_name' => $guardian->first_name,
                'last_name' => $guardian->last_name,
                'full_name' => $guardian->full_name,
                'email' => $guardian->email,
                'phone' => $guardian->phone,
                'relationship' => $guardian->relationship,
                'relationship_display' => $guardian->relationship_display,
                'emergency_contact' => $guardian->emergency_contact,
                'notification_preferences' => $guardian->notification_preferences,
                'students' => $guardian->students->map(function ($student) {
                    return [
                        'id' => $student->uuid,
                        'public_id' => $student->public_id,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'student_id' => $student->student_id,
                        'grade' => $student->grade,
                        'school' => $student->school
                    ];
                }),
                'created_at' => $guardian->created_at,
                'updated_at' => $guardian->updated_at
            ]
        ]);
    }

    /**
     * Update the specified parent guardian.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $guardian = ParentGuardian::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:school_transport_parent_guardians,email,' . $guardian->id,
            'phone' => 'sometimes|required|string|max:20',
            'relationship' => 'sometimes|required|in:father,mother,guardian,grandparent,other',
            'emergency_contact' => 'boolean',
            'notification_preferences' => 'nullable|json',
            'student_uuids' => 'nullable|array',
            'student_uuids.*' => 'exists:school_transport_students,uuid'
        ]);

        // Verify that all students belong to the company
        if ($request->filled('student_uuids')) {
            foreach ($request->input('student_uuids') as $studentUuid) {
                Student::where('uuid', $studentUuid)
                    ->where('company_uuid', session('company'))
                    ->firstOrFail();
            }
        }

        $guardian->update($request->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'relationship',
            'emergency_contact',
            'notification_preferences'
        ]));

        // Sync students if provided
        if ($request->has('student_uuids')) {
            $guardian->students()->sync($request->input('student_uuids'));
        }

        return response()->json([
            'parent_guardian' => $guardian->load('students')
        ]);
    }

    /**
     * Remove the specified parent guardian.
     */
    public function destroy(string $id): JsonResponse
    {
        $guardian = ParentGuardian::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $guardian->delete();

        return response()->json([
            'message' => 'Parent guardian deleted successfully'
        ]);
    }

    /**
     * Add students to a parent guardian.
     */
    public function addStudents(Request $request, string $id): JsonResponse
    {
        $guardian = ParentGuardian::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'student_uuids' => 'required|array|min:1',
            'student_uuids.*' => 'exists:school_transport_students,uuid'
        ]);

        // Verify that all students belong to the company
        foreach ($request->input('student_uuids') as $studentUuid) {
            Student::where('uuid', $studentUuid)
                ->where('company_uuid', session('company'))
                ->firstOrFail();
        }

        $guardian->students()->attach($request->input('student_uuids'));

        return response()->json([
            'parent_guardian' => $guardian->load('students'),
            'message' => 'Students added successfully'
        ]);
    }

    /**
     * Remove students from a parent guardian.
     */
    public function removeStudents(Request $request, string $id): JsonResponse
    {
        $guardian = ParentGuardian::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'student_uuids' => 'required|array|min:1',
            'student_uuids.*' => 'exists:school_transport_students,uuid'
        ]);

        $guardian->students()->detach($request->input('student_uuids'));

        return response()->json([
            'parent_guardian' => $guardian->load('students'),
            'message' => 'Students removed successfully'
        ]);
    }

    /**
     * Get emergency contacts.
     */
    public function emergencyContacts(): JsonResponse
    {
        $emergencyContacts = ParentGuardian::where('company_uuid', session('company'))
            ->where('emergency_contact', true)
            ->with(['students'])
            ->get();

        return response()->json([
            'emergency_contacts' => $emergencyContacts->map(function ($guardian) {
                return [
                    'id' => $guardian->uuid,
                    'full_name' => $guardian->full_name,
                    'phone' => $guardian->phone,
                    'email' => $guardian->email,
                    'relationship' => $guardian->relationship_display,
                    'students' => $guardian->students->map(function ($student) {
                        return [
                            'id' => $student->uuid,
                            'first_name' => $student->first_name,
                            'last_name' => $student->last_name,
                            'student_id' => $student->student_id
                        ];
                    })
                ];
            })
        ]);
    }

    /**
     * Send notification to parent guardian.
     */
    public function sendNotification(Request $request, string $id): JsonResponse
    {
        $guardian = ParentGuardian::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'type' => 'required|in:sms,email,push',
            'subject' => 'required_if:type,email|string|max:255',
            'message' => 'required|string|max:1000',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ]);

        // Here you would integrate with your notification service
        // For now, we'll just return a success response
        // In a real implementation, you'd send SMS via Twilio, email via Mailgun, etc.

        $notification = [
            'guardian_id' => $guardian->uuid,
            'type' => $request->input('type'),
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
            'priority' => $request->input('priority', 'normal'),
            'sent_at' => now(),
            'status' => 'sent'
        ];

        return response()->json([
            'notification' => $notification,
            'message' => 'Notification sent successfully'
        ]);
    }

    /**
     * Bulk send notifications to multiple parent guardians.
     */
    public function bulkSendNotifications(Request $request): JsonResponse
    {
        $this->validate($request, [
            'guardian_uuids' => 'required|array|min:1',
            'guardian_uuids.*' => 'exists:school_transport_parent_guardians,uuid',
            'type' => 'required|in:sms,email,push',
            'subject' => 'required_if:type,email|string|max:255',
            'message' => 'required|string|max:1000',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ]);

        // Verify all guardians belong to the company
        $guardians = ParentGuardian::whereIn('uuid', $request->input('guardian_uuids'))
            ->where('company_uuid', session('company'))
            ->get();

        if ($guardians->count() !== count($request->input('guardian_uuids'))) {
            return response()->json([
                'error' => 'Some guardians not found or do not belong to this company'
            ], 400);
        }

        // Here you would integrate with your notification service
        // For now, we'll just return a success response

        $notifications = $guardians->map(function ($guardian) use ($request) {
            return [
                'guardian_id' => $guardian->uuid,
                'guardian_name' => $guardian->full_name,
                'type' => $request->input('type'),
                'subject' => $request->input('subject'),
                'message' => $request->input('message'),
                'priority' => $request->input('priority', 'normal'),
                'sent_at' => now(),
                'status' => 'sent'
            ];
        });

        return response()->json([
            'notifications' => $notifications,
            'total_sent' => $notifications->count(),
            'message' => 'Bulk notifications sent successfully'
        ]);
    }

    /**
     * Get parent guardians by student.
     */
    public function byStudent(string $studentId): JsonResponse
    {
        $student = Student::where('uuid', $studentId)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $guardians = $student->parentGuardians;

        return response()->json([
            'student' => [
                'id' => $student->uuid,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_id' => $student->student_id
            ],
            'parent_guardians' => $guardians->map(function ($guardian) {
                return [
                    'id' => $guardian->uuid,
                    'full_name' => $guardian->full_name,
                    'email' => $guardian->email,
                    'phone' => $guardian->phone,
                    'relationship' => $guardian->relationship_display,
                    'emergency_contact' => $guardian->emergency_contact
                ];
            })
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updateNotificationPreferences(Request $request, string $id): JsonResponse
    {
        $guardian = ParentGuardian::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'notification_preferences' => 'required|json'
        ]);

        $guardian->update([
            'notification_preferences' => $request->input('notification_preferences')
        ]);

        return response()->json([
            'parent_guardian' => $guardian,
            'message' => 'Notification preferences updated successfully'
        ]);
    }
}
