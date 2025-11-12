import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsStudentPermissionsController extends Controller {
    @service fetch;
    @service notifications;
    
    @tracked medicalInformationAccess = 'authorized_only';
    @tracked photoSharingConsent = true;
    @tracked locationSharingConsent = true;
    @tracked emergencyContactUpdates = 'parent_only';
    @tracked routeChangeRequests = 'parent_admin_approval';
    @tracked absenceNotifications = true;
    @tracked disciplinaryNotifications = true;
    @tracked dataRetentionPeriod = 365;
    @tracked thirdPartySharing = false;
    
    @tracked accessLevelOptions = [
        { label: 'Public', value: 'public' },
        { label: 'Authorized Only', value: 'authorized_only' },
        { label: 'Admin Only', value: 'admin_only' },
        { label: 'Parent Only', value: 'parent_only' },
    ];
    
    @tracked approvalOptions = [
        { label: 'Automatic', value: 'automatic' },
        { label: 'Parent Approval', value: 'parent_approval' },
        { label: 'Admin Approval', value: 'admin_approval' },
        { label: 'Parent + Admin Approval', value: 'parent_admin_approval' },
    ];
    
    @tracked retentionOptions = [
        { label: '90 days', value: 90 },
        { label: '180 days', value: 180 },
        { label: '1 year', value: 365 },
        { label: '2 years', value: 730 },
        { label: '3 years', value: 1095 },
    ];

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    @task *saveSettings() {
        try {
            yield this.fetch.post('school-transport/settings/student-permissions-settings', {
                studentPermissionsSettings: {
                    medical_information_access: this.medicalInformationAccess,
                    photo_sharing_consent: this.photoSharingConsent,
                    location_sharing_consent: this.locationSharingConsent,
                    emergency_contact_updates: this.emergencyContactUpdates,
                    route_change_requests: this.routeChangeRequests,
                    absence_notifications: this.absenceNotifications,
                    disciplinary_notifications: this.disciplinaryNotifications,
                    data_retention_period: this.dataRetentionPeriod,
                    third_party_sharing: this.thirdPartySharing,
                }
            });

            this.notifications.success('Student permissions settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *getSettings() {
        try {
            const settings = yield this.fetch.get('school-transport/settings/student-permissions-settings');
            
            this.medicalInformationAccess = settings.medical_information_access || 'authorized_only';
            this.photoSharingConsent = settings.photo_sharing_consent !== false;
            this.locationSharingConsent = settings.location_sharing_consent !== false;
            this.emergencyContactUpdates = settings.emergency_contact_updates || 'parent_only';
            this.routeChangeRequests = settings.route_change_requests || 'parent_admin_approval';
            this.absenceNotifications = settings.absence_notifications !== false;
            this.disciplinaryNotifications = settings.disciplinary_notifications !== false;
            this.dataRetentionPeriod = settings.data_retention_period || 365;
            this.thirdPartySharing = settings.third_party_sharing === true;
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}