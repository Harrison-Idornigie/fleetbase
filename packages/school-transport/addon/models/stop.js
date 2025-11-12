import Model, { attr, belongsTo, hasMany } from '@ember-data/model';

export default class StopModel extends Model {
  @attr('string') public_id;
  @attr('string') company_uuid;
  @attr('string') route_uuid;
  @attr('string') name;
  @attr('string') address;
  @attr('string') city;
  @attr('string') state;
  @attr('string') postal_code;
  @attr('string') country;
  @attr('') location;
  @attr('number') latitude;
  @attr('number') longitude;
  @attr('string') stop_type;
  @attr('number') sequence_number;
  @attr('date') scheduled_arrival_time;
  @attr('date') scheduled_departure_time;
  @attr('number') estimated_duration_minutes;
  @attr('number') student_count;
  @attr('boolean') is_pickup;
  @attr('boolean') is_dropoff;
  @attr('boolean') is_accessible;
  @attr('string') special_instructions;
  @attr('string') landmark;
  @attr('string') status;
  @attr('string') notes;
  @attr('') meta;
  @attr('date') created_at;
  @attr('date') updated_at;
  @attr('date') deleted_at;

  // Relationships
  @belongsTo('school-route', { async: true, inverse: 'stops' }) route;
  @hasMany('student', { async: true, inverse: 'stops' }) students;

  // Computed properties
  get coordinates() {
    return {
      lat: this.latitude,
      lng: this.longitude
    };
  }

  get hasValidLocation() {
    return this.latitude && this.longitude;
  }

  get fullAddress() {
    const parts = [this.address, this.city, this.state, this.postal_code].filter(Boolean);
    return parts.join(', ');
  }

  get displayName() {
    return this.name || this.address || `Stop #${this.sequence_number}`;
  }

  get isActive() {
    return this.status === 'active';
  }

  get stopTypeLabel() {
    if (this.is_pickup && this.is_dropoff) return 'Pickup & Dropoff';
    if (this.is_pickup) return 'Pickup Only';
    if (this.is_dropoff) return 'Dropoff Only';
    return 'Unknown';
  }

  get estimatedTimeRange() {
    if (!this.scheduled_arrival_time || !this.estimated_duration_minutes) return null;
    const arrival = new Date(this.scheduled_arrival_time);
    const departure = new Date(arrival.getTime() + this.estimated_duration_minutes * 60000);
    return {
      arrival: arrival.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      departure: departure.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    };
  }
}
