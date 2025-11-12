<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Communication;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send communication to recipients
     *
     * @param Communication $communication
     * @return array
     */
    public function sendCommunication(Communication $communication)
    {
        try {
            $recipients = $this->resolveRecipients($communication);

            if (empty($recipients)) {
                return [
                    'success' => false,
                    'message' => 'No recipients found'
                ];
            }

            $deliveryStatus = [];
            $channels = $communication->delivery_channels ?? ['email'];

            foreach ($recipients as $recipient) {
                $status = [];

                foreach ($channels as $channel) {
                    switch ($channel) {
                        case 'email':
                            $status['email'] = $this->sendEmail($recipient, $communication);
                            break;
                        case 'sms':
                            $status['sms'] = $this->sendSMS($recipient, $communication);
                            break;
                        case 'push':
                            $status['push'] = $this->sendPushNotification($recipient, $communication);
                            break;
                    }
                }

                $deliveryStatus[$recipient['id']] = $status;
            }

            // Update communication status
            $communication->update([
                'status' => 'sent',
                'sent_at' => now(),
                'delivery_status' => $deliveryStatus
            ]);

            return [
                'success' => true,
                'message' => 'Communication sent successfully',
                'delivery_status' => $deliveryStatus
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send communication: ' . $e->getMessage());

            $communication->update([
                'status' => 'failed'
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send communication: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Resolve recipients based on communication settings
     *
     * @param Communication $communication
     * @return array
     */
    protected function resolveRecipients(Communication $communication)
    {
        $recipients = [];
        $recipientList = $communication->recipients ?? [];

        foreach ($recipientList as $recipientType) {
            if ($recipientType === 'all_parents') {
                $recipients = array_merge($recipients, $this->getAllParents());
            } elseif ($recipientType === 'route_parents' && $communication->route_uuid) {
                $recipients = array_merge($recipients, $this->getRouteParents($communication->route_uuid));
            } elseif ($recipientType === 'student_parent' && $communication->student_uuid) {
                $recipients = array_merge($recipients, $this->getStudentParents($communication->student_uuid));
            } elseif (str_starts_with($recipientType, 'parent-')) {
                // Specific parent ID
                $parentId = str_replace('parent-', '', $recipientType);
                $recipients[] = $this->getParentById($parentId);
            }
        }

        return array_filter($recipients);
    }

    /**
     * Get all parents
     *
     * @return array
     */
    protected function getAllParents()
    {
        $students = Student::where('is_active', true)->get();
        $parents = [];

        foreach ($students as $student) {
            if ($student->parent_email) {
                $parents[] = [
                    'id' => 'parent-' . $student->uuid,
                    'name' => $student->parent_name,
                    'email' => $student->parent_email,
                    'phone' => $student->parent_phone,
                    'student_id' => $student->uuid
                ];
            }
        }

        return $parents;
    }

    /**
     * Get parents for a specific route
     *
     * @param string $routeUuid
     * @return array
     */
    protected function getRouteParents($routeUuid)
    {
        $route = SchoolRoute::with('busAssignments.student')->find($routeUuid);

        if (!$route) {
            return [];
        }

        $parents = [];
        foreach ($route->busAssignments as $assignment) {
            $student = $assignment->student;
            if ($student && $student->parent_email) {
                $parents[] = [
                    'id' => 'parent-' . $student->uuid,
                    'name' => $student->parent_name,
                    'email' => $student->parent_email,
                    'phone' => $student->parent_phone,
                    'student_id' => $student->uuid
                ];
            }
        }

        return $parents;
    }

    /**
     * Get parents for a specific student
     *
     * @param string $studentUuid
     * @return array
     */
    protected function getStudentParents($studentUuid)
    {
        $student = Student::find($studentUuid);

        if (!$student || !$student->parent_email) {
            return [];
        }

        return [[
            'id' => 'parent-' . $student->uuid,
            'name' => $student->parent_name,
            'email' => $student->parent_email,
            'phone' => $student->parent_phone,
            'student_id' => $student->uuid
        ]];
    }

    /**
     * Get parent by ID
     *
     * @param string $parentId
     * @return array|null
     */
    protected function getParentById($parentId)
    {
        $student = Student::find($parentId);

        if (!$student || !$student->parent_email) {
            return null;
        }

        return [
            'id' => 'parent-' . $student->uuid,
            'name' => $student->parent_name,
            'email' => $student->parent_email,
            'phone' => $student->parent_phone,
            'student_id' => $student->uuid
        ];
    }

    /**
     * Send email notification
     *
     * @param array $recipient
     * @param Communication $communication
     * @return string
     */
    protected function sendEmail($recipient, $communication)
    {
        try {
            // Replace template variables
            $message = $this->replaceTemplateVariables(
                $communication->message,
                $communication->template_data ?? []
            );

            Mail::raw($message, function ($mail) use ($recipient, $communication) {
                $mail->to($recipient['email'], $recipient['name'])
                    ->subject($communication->title);
            });

            return 'delivered';
        } catch (\Exception $e) {
            Log::error('Email send failed: ' . $e->getMessage());
            return 'failed';
        }
    }

    /**
     * Send SMS notification
     *
     * @param array $recipient
     * @param Communication $communication
     * @return string
     */
    protected function sendSMS($recipient, $communication)
    {
        try {
            if (empty($recipient['phone'])) {
                return 'failed';
            }

            $message = $this->replaceTemplateVariables(
                $communication->message,
                $communication->template_data ?? []
            );

            // Truncate message for SMS (160 characters)
            $message = substr($message, 0, 160);

            // TODO: Integrate with SMS provider (Twilio, etc.)
            // For now, just log
            Log::info("SMS to {$recipient['phone']}: {$message}");

            return 'delivered';
        } catch (\Exception $e) {
            Log::error('SMS send failed: ' . $e->getMessage());
            return 'failed';
        }
    }

    /**
     * Send push notification
     *
     * @param array $recipient
     * @param Communication $communication
     * @return string
     */
    protected function sendPushNotification($recipient, $communication)
    {
        try {
            $message = $this->replaceTemplateVariables(
                $communication->message,
                $communication->template_data ?? []
            );

            // TODO: Integrate with push notification service (FCM, etc.)
            Log::info("Push notification to {$recipient['id']}: {$message}");

            return 'delivered';
        } catch (\Exception $e) {
            Log::error('Push notification failed: ' . $e->getMessage());
            return 'failed';
        }
    }

    /**
     * Replace template variables in message
     *
     * @param string $message
     * @param array $data
     * @return string
     */
    protected function replaceTemplateVariables($message, $data)
    {
        foreach ($data as $key => $value) {
            $message = str_replace("{{" . $key . "}}", $value, $message);
        }

        return $message;
    }

    /**
     * Send arrival notification
     *
     * @param string $routeUuid
     * @param string $stopName
     * @param int $eta
     * @return array
     */
    public function sendArrivalNotification($routeUuid, $stopName, $eta)
    {
        $route = SchoolRoute::find($routeUuid);

        if (!$route) {
            return ['success' => false, 'message' => 'Route not found'];
        }

        $communication = Communication::create([
            'company_uuid' => $route->company_uuid,
            'type' => 'notification',
            'title' => 'Bus Arriving Soon',
            'message' => "The bus for {$route->route_name} will arrive at {$stopName} in {$eta} minutes.",
            'route_uuid' => $routeUuid,
            'recipients' => ['route_parents'],
            'delivery_channels' => ['sms', 'push'],
            'priority' => 'normal',
            'status' => 'draft'
        ]);

        return $this->sendCommunication($communication);
    }
}
