import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class AssignmentFormComponent extends Component {
  @service schoolTransportApi;
  @service notifications;

  @tracked conflicts = [];
  @tracked hasConflicts = false;

  statusOptions = [
    'Active',
    'Inactive',
    'Pending',
    'Suspended',
    'Expired'
  ];

  daysOfWeek = [
    { value: 'monday', label: 'Monday' },
    { value: 'tuesday', label: 'Tuesday' },
    { value: 'wednesday', label: 'Wednesday' },
    { value: 'thursday', label: 'Thursday' },
    { value: 'friday', label: 'Friday' },
    { value: 'saturday', label: 'Saturday' },
    { value: 'sunday', label: 'Sunday' }
  ];

  get morningStops() {
    if (!this.args.assignment.morning_route_id) {
      return [];
    }

    const route = this.args.routes.find(r => r.id === this.args.assignment.morning_route_id);
    return route?.stops || [];
  }

  get afternoonStops() {
    if (!this.args.assignment.afternoon_route_id) {
      return [];
    }

    const route = this.args.routes.find(r => r.id === this.args.assignment.afternoon_route_id);
    return route?.stops || [];
  }

  @action
  toggleMorningRoute() {
    const enabled = !this.args.assignment.morning_route_enabled;
    this.args.onChange('morning_route_enabled', enabled);
    
    if (!enabled) {
      // Clear morning route fields
      this.args.onChange('morning_route_id', null);
      this.args.onChange('morning_stop_id', null);
      this.args.onChange('morning_pickup_time', null);
      this.args.onChange('morning_instructions', '');
    }
  }

  @action
  toggleAfternoonRoute() {
    const enabled = !this.args.assignment.afternoon_route_enabled;
    this.args.onChange('afternoon_route_enabled', enabled);
    
    if (!enabled) {
      // Clear afternoon route fields
      this.args.onChange('afternoon_route_id', null);
      this.args.onChange('afternoon_stop_id', null);
      this.args.onChange('afternoon_dropoff_time', null);
      this.args.onChange('afternoon_instructions', '');
    }
  }

  @action
  toggleDay(day) {
    const days = this.args.assignment.days_of_week || [];
    const index = days.indexOf(day);
    
    if (index > -1) {
      days.splice(index, 1);
    } else {
      days.push(day);
    }

    this.args.onChange('days_of_week', days);
    this.checkConflicts();
  }

  @action
  async checkConflicts() {
    if (!this.args.assignment.student_id) {
      return;
    }

    try {
      const conflicts = await this.schoolTransportApi.checkAssignmentConflicts(
        this.args.assignment.student_id,
        {
          morning_route_id: this.args.assignment.morning_route_id,
          afternoon_route_id: this.args.assignment.afternoon_route_id,
          days_of_week: this.args.assignment.days_of_week,
          effective_date: this.args.assignment.effective_date,
          expiry_date: this.args.assignment.expiry_date
        }
      );

      this.conflicts = conflicts;
      this.hasConflicts = conflicts.length > 0;

      if (this.hasConflicts) {
        this.notifications.warning('Assignment conflicts detected. Please review.');
      }
    } catch (error) {
      console.error('Error checking conflicts:', error);
    }
  }
}
