import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsNotificationsController extends Controller {
    @service fetch;
    @service notifications;
    @service currentUser;
    
    // Base FleetOps notification settings will be loaded
    @tracked fleetOpsSettings = {};
    
    // School-specific notification settings
    @tracked parentEtaNotifications = true;
    @tracked parentDelayNotifications = true;
    @tracked parentRouteChangeNotifications = true;
    @tracked parentAbsenceConfirmations = true;
    @tracked schoolAttendanceNotifications = true;
    @tracked schoolIncidentNotifications = true;
    @tracked driverStudentPickupNotifications = true;
    @tracked driverRouteUpdateNotifications = true;
    @tracked emergencyAlertEscalation = true;
    @tracked weatherDelayNotifications = true;
    @tracked maintenanceReminderNotifications = true;
    @tracked safetyComplianceAlerts = true;
    
    // Notification method options
    @tracked notificationMethods = [
        { label: 'Push Notification', value: 'push', enabled: true },
        { label: 'SMS', value: 'sms', enabled: true },
        { label: 'Email', value: 'email', enabled: true },
        { label: 'In-App', value: 'in_app', enabled: true },
    ];

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    /**
     * Save school transport notification settings.
     * Extends FleetOps notifications with school-specific options.
     *
     * @memberof SchoolTransportSettingsNotificationsController
     */
    @task *saveSettings() {
        try {
            // Save school-specific notification settings
            yield this.fetch.post('school-transport/settings/notification-settings', { 
                notificationSettings: {
                    parent_eta_notifications: this.parentEtaNotifications,
                    parent_delay_notifications: this.parentDelayNotifications,
                    parent_route_change_notifications: this.parentRouteChangeNotifications,
                    parent_absence_confirmations: this.parentAbsenceConfirmations,
                    school_attendance_notifications: this.schoolAttendanceNotifications,
                    school_incident_notifications: this.schoolIncidentNotifications,
                    driver_student_pickup_notifications: this.driverStudentPickupNotifications,
                    driver_route_update_notifications: this.driverRouteUpdateNotifications,
                    emergency_alert_escalation: this.emergencyAlertEscalation,
                    weather_delay_notifications: this.weatherDelayNotifications,
                    maintenance_reminder_notifications: this.maintenanceReminderNotifications,
                    safety_compliance_alerts: this.safetyComplianceAlerts,
                }
            });

            this.notifications.success('School transport notification settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    /**
     * Get school transport notification settings.
     *
     * @memberof SchoolTransportSettingsNotificationsController
     */
    @task *getSettings() {
        try {
            const response = yield this.fetch.get('school-transport/settings/notification-settings');
            
            // Set base FleetOps settings
            this.fleetOpsSettings = response.fleetOpsSettings || {};
            
            // Set school-specific settings
            const schoolSettings = response.schoolSpecificSettings || {};
            this.parentEtaNotifications = schoolSettings.parent_eta_notifications !== false;
            this.parentDelayNotifications = schoolSettings.parent_delay_notifications !== false;
            this.parentRouteChangeNotifications = schoolSettings.parent_route_change_notifications !== false;
            this.parentAbsenceConfirmations = schoolSettings.parent_absence_confirmations !== false;
            this.schoolAttendanceNotifications = schoolSettings.school_attendance_notifications !== false;
            this.schoolIncidentNotifications = schoolSettings.school_incident_notifications !== false;
            this.driverStudentPickupNotifications = schoolSettings.driver_student_pickup_notifications !== false;
            this.driverRouteUpdateNotifications = schoolSettings.driver_route_update_notifications !== false;
            this.emergencyAlertEscalation = schoolSettings.emergency_alert_escalation !== false;
            this.weatherDelayNotifications = schoolSettings.weather_delay_notifications !== false;
            this.maintenanceReminderNotifications = schoolSettings.maintenance_reminder_notifications !== false;
            this.safetyComplianceAlerts = schoolSettings.safety_compliance_alerts !== false;
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}