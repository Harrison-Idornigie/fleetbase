import Model, { attr, hasMany } from '@ember-data/model';

export default class ParentGuardianModel extends Model {
  @attr('string') public_id;
  @attr('string') company_uuid;
  @attr('string') user_uuid;
  @attr('string') first_name;
  @attr('string') last_name;
  @attr('string') email;
  @attr('string') phone;
  @attr('string') secondary_phone;
  @attr('string') relationship;
  @attr('string') address;
  @attr('string') city;
  @attr('string') state;
  @attr('string') postal_code;
  @attr('string') country;
  @attr('string') preferred_contact_method;
  @attr('boolean') is_emergency_contact;
  @attr('boolean') is_primary_contact;
  @attr('boolean') can_pickup;
  @attr('') notification_preferences;
  @attr('string') language_preference;
  @attr('string') status;
  @attr('string') notes;
  @attr('') meta;
  @attr('date') created_at;
  @attr('date') updated_at;
  @attr('date') deleted_at;

  // Relationships
  @hasMany('student', { async: true, inverse: 'parents' }) students;
  @hasMany('communication', { async: true, inverse: null }) communications;

  // Computed properties
  get fullName() {
    return `${this.first_name} ${this.last_name}`.trim();
  }

  get displayName() {
    return `${this.fullName} (${this.relationship || 'Parent'})`;
  }

  get fullAddress() {
    const parts = [this.address, this.city, this.state, this.postal_code].filter(Boolean);
    return parts.join(', ');
  }

  get isActive() {
    return this.status === 'active';
  }

  get primaryContact() {
    return this.phone || this.email;
  }

  get hasEmailNotifications() {
    if (!this.notification_preferences) return false;
    return this.notification_preferences.email === true;
  }

  get hasSmsNotifications() {
    if (!this.notification_preferences) return false;
    return this.notification_preferences.sms === true;
  }

  get hasPushNotifications() {
    if (!this.notification_preferences) return false;
    return this.notification_preferences.push === true;
  }

  get contactMethods() {
    const methods = [];
    if (this.email) methods.push({ type: 'email', value: this.email });
    if (this.phone) methods.push({ type: 'phone', value: this.phone });
    if (this.secondary_phone) methods.push({ type: 'phone', value: this.secondary_phone });
    return methods;
  }
}
