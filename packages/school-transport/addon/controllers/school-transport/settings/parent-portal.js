import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsParentPortalController extends Controller {
    @service fetch;
    @service notifications;
    
    @tracked enabled = true;
    @tracked allowTripCancellation = true;
    @tracked allowScheduleChanges = false;
    @tracked realTimeTracking = true;
    @tracked etaNotifications = true;
    @tracked absenceReporting = true;
    @tracked emergencyContactUpdates = true;
    @tracked documentAccess = true;
    @tracked mobileAppEnabled = true;

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    @task *saveSettings() {
        try {
            yield this.fetch.post('school-transport/settings/parent-portal-settings', {
                parentPortalSettings: {
                    enabled: this.enabled,
                    allow_trip_cancellation: this.allowTripCancellation,
                    allow_schedule_changes: this.allowScheduleChanges,
                    real_time_tracking: this.realTimeTracking,
                    eta_notifications: this.etaNotifications,
                    absence_reporting: this.absenceReporting,
                    emergency_contact_updates: this.emergencyContactUpdates,
                    document_access: this.documentAccess,
                    mobile_app_enabled: this.mobileAppEnabled,
                }
            });

            this.notifications.success('Parent portal settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *getSettings() {
        try {
            const settings = yield this.fetch.get('school-transport/settings/parent-portal-settings');
            
            this.enabled = settings.enabled !== false;
            this.allowTripCancellation = settings.allow_trip_cancellation !== false;
            this.allowScheduleChanges = settings.allow_schedule_changes === true;
            this.realTimeTracking = settings.real_time_tracking !== false;
            this.etaNotifications = settings.eta_notifications !== false;
            this.absenceReporting = settings.absence_reporting !== false;
            this.emergencyContactUpdates = settings.emergency_contact_updates !== false;
            this.documentAccess = settings.document_access !== false;
            this.mobileAppEnabled = settings.mobile_app_enabled !== false;
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}