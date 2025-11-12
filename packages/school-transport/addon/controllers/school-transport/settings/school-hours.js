import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsSchoolHoursController extends Controller {
    @service fetch;
    @service notifications;
    
    @tracked schoolStartTime = '08:00';
    @tracked schoolEndTime = '15:00';
    @tracked earlyPickupAllowed = true;
    @tracked latePickupAllowed = true;
    @tracked maxEarlyPickupMinutes = 30;
    @tracked maxLatePickupMinutes = 60;
    
    @tracked timeOptions = [
        { label: '6:00 AM', value: '06:00' },
        { label: '6:30 AM', value: '06:30' },
        { label: '7:00 AM', value: '07:00' },
        { label: '7:30 AM', value: '07:30' },
        { label: '8:00 AM', value: '08:00' },
        { label: '8:30 AM', value: '08:30' },
        { label: '9:00 AM', value: '09:00' },
        { label: '2:00 PM', value: '14:00' },
        { label: '2:30 PM', value: '14:30' },
        { label: '3:00 PM', value: '15:00' },
        { label: '3:30 PM', value: '15:30' },
        { label: '4:00 PM', value: '16:00' },
        { label: '4:30 PM', value: '16:30' },
        { label: '5:00 PM', value: '17:00' },
    ];

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    @task *saveSettings() {
        try {
            yield this.fetch.post('school-transport/settings/school-hours-settings', {
                schoolHoursSettings: {
                    school_start_time: this.schoolStartTime,
                    school_end_time: this.schoolEndTime,
                    early_pickup_allowed: this.earlyPickupAllowed,
                    late_pickup_allowed: this.latePickupAllowed,
                    max_early_pickup_minutes: this.maxEarlyPickupMinutes,
                    max_late_pickup_minutes: this.maxLatePickupMinutes,
                }
            });

            this.notifications.success('School hours settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *getSettings() {
        try {
            const settings = yield this.fetch.get('school-transport/settings/school-hours-settings');
            
            this.schoolStartTime = settings.school_start_time || '08:00';
            this.schoolEndTime = settings.school_end_time || '15:00';
            this.earlyPickupAllowed = settings.early_pickup_allowed !== false;
            this.latePickupAllowed = settings.late_pickup_allowed !== false;
            this.maxEarlyPickupMinutes = settings.max_early_pickup_minutes || 30;
            this.maxLatePickupMinutes = settings.max_late_pickup_minutes || 60;
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}