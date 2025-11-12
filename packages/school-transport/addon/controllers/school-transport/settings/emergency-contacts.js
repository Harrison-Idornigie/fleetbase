import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsEmergencyContactsController extends Controller {
    @service fetch;
    @service notifications;
    
    @tracked minimumContactsRequired = 2;
    @tracked maximumContactsAllowed = 5;
    @tracked relationshipVerification = true;
    @tracked authorizationLevels = ['pickup', 'medical', 'emergency'];
    @tracked contactValidationRequired = true;
    @tracked automaticNotifications = true;
    @tracked escalationProcedures = true;
    
    @tracked authorizationOptions = [
        { label: 'Pickup Authorization', value: 'pickup', selected: true },
        { label: 'Medical Authorization', value: 'medical', selected: true },
        { label: 'Emergency Authorization', value: 'emergency', selected: true },
        { label: 'Administrative Authorization', value: 'admin', selected: false },
    ];

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    @task *saveSettings() {
        try {
            const selectedAuthLevels = this.authorizationOptions.filter(opt => opt.selected).map(opt => opt.value);
            
            yield this.fetch.post('school-transport/settings/emergency-contacts-settings', {
                emergencyContactsSettings: {
                    minimum_contacts_required: this.minimumContactsRequired,
                    maximum_contacts_allowed: this.maximumContactsAllowed,
                    relationship_verification: this.relationshipVerification,
                    authorization_levels: selectedAuthLevels,
                    contact_validation_required: this.contactValidationRequired,
                    automatic_notifications: this.automaticNotifications,
                    escalation_procedures: this.escalationProcedures,
                }
            });

            this.notifications.success('Emergency contacts settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *getSettings() {
        try {
            const settings = yield this.fetch.get('school-transport/settings/emergency-contacts-settings');
            
            this.minimumContactsRequired = settings.minimum_contacts_required || 2;
            this.maximumContactsAllowed = settings.maximum_contacts_allowed || 5;
            this.relationshipVerification = settings.relationship_verification !== false;
            this.authorizationLevels = settings.authorization_levels || ['pickup', 'medical', 'emergency'];
            this.contactValidationRequired = settings.contact_validation_required !== false;
            this.automaticNotifications = settings.automatic_notifications !== false;
            this.escalationProcedures = settings.escalation_procedures !== false;
            
            // Update authorization options based on saved settings
            this.authorizationOptions.forEach(option => {
                option.selected = this.authorizationLevels.includes(option.value);
            });
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}