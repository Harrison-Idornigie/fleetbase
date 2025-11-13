<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Communication;
use Fleetbase\SchoolTransportEngine\Models\Student;
use Fleetbase\SchoolTransportEngine\Models\SchoolRoute;
use Illuminate\Support\Facades\Log;

class CommunicationService
{
    /**
     * Get all communications with optional filtering
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCommunications(array $filters = [])
    {
        $query = Communication::where('company_uuid', session('company'));

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['recipient_type'])) {
            $query->where('recipient_type', $filters['recipient_type']);
        }

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('sent_at', [$filters['date_from'], $filters['date_to']]);
        }

        return $query->with(['route', 'student'])->latest('sent_at')->get();
    }

    /**
     * Send a communication
     *
     * @param array $data
     * @return Communication
     */
    public function sendCommunication(array $data): Communication
    {
        try {
            $data['company_uuid'] = $data['company_uuid'] ?? session('company');
            $data['sent_by_uuid'] = $data['sent_by_uuid'] ?? auth()->id();
            $data['sent_at'] = now();
            $data['status'] = 'sent';

            $communication = Communication::create($data);

            // Trigger actual sending based on type
            $this->deliverCommunication($communication);

            return $communication;
        } catch (\Exception $e) {
            Log::error('Failed to send communication: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send bulk communications
     *
     * @param array $data
     * @param array $recipients
     * @return array
     */
    public function sendBulkCommunications(array $data, array $recipients): array
    {
        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($recipients as $recipient) {
            try {
                $communicationData = array_merge($data, [
                    'recipient_uuid' => $recipient['uuid'],
                    'recipient_type' => $recipient['type'],
                    'recipient_contact' => $recipient['contact'] ?? null,
                ]);

                $this->sendCommunication($communicationData);
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Get communications for a student
     *
     * @param string $studentUuid
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStudentCommunications(string $studentUuid)
    {
        return Communication::where('company_uuid', session('company'))
            ->where(function ($query) use ($studentUuid) {
                $query->where('student_uuid', $studentUuid)
                    ->orWhere(function ($q) use ($studentUuid) {
                        // Get communications for the student's guardians
                        $student = Student::find($studentUuid);
                        if ($student) {
                            $guardianUuids = $student->guardians()->pluck('uuid')->toArray();
                            $q->whereIn('recipient_uuid', $guardianUuids);
                        }
                    });
            })
            ->latest('sent_at')
            ->get();
    }

    /**
     * Get communications for a route
     *
     * @param string $routeUuid
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRouteCommunications(string $routeUuid)
    {
        return Communication::where('company_uuid', session('company'))
            ->where('route_uuid', $routeUuid)
            ->latest('sent_at')
            ->get();
    }

    /**
     * Mark communication as delivered
     *
     * @param string $uuid
     * @return Communication
     */
    public function markAsDelivered(string $uuid): Communication
    {
        $communication = Communication::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $communication->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        return $communication;
    }

    /**
     * Mark communication as read
     *
     * @param string $uuid
     * @return Communication
     */
    public function markAsRead(string $uuid): Communication
    {
        $communication = Communication::where('company_uuid', session('company'))
            ->where('uuid', $uuid)
            ->firstOrFail();

        $communication->update([
            'status' => 'read',
            'read_at' => now(),
        ]);

        return $communication;
    }

    /**
     * Get communication statistics
     *
     * @param array $filters
     * @return array
     */
    public function getCommunicationStatistics(array $filters = []): array
    {
        $query = Communication::where('company_uuid', session('company'));

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('sent_at', [$filters['date_from'], $filters['date_to']]);
        }

        $communications = $query->get();

        return [
            'total_sent' => $communications->count(),
            'by_type' => $communications->groupBy('type')->map->count(),
            'by_priority' => $communications->groupBy('priority')->map->count(),
            'by_status' => $communications->groupBy('status')->map->count(),
            'delivered' => $communications->where('status', 'delivered')->count(),
            'read' => $communications->where('status', 'read')->count(),
            'failed' => $communications->where('status', 'failed')->count(),
            'delivery_rate' => $communications->count() > 0
                ? round(($communications->whereIn('status', ['delivered', 'read'])->count() / $communications->count()) * 100, 2)
                : 0,
        ];
    }

    /**
     * Deliver communication based on type
     *
     * @param Communication $communication
     * @return void
     */
    protected function deliverCommunication(Communication $communication): void
    {
        try {
            switch ($communication->type) {
                case 'email':
                    app(EmailNotificationService::class)->send(
                        $communication->recipient_contact,
                        $communication->subject,
                        $communication->message
                    );
                    break;

                case 'sms':
                    app(SmsNotificationService::class)->send(
                        $communication->recipient_contact,
                        $communication->message
                    );
                    break;

                case 'push':
                    // Would integrate with push notification service
                    break;

                case 'in_app':
                    // Already stored in database, no external delivery needed
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Failed to deliver communication: ' . $e->getMessage());
            $communication->update(['status' => 'failed']);
        }
    }
}
