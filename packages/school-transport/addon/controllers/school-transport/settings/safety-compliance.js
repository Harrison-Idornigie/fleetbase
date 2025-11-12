import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsSafetyComplianceController extends Controller {
    @service fetch;
    @service notifications;
    
    @tracked speedLimitEnforcement = true;
    @tracked routeDeviationAlerts = true;
    @tracked emergencyContactRequired = true;
    @tracked driverCertificationRequired = true;
    @tracked vehicleInspectionRequired = true;
    @tracked incidentReportingRequired = true;
    @tracked childSafetyLocks = true;
    @tracked seatBeltMonitoring = false;
    @tracked emergencyEvacuationDrills = true;
    @tracked stopSignArmEnforcement = true;

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    @task *saveSettings() {
        try {
            yield this.fetch.post('school-transport/settings/safety-compliance-settings', {
                safetySettings: {
                    speed_limit_enforcement: this.speedLimitEnforcement,
                    route_deviation_alerts: this.routeDeviationAlerts,
                    emergency_contact_required: this.emergencyContactRequired,
                    driver_certification_required: this.driverCertificationRequired,
                    vehicle_inspection_required: this.vehicleInspectionRequired,
                    incident_reporting_required: this.incidentReportingRequired,
                    child_safety_locks: this.childSafetyLocks,
                    seat_belt_monitoring: this.seatBeltMonitoring,
                    emergency_evacuation_drills: this.emergencyEvacuationDrills,
                    stop_sign_arm_enforcement: this.stopSignArmEnforcement,
                }
            });

            this.notifications.success('Safety compliance settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *getSettings() {
        try {
            const settings = yield this.fetch.get('school-transport/settings/safety-compliance-settings');
            
            this.speedLimitEnforcement = settings.speed_limit_enforcement !== false;
            this.routeDeviationAlerts = settings.route_deviation_alerts !== false;
            this.emergencyContactRequired = settings.emergency_contact_required !== false;
            this.driverCertificationRequired = settings.driver_certification_required !== false;
            this.vehicleInspectionRequired = settings.vehicle_inspection_required !== false;
            this.incidentReportingRequired = settings.incident_reporting_required !== false;
            this.childSafetyLocks = settings.child_safety_locks !== false;
            this.seatBeltMonitoring = settings.seat_belt_monitoring === true;
            this.emergencyEvacuationDrills = settings.emergency_evacuation_drills !== false;
            this.stopSignArmEnforcement = settings.stop_sign_arm_enforcement !== false;
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}