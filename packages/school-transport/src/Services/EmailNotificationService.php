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
     * Send bus arrival notification
     *
     * @param string $to
     * @param array $tripData
     * @return bool
     */
    public function sendArrivalNotification(string $to, array $tripData): bool
    {
        $subject = "Bus Arriving Soon - Route {$tripData['route_name']}";
        $message = "Your child's bus is approaching the stop. Estimated arrival in {$tripData['eta_minutes']} minutes.";

        return $this->sendParentNotification($to, $subject, $message, $tripData);
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
