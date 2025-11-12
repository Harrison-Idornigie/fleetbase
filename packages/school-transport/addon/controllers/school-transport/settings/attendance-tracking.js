import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsAttendanceTrackingController extends Controller {
    @service fetch;
    @service notifications;
    
    @tracked requiredOnBoarding = true;
    @tracked requiredOnExit = true;
    @tracked rfidScanning = false;
    @tracked photoVerification = true;
    @tracked automaticCheckIn = false;
    @tracked geofenceCheckIn = true;
    @tracked parentNotifications = true;
    @tracked schoolNotifications = true;
    @tracked tardinessAlerts = true;
    @tracked absenceFollowup = true;

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    @task *saveSettings() {
        try {
            yield this.fetch.post('school-transport/settings/attendance-tracking-settings', {
                attendanceSettings: {
                    required_on_boarding: this.requiredOnBoarding,
                    required_on_exit: this.requiredOnExit,
                    rfid_scanning: this.rfidScanning,
                    photo_verification: this.photoVerification,
                    automatic_check_in: this.automaticCheckIn,
                    geofence_check_in: this.geofenceCheckIn,
                    parent_notifications: this.parentNotifications,
                    school_notifications: this.schoolNotifications,
                    tardiness_alerts: this.tardinessAlerts,
                    absence_followup: this.absenceFollowup,
                }
            });

            this.notifications.success('Attendance tracking settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *getSettings() {
        try {
            const settings = yield this.fetch.get('school-transport/settings/attendance-tracking-settings');
            
            this.requiredOnBoarding = settings.required_on_boarding !== false;
            this.requiredOnExit = settings.required_on_exit !== false;
            this.rfidScanning = settings.rfid_scanning === true;
            this.photoVerification = settings.photo_verification !== false;
            this.automaticCheckIn = settings.automatic_check_in === true;
            this.geofenceCheckIn = settings.geofence_check_in !== false;
            this.parentNotifications = settings.parent_notifications !== false;
            this.schoolNotifications = settings.school_notifications !== false;
            this.tardinessAlerts = settings.tardiness_alerts !== false;
            this.absenceFollowup = settings.absence_followup !== false;
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}