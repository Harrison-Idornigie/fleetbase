import Model, { attr, belongsTo, hasMany } from '@ember-data/model';

export default class DriverModel extends Model {
  @attr('string') public_id;
  @attr('string') company_uuid;
  @attr('string') user_uuid;
  @attr('string') first_name;
  @attr('string') last_name;
  @attr('string') email;
  @attr('string') phone;
  @attr('string') license_number;
  @attr('string') license_type;
  @attr('date') license_expiry_date;
  @attr('string') employee_id;
  @attr('date') hire_date;
  @attr('string') status;
  @attr('') location;
  @attr('string') emergency_contact_name;
  @attr('string') emergency_contact_phone;
  @attr('string') emergency_contact_relationship;
  @attr('string') address;
  @attr('string') city;
  @attr('string') state;
  @attr('string') postal_code;
  @attr('string') country;
  @attr('string') notes;
  @attr('') meta;
  @attr('date') created_at;
  @attr('date') updated_at;
  @attr('date') deleted_at;

  // Relationships
  @hasMany('bus-assignment', { async: true, inverse: 'driver' }) assignments;
  @hasMany('trip', { async: true, inverse: 'driver' }) trips;
  @hasMany('driver-certification', { async: true, inverse: 'driver' }) certifications;
  @hasMany('alert', { async: true, inverse: 'driver' }) alerts;

  // Computed properties
  get fullName() {
    return `${this.first_name} ${this.last_name}`.trim();
  }

  get licenseExpired() {
    if (!this.license_expiry_date) return false;
    return new Date(this.license_expiry_date) < new Date();
  }

  get licenseExpiringSoon() {
    if (!this.license_expiry_date) return false;
    const daysUntilExpiry = Math.floor((new Date(this.license_expiry_date) - new Date()) / (1000 * 60 * 60 * 24));
    return daysUntilExpiry <= 30 && daysUntilExpiry > 0;
  }

  get isActive() {
    return this.status === 'active' || this.status === 'on_duty';
  }

  get isAvailable() {
    return this.status === 'available';
  }
}
