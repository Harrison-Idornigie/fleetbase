import Model, { attr, belongsTo, hasMany } from '@ember-data/model';

export default class BusModel extends Model {
  @attr('string') public_id;
  @attr('string') company_uuid;
  @attr('string') school_uuid;
  @attr('string') vehicle_number;
  @attr('string') license_plate;
  @attr('string') make;
  @attr('string') model_name;
  @attr('number') year;
  @attr('number') capacity;
  @attr('number') wheelchair_capacity;
  @attr('boolean') has_air_conditioning;
  @attr('boolean') has_wifi;
  @attr('boolean') has_gps;
  @attr('boolean') has_security_cameras;
  @attr('string') status;
  @attr('date') last_inspection_date;
  @attr('date') next_inspection_date;
  @attr('number') odometer_reading;
  @attr('string') vin;
  @attr('string') insurance_policy_number;
  @attr('date') insurance_expiry_date;
  @attr('string') registration_number;
  @attr('date') registration_expiry_date;
  @attr('') location;
  @attr('string') notes;
  @attr('') meta;
  @attr('date') created_at;
  @attr('date') updated_at;
  @attr('date') deleted_at;

  // Relationships
  @belongsTo('school', { async: true, inverse: null }) school;
  @hasMany('bus-assignment', { async: true, inverse: 'bus' }) assignments;
  @hasMany('trip', { async: true, inverse: 'bus' }) trips;
  @hasMany('vehicle-inspection', { async: true, inverse: 'bus' }) inspections;
  @hasMany('alert', { async: true, inverse: 'bus' }) alerts;

  // Computed properties
  get displayName() {
    return this.vehicle_number || `${this.make} ${this.model_name}`;
  }

  get isOperational() {
    return this.status === 'operational' || this.status === 'active';
  }

  get needsInspection() {
    if (!this.next_inspection_date) return false;
    const daysUntilInspection = Math.floor((new Date(this.next_inspection_date) - new Date()) / (1000 * 60 * 60 * 24));
    return daysUntilInspection <= 7;
  }

  get totalCapacity() {
    return (this.capacity || 0) + (this.wheelchair_capacity || 0);
  }
}
