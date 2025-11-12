<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class SmsNotificationService
{
    protected $client;
    protected $fromNumber;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->fromNumber = config('services.twilio.from');

        if ($sid && $token) {
            try {
                $this->client = new Client($sid, $token);
            } catch (\Exception $e) {
                Log::error("Failed to initialize Twilio client: " . $e->getMessage());
                $this->client = null;
            }
        }
    }

    /**
     * Send SMS notification
     *
     * @param string $to Phone number in E.164 format
     * @param string $message
     * @return bool
     */
    public function send(string $to, string $message): bool
    {
        if (!$this->client) {
            Log::warning("Twilio client not configured, skipping SMS to {$to}");
            return false;
        }

        try {
            $this->client->messages->create($to, [
                'from' => $this->fromNumber,
                'body' => $message
            ]);

            Log::info("SMS sent to {$to}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send SMS to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send arrival notification with ETA information
     *
     * @param string $to
     * @param string $message Custom message or null for default
     * @param array $data Notification data including ETA information
     * @return bool
     */
    public function sendArrivalNotification(string $to, string $message = null, array $data = []): bool
    {
        if (!$message) {
            // Build default message with enhanced ETA information
            $studentName = $data['student_name'] ?? 'your child';
            $busNumber = $data['bus_number'] ?? 'School Bus';
            $stopName = $data['stop_name'] ?? 'your stop';
            $etaMinutes = $data['eta_minutes'] ?? null;
            $isArriving = $data['is_arriving'] ?? false;

            if ($isArriving) {
                $message = "🚌 {$busNumber} is arriving NOW at {$stopName} for {$studentName}. Please be ready!";
            } elseif ($etaMinutes !== null) {
                if ($etaMinutes <= 1) {
                    $message = "🚌 {$busNumber} will arrive at {$stopName} in less than 1 minute for {$studentName}. Please be ready!";
                } elseif ($etaMinutes <= 5) {
                    $message = "🚌 {$busNumber} will arrive at {$stopName} in {$etaMinutes} minutes for {$studentName}. Please be ready!";
                } else {
                    $message = "🚌 {$busNumber} will arrive at {$stopName} in approximately {$etaMinutes} minutes for {$studentName}.";
                }
            } else {
                $message = "🚌 {$busNumber} is on route to {$stopName} for {$studentName}. Estimated arrival time will be updated shortly.";
            }

            // Add live tracking link if available
            if (!empty($data['tracking_link'])) {
                $message .= " Track live: {$data['tracking_link']}";
            }

            // Add additional info if provided
            if (!empty($data['route_name'])) {
                $message .= " Route: {$data['route_name']}";
            }
        }

        return $this->send($to, $message);
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
        $message = "Bus Delay: Route {$tripData['route_name']} delayed by {$tripData['delay_minutes']} min. Reason: {$tripData['delay_reason']}";
        return $this->send($to, $message);
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
        $message = "URGENT: {$alertData['title']} - {$alertData['description']}. Contact school immediately.";
        return $this->send($to, $message);
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
        $message = "{$studentData['student_name']} checked in to bus at {$studentData['time']}.";
        return $this->send($to, $message);
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
        $message = "{$studentData['student_name']} arrived safely at school at {$studentData['time']}.";
        return $this->send($to, $message);
    }

    /**
     * Send bulk SMS notifications
     *
     * @param array $recipients Array of ['phone' => $phone, 'message' => $message]
     * @return array ['success' => int, 'failed' => int]
     */
    public function sendBulk(array $recipients): array
    {
        $success = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $result = $this->send($recipient['phone'], $recipient['message']);

            if ($result) {
                $success++;
            } else {
                $failed++;
            }

            // Add small delay to avoid rate limiting
            usleep(100000); // 100ms delay
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Validate phone number format
     *
     * @param string $phone
     * @return bool
     */
    public function isValidPhoneNumber(string $phone): bool
    {
        // Check if number is in E.164 format: +[country code][number]
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone) === 1;
    }

    /**
     * Format phone number to E.164
     *
     * @param string $phone
     * @param string $defaultCountryCode Default +1 for US
     * @return string
     */
    public function formatPhoneNumber(string $phone, string $defaultCountryCode = '+1'): string
    {
        // Remove all non-digit characters
        $cleaned = preg_replace('/\D/', '', $phone);

        // If already starts with country code
        if (strpos($phone, '+') === 0) {
            return $phone;
        }

        // Add default country code
        return $defaultCountryCode . $cleaned;
    }
}
