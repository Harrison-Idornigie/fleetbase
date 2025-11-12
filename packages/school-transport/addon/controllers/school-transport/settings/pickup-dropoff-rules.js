import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsPickupDropoffRulesController extends Controller {
    @service fetch;
    @service notifications;
    
    @tracked geofenceRequired = true;
    @tracked parentPresenceRequired = false;
    @tracked authorizedPersonOnly = true;
    @tracked idVerificationRequired = false;
    @tracked photoDocumentation = true;
    @tracked timeWindowEnforcement = true;
    @tracked earlyPickupWindowMinutes = 15;
    @tracked latePickupGraceMinutes = 10;
    @tracked weatherContingencies = true;
    @tracked specialNeedsAccommodations = true;

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    @task *saveSettings() {
        try {
            yield this.fetch.post('school-transport/settings/pickup-dropoff-rules-settings', {
                pickupDropoffSettings: {
                    geofence_required: this.geofenceRequired,
                    parent_presence_required: this.parentPresenceRequired,
                    authorized_person_only: this.authorizedPersonOnly,
                    id_verification_required: this.idVerificationRequired,
                    photo_documentation: this.photoDocumentation,
                    time_window_enforcement: this.timeWindowEnforcement,
                    early_pickup_window_minutes: this.earlyPickupWindowMinutes,
                    late_pickup_grace_minutes: this.latePickupGraceMinutes,
                    weather_contingencies: this.weatherContingencies,
                    special_needs_accommodations: this.specialNeedsAccommodations,
                }
            });

            this.notifications.success('Pickup/dropoff rules settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *getSettings() {
        try {
            const settings = yield this.fetch.get('school-transport/settings/pickup-dropoff-rules-settings');
            
            this.geofenceRequired = settings.geofence_required !== false;
            this.parentPresenceRequired = settings.parent_presence_required === true;
            this.authorizedPersonOnly = settings.authorized_person_only !== false;
            this.idVerificationRequired = settings.id_verification_required === true;
            this.photoDocumentation = settings.photo_documentation !== false;
            this.timeWindowEnforcement = settings.time_window_enforcement !== false;
            this.earlyPickupWindowMinutes = settings.early_pickup_window_minutes || 15;
            this.latePickupGraceMinutes = settings.late_pickup_grace_minutes || 10;
            this.weatherContingencies = settings.weather_contingencies !== false;
            this.specialNeedsAccommodations = settings.special_needs_accommodations !== false;
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}