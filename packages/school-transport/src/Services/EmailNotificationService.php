<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    /**
     * Send parent notification email
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param array $data
     * @return bool
     */
    public function sendParentNotification(string $to, string $subject, string $message, array $data = []): bool
    {
        try {
            Mail::send('school-transport::emails.parent-notification', [
                'message' => $message,
                'data' => $data
            ], function ($mail) use ($to, $subject) {
                $mail->to($to)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info("Email sent to {$to}: {$subject}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send email to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send bus arrival notification with enhanced ETA information
     *
     * @param string $to
     * @param string $message Custom message or null for default
     * @param array $data Notification data including ETA information
     * @return bool
     */
    public function sendArrivalNotification(string $to, string $message = null, array $data = []): bool
    {
        $studentName = $data['student_name'] ?? 'Your child';
        $busNumber = $data['bus_number'] ?? 'School Bus';
        $stopName = $data['stop_name'] ?? 'the bus stop';
        $routeName = $data['route_name'] ?? 'School Route';
        $etaMinutes = $data['eta_minutes'] ?? null;
        $isArriving = $data['is_arriving'] ?? false;

        // Build subject line
        if ($isArriving) {
            $subject = "🚌 Bus Arriving NOW - {$routeName}";
        } elseif ($etaMinutes !== null && $etaMinutes <= 5) {
            $subject = "🚌 Bus Arriving Soon - {$routeName}";
        } else {
            $subject = "🚌 Bus Update - {$routeName}";
        }

        // Build email message if not provided
        if (!$message) {
            if ($isArriving) {
                $message = "
                <h2>🚌 Bus Arriving Now!</h2>
                <p><strong>{$busNumber}</strong> is arriving NOW at <strong>{$stopName}</strong> for <strong>{$studentName}</strong>.</p>
                <p style='color: #DC2626; font-weight: bold;'>Please be ready at the bus stop!</p>
                ";
            } elseif ($etaMinutes !== null) {
                if ($etaMinutes <= 1) {
                    $message = "
                    <h2>🚌 Bus Arriving Soon!</h2>
                    <p><strong>{$busNumber}</strong> will arrive at <strong>{$stopName}</strong> in less than 1 minute for <strong>{$studentName}</strong>.</p>
                    <p style='color: #F59E0B; font-weight: bold;'>Please be ready at the bus stop!</p>
                    ";
                } elseif ($etaMinutes <= 10) {
                    $message = "
                    <h2>🚌 Bus Arriving Soon!</h2>
                    <p><strong>{$busNumber}</strong> will arrive at <strong>{$stopName}</strong> in approximately <strong>{$etaMinutes} minutes</strong> for <strong>{$studentName}</strong>.</p>
                    <p style='color: #F59E0B; font-weight: bold;'>Please be ready at the bus stop!</p>
                    ";
                } else {
                    $message = "
                    <h2>🚌 Bus Update</h2>
                    <p><strong>{$busNumber}</strong> will arrive at <strong>{$stopName}</strong> in approximately <strong>{$etaMinutes} minutes</strong> for <strong>{$studentName}</strong>.</p>
                    <p>We'll send another update when the bus is closer.</p>
                    ";
                }
            } else {
                $message = "
                <h2>🚌 Bus En Route</h2>
                <p><strong>{$busNumber}</strong> is on route to <strong>{$stopName}</strong> for <strong>{$studentName}</strong>.</p>
                <p>Estimated arrival time will be updated shortly.</p>
                ";
            }

            // Add live tracking information if available
            if (!empty($data['tracking_link'])) {
                $message .= "
                <p style='margin-top: 20px;'>
                    <a href='{$data['tracking_link']}' style='background-color: #3B82F6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        📍 Track Bus Live
                    </a>
                </p>
                ";
            }

            // Add additional route information
            $message .= "
            <hr style='margin: 20px 0;'>
            <p style='color: #6B7280; font-size: 12px;'>
                Route: {$routeName}<br>
                Bus: {$busNumber}<br>
                Stop: {$stopName}<br>
                Student: {$studentName}<br>
                Time: " . now()->format('Y-m-d H:i:s') . "
            </p>
            ";
        }

        return $this->sendParentNotification($to, $subject, $message, $data);
    }

    /**
     * Send delay notification
     *
     * @param string $to
     * @param array $tripData
     * @return bool
     */
    public function sendDelayNotification(string $to, array $tripData): bool
    {
        $subject = "Bus Delayed - Route {$tripData['route_name']}";
        $message = "The bus is running {$tripData['delay_minutes']} minutes late. Reason: {$tripData['delay_reason']}.";

        return $this->sendParentNotification($to, $subject, $message, $tripData);
    }

    /**
     * Send emergency alert
     *
     * @param string $to
     * @param array $alertData
     * @return bool
     */
    public function sendEmergencyAlert(string $to, array $alertData): bool
    {
        $subject = "URGENT: {$alertData['title']}";
        $message = $alertData['description'];

        return $this->sendParentNotification($to, $subject, $message, $alertData);
    }

    /**
     * Send check-in notification
     *
     * @param string $to
     * @param array $studentData
     * @return bool
     */
    public function sendCheckInNotification(string $to, array $studentData): bool
    {
        $subject = "{$studentData['student_name']} Checked In";
        $message = "Your child has been checked in to the bus at {$studentData['time']}.";

        return $this->sendParentNotification($to, $subject, $message, $studentData);
    }

    /**
     * Send check-out notification
     *
     * @param string $to
     * @param array $studentData
     * @return bool
     */
    public function sendCheckOutNotification(string $to, array $studentData): bool
    {
        $subject = "{$studentData['student_name']} Arrived at School";
        $message = "Your child has arrived safely at school at {$studentData['time']}.";

        return $this->sendParentNotification($to, $subject, $message, $studentData);
    }

    /**
     * Send bulk notifications
     *
     * @param array $recipients Array of ['email' => $email, 'subject' => $subject, 'message' => $message]
     * @return array ['success' => int, 'failed' => int]
     */
    public function sendBulk(array $recipients): array
    {
        $success = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $result = $this->sendParentNotification(
                $recipient['email'],
                $recipient['subject'],
                $recipient['message'],
                $recipient['data'] ?? []
            );

            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }
}
