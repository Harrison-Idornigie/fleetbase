import Model, { attr, hasMany } from '@ember-data/model';
import { computed } from '@ember/object';

export default class SchoolRouteModel extends Model {
  /** @ids */
  @attr('string') public_id;

  /** @attributes */
  @attr('string') route_name;
  @attr('string') route_number;
  @attr('string') description;
  @attr('string') school;
  @attr('string') route_type;
  @attr('string') start_time;
  @attr('string') end_time;
  @attr('number') estimated_duration;
  @attr('number') estimated_distance;
  @attr() stops;
  @attr() waypoints;
  @attr('string') vehicle_uuid;
  @attr('string') driver_uuid;
  @attr('number') capacity;
  @attr('boolean') wheelchair_accessible;
  @attr('boolean') is_active;
  @attr('string') status;
  @attr() days_of_week;
  @attr('date') effective_date;
  @attr('date') end_date;
  @attr() special_instructions;
  @attr() meta;

  /** @computed */
  @computed('bus_assignments.@each.status')
  get assigned_students_count() {
    return this.bus_assignments.filter(assignment => assignment.status === 'active').length;
  }

  @computed('capacity', 'assigned_students_count')
  get available_capacity() {
    return Math.max(0, this.capacity - this.assigned_students_count);
  }

  @computed('assigned_students_count', 'capacity')
  get utilization_percentage() {
    if (this.capacity <= 0) return 0;
    return Math.round((this.assigned_students_count / this.capacity) * 100);
  }

  @computed('utilization_percentage')
  get is_overutilized() {
    return this.utilization_percentage > 90;
  }

  @computed('utilization_percentage')
  get is_underutilized() {
    return this.utilization_percentage < 60;
  }

  @computed('estimated_duration')
  get formatted_duration() {
    if (!this.estimated_duration) return 'Unknown';
    
    const hours = Math.floor(this.estimated_duration / 60);
    const minutes = this.estimated_duration % 60;
    
    if (hours > 0) {
      return `${hours}h ${minutes}m`;
    }
    
    return `${minutes}m`;
  }

  @computed('is_active', 'status', 'effective_date', 'end_date')
  get is_current() {
    if (!this.is_active || this.status !== 'active') return false;
    
    const today = new Date().toISOString().split('T')[0];
    
    if (this.effective_date && this.effective_date > today) return false;
    if (this.end_date && this.end_date < today) return false;
    
    return true;
  }

  @computed('days_of_week')
  get operates_today() {
    if (!this.days_of_week || !Array.isArray(this.days_of_week)) return false;
    
    const today = new Date().toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    return this.days_of_week.includes(today);
  }

  @computed('stops')
  get next_stop() {
    if (!this.stops || !Array.isArray(this.stops)) return null;
    
    const now = new Date();
    const currentTime = now.toTimeString().slice(0, 5); // HH:MM format
    
    return this.stops.find(stop => stop.time > currentTime) || null;
  }

  @computed('utilization_percentage', 'estimated_distance')
  get efficiency_score() {
    // Basic efficiency calculation
    const utilizationScore = Math.min(this.utilization_percentage / 80, 1.0); // Target 80% utilization
    const distanceScore = this.estimated_distance ? Math.min(20 / this.estimated_distance, 1.0) : 0.5;
    
    return Math.round(((utilizationScore + distanceScore) / 2) * 100);
  }

  /** @relationships */
  @hasMany('bus-assignment') bus_assignments;
  @hasMany('student', { through: 'bus_assignments' }) students;
  @hasMany('attendance') attendance_records;
  @hasMany('communication') communications;

  /** @methods */
  operatesOnDay(day) {
    if (!this.days_of_week || !Array.isArray(this.days_of_week)) return false;
    return this.days_of_week.includes(day.toLowerCase());
  }

  getActiveStudents() {
    return this.bus_assignments
      .filter(assignment => assignment.status === 'active' && assignment.is_active)
      .map(assignment => assignment.student)
      .filter(Boolean);
  }

  calculateArrivalTime(stopIndex) {
    if (!this.stops || !this.stops[stopIndex] || !this.start_time) return null;
    
    const minutesFromStart = stopIndex * 5; // 5 minutes per stop estimate
    const startTime = new Date(`1970-01-01T${this.start_time}:00`);
    startTime.setMinutes(startTime.getMinutes() + minutesFromStart);
    
    return startTime.toTimeString().slice(0, 5);
  }

  getStopBySequence(sequence) {
    if (!this.stops || !Array.isArray(this.stops)) return null;
    return this.stops.find(stop => stop.sequence === sequence) || null;
  }

  getTotalDistance() {
    // This would calculate total route distance from waypoints
    // For now return estimated_distance or calculate from stops
    return this.estimated_distance || 0;
  }
}