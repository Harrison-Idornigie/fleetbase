import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class RouteFormComponent extends Component {
  @tracked isOptimizing = false;

  routeTypes = [
    'Morning Pickup',
    'Afternoon Dropoff',
    'Field Trip',
    'Special Needs',
    'Charter'
  ];

  statusOptions = [
    'Active',
    'Inactive',
    'Planned',
    'Suspended'
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

  stopTypes = [
    'Pickup',
    'Dropoff',
    'Both'
  ];

  @action
  toggleDay(day) {
    const days = this.args.route.days_of_week || [];
    const index = days.indexOf(day);
    
    if (index > -1) {
      days.splice(index, 1);
    } else {
      days.push(day);
    }

    this.args.onChange('days_of_week', days);
  }

  @action
  addStop() {
    const stops = this.args.route.stops || [];
    stops.push({
      name: '',
      address: '',
      latitude: null,
      longitude: null,
      scheduled_time: '',
      duration: 5,
      stop_type: 'Both',
      sequence: stops.length + 1,
      notes: ''
    });
    this.args.onChange('stops', stops);
  }

  @action
  removeStop(index) {
    const stops = [...this.args.route.stops];
    stops.splice(index, 1);
    
    // Resequence remaining stops
    stops.forEach((stop, idx) => {
      stop.sequence = idx + 1;
    });

    this.args.onChange('stops', stops);
  }

  @action
  moveStopUp(index) {
    if (index === 0) return;
    
    const stops = [...this.args.route.stops];
    [stops[index - 1], stops[index]] = [stops[index], stops[index - 1]];
    
    // Resequence
    stops.forEach((stop, idx) => {
      stop.sequence = idx + 1;
    });

    this.args.onChange('stops', stops);
  }

  @action
  moveStopDown(index) {
    const stops = [...this.args.route.stops];
    if (index >= stops.length - 1) return;
    
    [stops[index], stops[index + 1]] = [stops[index + 1], stops[index]];
    
    // Resequence
    stops.forEach((stop, idx) => {
      stop.sequence = idx + 1;
    });

    this.args.onChange('stops', stops);
  }

  @action
  updateStopType(index, type) {
    const stops = [...this.args.route.stops];
    stops[index].stop_type = type;
    this.args.onChange('stops', stops);
  }

  get totalDuration() {
    const stops = this.args.route.stops || [];
    return stops.reduce((sum, stop) => sum + (stop.duration || 0), 0);
  }

  get totalDistance() {
    // This would be calculated from actual coordinates
    // For now, return placeholder
    return '--';
  }
}
