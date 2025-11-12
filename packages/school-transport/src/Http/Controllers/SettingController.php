<?php

namespace Fleetbase\SchoolTransport\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Models\Setting;
use Illuminate\Http\Request;

/**
 * Class SettingController.
 * Handles School Transport specific settings that extend FleetOps functionality.
 */
class SettingController extends Controller
{
    /**
     * Save school hours settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveSchoolHoursSettings(Request $request)
    {
        $schoolHoursSettings = $request->array('schoolHoursSettings');
        Setting::configureCompany('school-transport.school-hours', $schoolHoursSettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'School hours settings successfully saved.',
        ]);
    }

    /**
     * Get school hours settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSchoolHoursSettings()
    {
        $schoolHoursSettings = Setting::lookupCompany('school-transport.school-hours', [
            'school_start_time' => '08:00',
            'school_end_time' => '15:00',
            'early_pickup_allowed' => true,
            'late_pickup_allowed' => true,
            'max_early_pickup_minutes' => 30,
            'max_late_pickup_minutes' => 60,
        ]);

        return response()->json($schoolHoursSettings);
    }

    /**
     * Save parent portal settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveParentPortalSettings(Request $request)
    {
        $parentPortalSettings = $request->array('parentPortalSettings');
        Setting::configureCompany('school-transport.parent-portal', $parentPortalSettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'Parent portal settings successfully saved.',
        ]);
    }

    /**
     * Get parent portal settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getParentPortalSettings()
    {
        $parentPortalSettings = Setting::lookupCompany('school-transport.parent-portal', [
            'enabled' => true,
            'allow_trip_cancellation' => true,
            'allow_schedule_changes' => false,
            'real_time_tracking' => true,
            'eta_notifications' => true,
            'absence_reporting' => true,
            'emergency_contact_updates' => true,
            'document_access' => true,
            'mobile_app_enabled' => true,
        ]);

        return response()->json($parentPortalSettings);
    }

    /**
     * Save attendance tracking settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveAttendanceTrackingSettings(Request $request)
    {
        $attendanceSettings = $request->array('attendanceSettings');
        Setting::configureCompany('school-transport.attendance-tracking', $attendanceSettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'Attendance tracking settings successfully saved.',
        ]);
    }

    /**
     * Get attendance tracking settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAttendanceTrackingSettings()
    {
        $attendanceSettings = Setting::lookupCompany('school-transport.attendance-tracking', [
            'required_on_boarding' => true,
            'required_on_exit' => true,
            'rfid_scanning' => false,
            'photo_verification' => true,
            'automatic_check_in' => false,
            'geofence_check_in' => true,
            'parent_notifications' => true,
            'school_notifications' => true,
            'tardiness_alerts' => true,
            'absence_followup' => true,
        ]);

        return response()->json($attendanceSettings);
    }

    /**
     * Save safety compliance settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveSafetyComplianceSettings(Request $request)
    {
        $safetySettings = $request->array('safetySettings');
        Setting::configureCompany('school-transport.safety-compliance', $safetySettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'Safety compliance settings successfully saved.',
        ]);
    }

    /**
     * Get safety compliance settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSafetyComplianceSettings()
    {
        $safetySettings = Setting::lookupCompany('school-transport.safety-compliance', [
            'speed_limit_enforcement' => true,
            'route_deviation_alerts' => true,
            'emergency_contact_required' => true,
            'driver_certification_required' => true,
            'vehicle_inspection_required' => true,
            'incident_reporting_required' => true,
            'child_safety_locks' => true,
            'seat_belt_monitoring' => false,
            'emergency_evacuation_drills' => true,
            'stop_sign_arm_enforcement' => true,
        ]);

        return response()->json($safetySettings);
    }

    /**
     * Save emergency contacts settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveEmergencyContactsSettings(Request $request)
    {
        $emergencyContactsSettings = $request->array('emergencyContactsSettings');
        Setting::configureCompany('school-transport.emergency-contacts', $emergencyContactsSettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'Emergency contacts settings successfully saved.',
        ]);
    }

    /**
     * Get emergency contacts settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmergencyContactsSettings()
    {
        $emergencyContactsSettings = Setting::lookupCompany('school-transport.emergency-contacts', [
            'minimum_contacts_required' => 2,
            'maximum_contacts_allowed' => 5,
            'relationship_verification' => true,
            'authorization_levels' => ['pickup', 'medical', 'emergency'],
            'contact_validation_required' => true,
            'automatic_notifications' => true,
            'escalation_procedures' => true,
        ]);

        return response()->json($emergencyContactsSettings);
    }

    /**
     * Save pickup dropoff rules settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function savePickupDropoffRulesSettings(Request $request)
    {
        $pickupDropoffSettings = $request->array('pickupDropoffSettings');
        Setting::configureCompany('school-transport.pickup-dropoff-rules', $pickupDropoffSettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'Pickup/dropoff rules settings successfully saved.',
        ]);
    }

    /**
     * Get pickup dropoff rules settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPickupDropoffRulesSettings()
    {
        $pickupDropoffSettings = Setting::lookupCompany('school-transport.pickup-dropoff-rules', [
            'geofence_required' => true,
            'parent_presence_required' => false,
            'authorized_person_only' => true,
            'id_verification_required' => false,
            'photo_documentation' => true,
            'time_window_enforcement' => true,
            'early_pickup_window_minutes' => 15,
            'late_pickup_grace_minutes' => 10,
            'weather_contingencies' => true,
            'special_needs_accommodations' => true,
        ]);

        return response()->json($pickupDropoffSettings);
    }

    /**
     * Save student permissions settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveStudentPermissionsSettings(Request $request)
    {
        $studentPermissionsSettings = $request->array('studentPermissionsSettings');
        Setting::configureCompany('school-transport.student-permissions', $studentPermissionsSettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'Student permissions settings successfully saved.',
        ]);
    }

    /**
     * Get student permissions settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentPermissionsSettings()
    {
        $studentPermissionsSettings = Setting::lookupCompany('school-transport.student-permissions', [
            'medical_information_access' => 'authorized_only',
            'photo_sharing_consent' => true,
            'location_sharing_consent' => true,
            'emergency_contact_updates' => 'parent_only',
            'route_change_requests' => 'parent_admin_approval',
            'absence_notifications' => true,
            'disciplinary_notifications' => true,
            'data_retention_period' => 365,
            'third_party_sharing' => false,
        ]);

        return response()->json($studentPermissionsSettings);
    }

    /**
     * Save reporting preferences settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveReportingPreferencesSettings(Request $request)
    {
        $reportingSettings = $request->array('reportingSettings');
        Setting::configureCompany('school-transport.reporting-preferences', $reportingSettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'Reporting preferences settings successfully saved.',
        ]);
    }

    /**
     * Get reporting preferences settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReportingPreferencesSettings()
    {
        $reportingSettings = Setting::lookupCompany('school-transport.reporting-preferences', [
            'daily_attendance_reports' => true,
            'weekly_route_summaries' => true,
            'monthly_safety_reports' => true,
            'incident_alerts' => 'immediate',
            'parent_weekly_summaries' => true,
            'performance_metrics' => true,
            'compliance_reporting' => true,
            'automated_report_distribution' => true,
            'custom_report_templates' => true,
            'data_export_formats' => ['pdf', 'excel', 'csv'],
        ]);

        return response()->json($reportingSettings);
    }

    /**
     * Save school transport routing settings (extends FleetOps routing).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveSchoolRoutingSettings(Request $request)
    {
        $routingSettings = $request->array('routingSettings');

        // Save school-specific routing settings
        Setting::configureCompany('school-transport.routing', $routingSettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'School transport routing settings successfully saved.',
        ]);
    }

    /**
     * Get school transport routing settings (extends FleetOps routing).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSchoolRoutingSettings()
    {
        // Get base FleetOps routing settings
        $fleetOpsRouting = Setting::lookupCompany('routing', ['router' => 'osrm', 'unit' => 'km']);

        // Get school-specific routing settings
        $schoolRouting = Setting::lookupCompany('school-transport.routing', [
            'school_zone_speed_limit' => 25,
            'bus_stop_dwell_time' => 2,
            'route_optimization_enabled' => true,
            'traffic_avoidance' => true,
            'weather_routing' => true,
            'accessibility_routing' => true,
            'minimize_walk_distance' => true,
            'consider_grade_levels' => true,
        ]);

        // Merge FleetOps base settings with school-specific extensions
        return response()->json(array_merge($fleetOpsRouting, $schoolRouting));
    }

    /**
     * Save school transport notifications settings (extends FleetOps notifications).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveSchoolNotificationSettings(Request $request)
    {
        $notificationSettings = $request->array('notificationSettings');

        // Save school-specific notification settings
        Setting::configureCompany('school-transport.notifications', $notificationSettings);

        return response()->json([
            'status' => 'ok',
            'message' => 'School transport notification settings successfully saved.',
        ]);
    }

    /**
     * Get school transport notifications settings (extends FleetOps notifications).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSchoolNotificationSettings()
    {
        // Get base FleetOps notification settings
        $fleetOpsNotifications = Setting::lookupCompany('notification_settings', []);

        // Get school-specific notification settings
        $schoolNotifications = Setting::lookupCompany('school-transport.notifications', [
            'parent_eta_notifications' => true,
            'parent_delay_notifications' => true,
            'parent_route_change_notifications' => true,
            'parent_absence_confirmations' => true,
            'school_attendance_notifications' => true,
            'school_incident_notifications' => true,
            'driver_student_pickup_notifications' => true,
            'driver_route_update_notifications' => true,
            'emergency_alert_escalation' => true,
            'weather_delay_notifications' => true,
            'maintenance_reminder_notifications' => true,
            'safety_compliance_alerts' => true,
        ]);

        // Merge FleetOps base settings with school-specific extensions
        return response()->json([
            'fleetOpsSettings' => $fleetOpsNotifications,
            'schoolSpecificSettings' => $schoolNotifications
        ]);
    }
}
