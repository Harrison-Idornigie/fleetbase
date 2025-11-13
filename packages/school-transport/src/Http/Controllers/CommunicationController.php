<?php

namespace Fleetbase\SchoolTransportEngine\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;
use Fleetbase\SchoolTransportEngine\Services\CommunicationService;
use Fleetbase\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommunicationController extends FleetbaseController
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
    public $resource = 'communication';

    /**
     * The communication service instance
     *
     * @var CommunicationService
     */
    protected $communicationService;

    /**
     * Create a new controller instance
     */
    public function __construct(CommunicationService $communicationService)
    {
        $this->communicationService = $communicationService;
    }

    /**
     * Display a listing of communications.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->fleetbaseRequest(
            // Query setup
            function (&$query, Request $request) {
                $query->where('company_uuid', session('company'));

                // Filter by type
                if ($request->filled('type')) {
                    $query->byType($request->input('type'));
                }

                // Filter by priority
                if ($request->filled('priority')) {
                    $query->byPriority($request->input('priority'));
                }

    /**
     * Send notification with settings validation
     */
    public function sendNotification(Request $request): JsonResponse
    {
        // Get notification settings
        $notificationSettings = Setting::lookupCompany('school-transport.notifications', [
            'parent_eta_notifications' => true,
            'parent_delay_notifications' => true,
            'parent_route_change_notifications' => true,
            'school_attendance_notifications' => true,
            'emergency_alert_escalation' => true,
        ]);

        // Get parent portal settings
        $parentPortalSettings = Setting::lookupCompany('school-transport.parent-portal', [
            'enabled' => true,
            'eta_notifications' => true,
            'mobile_app_enabled' => true,
        ]);

        $notificationType = $request->input('type');
        $recipients = $request->input('recipients', []);
        $message = $request->input('message');
        $priority = $request->input('priority', 'normal');

        // Check if notification type is enabled
        if (!$this->isNotificationTypeEnabled($notificationType, $notificationSettings)) {
            return response()->json([
                'error' => 'Notification type disabled',
                'message' => "Notifications of type '{$notificationType}' are currently disabled in settings.",
            ], 400);
        }

        // Check parent portal access for parent notifications
        if (str_contains($notificationType, 'parent') && !$parentPortalSettings['enabled']) {
            return response()->json([
                'error' => 'Parent portal disabled',
                'message' => 'Parent notifications require parent portal to be enabled.',
            ], 400);
        }

        // Create communication record
        $communication = Communication::create([
            'company_uuid' => session('company'),
            'type' => $notificationType,
            'message' => $message,
            'priority' => $priority,
            'recipients' => $recipients,
            'status' => 'pending',
            'settings_applied' => [
                'notification_settings' => $notificationSettings,
                'parent_portal_settings' => $parentPortalSettings,
            ],
        ]);

        // Send the notification
        $result = $this->dispatchNotification($communication, $notificationSettings, $parentPortalSettings);

        return response()->json([
            'communication' => $communication,
            'delivery_result' => $result,
            'settings_applied' => [
                'type_enabled' => true,
                'parent_portal_enabled' => $parentPortalSettings['enabled'],
                'mobile_app_enabled' => $parentPortalSettings['mobile_app_enabled'],
            ],
        ]);
    }

    /**
     * Check if notification type is enabled in settings
     */
    private function isNotificationTypeEnabled($type, $settings)
    {
        $typeMapping = [
            'parent_eta' => 'parent_eta_notifications',
            'parent_delay' => 'parent_delay_notifications',
            'parent_route_change' => 'parent_route_change_notifications',
            'school_attendance' => 'school_attendance_notifications',
            'emergency' => 'emergency_alert_escalation',
        ];

        return $settings[$typeMapping[$type] ?? $type] ?? false;
    }

    /**
     * Dispatch notification based on settings
     */
    private function dispatchNotification($communication, $notificationSettings, $parentPortalSettings)
    {
        $channels = [];

        // Determine delivery channels based on settings
        if ($parentPortalSettings['mobile_app_enabled'] && str_contains($communication->type, 'parent')) {
            $channels[] = 'mobile_push';
        }

        if ($notificationSettings['parent_eta_notifications']) {
            $channels[] = 'email';
            $channels[] = 'sms';
        }

        // Emergency escalation
        if ($communication->priority === 'emergency' && $notificationSettings['emergency_alert_escalation']) {
            $channels[] = 'phone_call';
            $channels[] = 'emergency_contact';
        }

        // Simulate notification dispatch
        $results = [];
        foreach ($channels as $channel) {
            $results[$channel] = [
                'status' => 'sent',
                'timestamp' => now(),
                'recipients' => count($communication->recipients),
            ];
        }

        return [
            'channels_used' => $channels,
            'delivery_results' => $results,
            'total_sent' => count($channels) * count($communication->recipients),
        ];
    }

                // Filter by route
                if ($request->filled('route_id')) {
                    $query->forRoute($request->input('route_id'));
                }

                // Filter by student
                if ($request->filled('student_id')) {
                    $query->forStudent($request->input('student_id'));
                }

                // Filter pending communications
                if ($request->boolean('pending')) {
                    $query->pending();
                }

                // Filter sent communications
                if ($request->boolean('sent')) {
                    $query->sent();
                }

                // Filter high priority
                if ($request->boolean('high_priority')) {
                    $query->highPriority();
                }

                // Include relationships
                $query->with(['route', 'student']);

                // Default ordering
                $query->orderBy('created_at', 'desc');
            },
            // Transform function
            function (&$communications) {
                return $communications->map(function ($communication) {
                    return [
                        'id' => $communication->uuid,
                        'public_id' => $communication->public_id,
                        'type' => $communication->type,
                        'title' => $communication->title,
                        'message' => $communication->message,
                        'formatted_message' => $communication->formatted_message,
                        'priority' => $communication->priority,
                        'status' => $communication->status,
                        'delivery_channels' => $communication->delivery_channels,
                        'route' => $communication->route ? [
                            'id' => $communication->route->uuid,
                            'route_name' => $communication->route->route_name,
                            'school' => $communication->route->school
                        ] : null,
                        'student' => $communication->student ? [
                            'id' => $communication->student->uuid,
                            'full_name' => $communication->student->full_name,
                            'student_id' => $communication->student->student_id
                        ] : null,
                        'recipients_count' => count($communication->recipients ?? []),
                        'is_scheduled' => $communication->is_scheduled,
                        'is_sent' => $communication->is_sent,
                        'delivery_rate' => $communication->delivery_rate,
                        'requires_acknowledgment' => $communication->requires_acknowledgment,
                        'acknowledgment_rate' => $communication->acknowledgment_rate,
                        'scheduled_at' => $communication->scheduled_at,
                        'sent_at' => $communication->sent_at,
                        'created_at' => $communication->created_at,
                        'updated_at' => $communication->updated_at
                    ];
                });
            }
        );
    }

    /**
     * Store a newly created communication.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('school-transport.manage');

        $this->validate($request, [
            'type' => 'required|in:notification,alert,reminder,update,emergency',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'recipients' => 'required|array|min:1',
            'delivery_channels' => 'required|array|min:1',
            'delivery_channels.*' => 'in:email,sms,app_notification',
            'priority' => 'required|in:low,normal,high,urgent',
            'route_id' => 'nullable|exists:school_transport_routes,uuid',
            'student_id' => 'nullable|exists:school_transport_students,uuid',
            'requires_acknowledgment' => 'boolean',
            'scheduled_at' => 'nullable|date|after:now',
            'template_data' => 'nullable|array'
        ]);

        // Validate recipients
        $recipients = $request->input('recipients');
        $validatedRecipients = [];

        foreach ($recipients as $recipient) {
            if ($recipient === 'all') {
                $validatedRecipients[] = 'all';
            } elseif (is_string($recipient) && \Illuminate\Support\Str::isUuid($recipient)) {
                // Validate that UUID exists in students or other recipient tables
                $validatedRecipients[] = $recipient;
            }
        }

        if (empty($validatedRecipients)) {
            return response()->json([
                'error' => 'No valid recipients provided'
            ], 422);
        }

        $communication = Communication::create([
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'recipients' => $validatedRecipients,
            'delivery_channels' => $request->input('delivery_channels'),
            'priority' => $request->input('priority'),
            'route_uuid' => $request->input('route_id'),
            'student_uuid' => $request->input('student_id'),
            'requires_acknowledgment' => $request->boolean('requires_acknowledgment'),
            'scheduled_at' => $request->input('scheduled_at'),
            'template_data' => $request->input('template_data'),
            'created_by_uuid' => auth()->id(),
            'company_uuid' => session('company')
        ]);

        // Apply template if template data provided
        if ($request->filled('template_data')) {
            $communication->applyTemplate($request->input('template_data'));
            $communication->save();
        }

        return response()->json([
            'communication' => $communication->load(['route', 'student'])
        ], 201);
    }

    /**
     * Display the specified communication.
     */
    public function show(string $id): JsonResponse
    {
        $communication = Communication::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->with(['route', 'student'])
            ->firstOrFail();

        return response()->json([
            'communication' => [
                'id' => $communication->uuid,
                'public_id' => $communication->public_id,
                'type' => $communication->type,
                'title' => $communication->title,
                'message' => $communication->message,
                'formatted_message' => $communication->formatted_message,
                'recipients' => $communication->recipients,
                'delivery_channels' => $communication->delivery_channels,
                'priority' => $communication->priority,
                'route' => $communication->route,
                'student' => $communication->student,
                'status' => $communication->status,
                'is_scheduled' => $communication->is_scheduled,
                'is_sent' => $communication->is_sent,
                'scheduled_at' => $communication->scheduled_at,
                'sent_at' => $communication->sent_at,
                'delivery_status' => $communication->delivery_status,
                'delivery_rate' => $communication->delivery_rate,
                'template_data' => $communication->template_data,
                'requires_acknowledgment' => $communication->requires_acknowledgment,
                'acknowledgments' => $communication->acknowledgments,
                'acknowledgment_rate' => $communication->acknowledgment_rate,
                'unacknowledged_recipients' => $communication->getUnacknowledgedRecipients(),
                'created_by_uuid' => $communication->created_by_uuid,
                'meta' => $communication->meta,
                'created_at' => $communication->created_at,
                'updated_at' => $communication->updated_at
            ]
        ]);
    }

    /**
     * Update communication status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $this->authorize('school-transport.manage');

        $communication = Communication::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'status' => 'required|in:draft,scheduled,sent,delivered,failed'
        ]);

        $newStatus = $request->input('status');

        // Validate status transitions
        $validTransitions = [
            'draft' => ['scheduled', 'sent'],
            'scheduled' => ['sent', 'draft'],
            'sent' => ['delivered', 'failed'],
            'delivered' => [], // Final state
            'failed' => ['draft', 'scheduled'] // Can retry
        ];

        if (!in_array($newStatus, $validTransitions[$communication->status] ?? [])) {
            return response()->json([
                'error' => "Invalid status transition from {$communication->status} to {$newStatus}"
            ], 422);
        }

        $communication->status = $newStatus;

        if ($newStatus === 'sent') {
            $communication->sent_at = now();
        }

        $communication->save();

        return response()->json([
            'communication' => $communication->fresh()
        ]);
    }

    /**
     * Send a notification immediately.
     */
    public function sendNotification(Request $request): JsonResponse
    {
        $this->authorize('school-transport.manage');

        $this->validate($request, [
            'type' => 'required|in:delay,route_change,emergency,absence',
            'route_id' => 'nullable|exists:school_transport_routes,uuid',
            'student_id' => 'nullable|exists:school_transport_students,uuid',
            'message_data' => 'required|array',
            'channels' => 'nullable|array',
            'channels.*' => 'in:email,sms,app_notification'
        ]);

        $type = $request->input('type');
        $routeId = $request->input('route_id');
        $studentId = $request->input('student_id');
        $messageData = $request->input('message_data');
        $channels = $request->input('channels', ['email', 'sms']);

        // Create appropriate notification based on type
        switch ($type) {
            case 'delay':
                if (!$routeId || !isset($messageData['delay_minutes'])) {
                    return response()->json(['error' => 'Route ID and delay minutes required'], 422);
                }

                $communication = Communication::createDelayNotification(
                    $routeId,
                    $messageData['delay_minutes'],
                    $messageData['reason'] ?? null
                );
                break;

            case 'emergency':
                if (!isset($messageData['title']) || !isset($messageData['message'])) {
                    return response()->json(['error' => 'Title and message required for emergency'], 422);
                }

                $communication = Communication::createEmergencyAlert(
                    $messageData['title'],
                    $messageData['message'],
                    $messageData['recipients'] ?? ['all'],
                    $routeId
                );
                break;

            default:
                return response()->json(['error' => 'Notification type not implemented'], 422);
        }

        // Update delivery channels if specified
        if ($channels) {
            $communication->delivery_channels = $channels;
            $communication->save();
        }

        // Send immediately
        $sent = $communication->send();

        return response()->json([
            'success' => $sent,
            'communication' => $communication->fresh(),
            'message' => $sent ? 'Notification sent successfully' : 'Failed to send notification'
        ]);
    }

    /**
     * Get communication templates.
     */
    public function templates(): JsonResponse
    {
        $templates = config('school-transport.communications.notification_templates', []);

        $formattedTemplates = [];
        foreach ($templates as $type => $template) {
            $formattedTemplates[] = [
                'type' => $type,
                'name' => ucwords(str_replace('_', ' ', $type)),
                'template' => $template,
                'variables' => $this->extractTemplateVariables($template)
            ];
        }

        return response()->json([
            'templates' => $formattedTemplates
        ]);
    }

    /**
     * Extract template variables from a template string.
     */
    private function extractTemplateVariables(string $template): array
    {
        preg_match_all('/\{(\w+)\}/', $template, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Mark communication as acknowledged by recipient.
     */
    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $communication = Communication::where('uuid', $id)
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $this->validate($request, [
            'recipient_id' => 'required|string',
            'acknowledgment_data' => 'nullable|array'
        ]);

        $recipientId = $request->input('recipient_id');
        $data = $request->input('acknowledgment_data');

        $acknowledged = $communication->acknowledge($recipientId, $data);

        if (!$acknowledged) {
            return response()->json([
                'error' => 'Communication does not require acknowledgment'
            ], 422);
        }

        return response()->json([
            'message' => 'Communication acknowledged successfully',
            'acknowledgment_rate' => $communication->fresh()->acknowledgment_rate
        ]);
    }

    /**
     * Get communication analytics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $companyUuid = session('company');

        $this->validate($request, [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'nullable|in:notification,alert,reminder,update,emergency'
        ]);

        $query = Communication::where('company_uuid', $companyUuid);

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        if ($request->filled('type')) {
            $query->byType($request->input('type'));
        }

        $communications = $query->get();

        $analytics = [
            'total_communications' => $communications->count(),
            'by_type' => $communications->groupBy('type')->map(function ($group) {
                return $group->count();
            }),
            'by_priority' => $communications->groupBy('priority')->map(function ($group) {
                return $group->count();
            }),
            'by_status' => $communications->groupBy('status')->map(function ($group) {
                return $group->count();
            }),
            'delivery_stats' => [
                'average_delivery_rate' => $communications->where('status', 'sent')->avg('delivery_rate') ?: 0,
                'total_sent' => $communications->whereIn('status', ['sent', 'delivered'])->count(),
                'total_pending' => $communications->whereIn('status', ['draft', 'scheduled'])->count(),
                'total_failed' => $communications->where('status', 'failed')->count()
            ],
            'acknowledgment_stats' => [
                'requiring_acknowledgment' => $communications->where('requires_acknowledgment', true)->count(),
                'average_acknowledgment_rate' => $communications->where('requires_acknowledgment', true)->avg('acknowledgment_rate') ?: 0,
                'fully_acknowledged' => $communications->where('requires_acknowledgment', true)->filter(function ($comm) {
                    return $comm->acknowledgment_rate >= 100;
                })->count()
            ],
            'recent_activity' => $communications->sortByDesc('created_at')->take(10)->values()
        ];

        return response()->json($analytics);
    }
}
