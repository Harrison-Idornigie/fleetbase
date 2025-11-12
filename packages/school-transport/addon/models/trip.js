import Model, { attr, belongsTo, hasMany } from '@ember-data/model';

export default class TripModel extends Model {
  @attr('string') public_id;
  @attr('string') company_uuid;
  @attr('string') route_uuid;
  @attr('string') bus_uuid;
  @attr('string') driver_uuid;
  @attr('string') trip_type;
  @attr('date') scheduled_start_time;
  @attr('date') scheduled_end_time;
  @attr('date') actual_start_time;
  @attr('date') actual_end_time;
  @attr('string') status;
  @attr('number') total_students;
  @attr('number') students_checked_in;
  @attr('number') students_checked_out;
  @attr('number') total_stops;
  @attr('number') completed_stops;
  @attr('') start_location;
  @attr('') end_location;
  @attr('') current_location;
  @attr('number') distance_traveled;
  @attr('string') weather_conditions;
  @attr('string') traffic_conditions;
  @attr('string') delay_reason;
  @attr('number') delay_minutes;
  @attr('string') notes;
  @attr('') meta;
  @attr('date') created_at;
  @attr('date') updated_at;
  @attr('date') deleted_at;

  // Relationships
  @belongsTo('school-route', { async: true, inverse: 'trips' }) route;
  @belongsTo('bus', { async: true, inverse: 'trips' }) bus;
  @belongsTo('driver', { async: true, inverse: 'trips' }) driver;
  @hasMany('tracking-log', { async: true, inverse: 'trip' }) trackingLogs;
  @hasMany('alert', { async: true, inverse: 'trip' }) alerts;

  // Computed properties
  get isInProgress() {
    return this.status === 'in_progress' || this.status === 'started';
  }

  get isCompleted() {
    return this.status === 'completed';
  }

  get isCancelled() {
    return this.status === 'cancelled';
  }

  get isDelayed() {
    return this.delay_minutes && this.delay_minutes > 0;
  }

  get completionPercentage() {
    if (!this.total_stops || this.total_stops === 0) return 0;
    return Math.round((this.completed_stops / this.total_stops) * 100);
  }

  get studentCheckInPercentage() {
    if (!this.total_students || this.total_students === 0) return 0;
    return Math.round((this.students_checked_in / this.total_students) * 100);
  }

  get duration() {
    if (!this.actual_start_time || !this.actual_end_time) return null;
    const diff = new Date(this.actual_end_time) - new Date(this.actual_start_time);
    return Math.round(diff / (1000 * 60)); // minutes
  }
}
