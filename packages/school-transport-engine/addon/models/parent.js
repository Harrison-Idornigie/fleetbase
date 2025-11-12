import Model, { attr, hasMany } from '@ember-data/model';
import { computed } from '@ember/object';

export default class ParentModel extends Model {
  /** @ids */
  @attr('string') public_id;
  @attr('string') user_uuid;

  /** @attributes */
  @attr('string') first_name;
  @attr('string') last_name;
  @attr('string') email;
  @attr('string') phone;
  @attr('string') alternate_phone;
  @attr('string') address;
  @attr('string') city;
  @attr('string') state;
  @attr('string') zip_code;
  @attr() coordinates;
  @attr('string') relationship; // parent, guardian, foster_parent, etc.
  @attr('boolean') is_primary_contact;
  @attr('boolean') is_emergency_contact;
  @attr('boolean') can_pickup;
  @attr('boolean') receives_notifications;
  @attr() notification_preferences; // email, sms, push preferences
  @attr('string') preferred_language;
  @attr() authorized_pickups; // array of authorized people
  @attr('boolean') is_active;
  @attr() meta;

  /** @timestamps */
  @attr('date') created_at;
  @attr('date') updated_at;

  /** @computed */
  @computed('first_name', 'last_name')
  get full_name() {
    return `${this.first_name} ${this.last_name}`;
  }

  @computed('address', 'city', 'state', 'zip_code')
  get full_address() {
    return `${this.address}, ${this.city}, ${this.state} ${this.zip_code}`;
  }

  @computed('relationship')
  get relationship_label() {
    const labels = {
      parent: 'Parent',
      guardian: 'Legal Guardian',
      foster_parent: 'Foster Parent',
      grandparent: 'Grandparent',
      other: 'Other'
    };
    return labels[this.relationship] || this.relationship;
  }

  @computed('notification_preferences')
  get receives_email() {
    return this.notification_preferences?.email !== false;
  }

  @computed('notification_preferences')
  get receives_sms() {
    return this.notification_preferences?.sms === true;
  }

  @computed('notification_preferences')
  get receives_push() {
    return this.notification_preferences?.push === true;
  }

  /** @relationships */
  @hasMany('student') students;
  @hasMany('communication') communications;

  /** @methods */
  canReceiveNotification(type) {
    if (!this.receives_notifications) return false;
    if (!this.notification_preferences) return true;
    
    return this.notification_preferences[type] !== false;
  }

  isAuthorizedToPickup(personName) {
    if (!this.authorized_pickups) return false;
    return this.authorized_pickups.some(p => 
      p.name.toLowerCase() === personName.toLowerCase()
    );
  }

  addAuthorizedPickup(person) {
    const pickups = this.authorized_pickups || [];
    pickups.push({
      name: person.name,
      relationship: person.relationship,
      phone: person.phone,
      id_verified: person.id_verified || false,
      added_at: new Date().toISOString()
    });
    this.authorized_pickups = pickups;
    return this.save();
  }

  removeAuthorizedPickup(personName) {
    if (!this.authorized_pickups) return;
    this.authorized_pickups = this.authorized_pickups.filter(p => 
      p.name.toLowerCase() !== personName.toLowerCase()
    );
    return this.save();
  }

  updateNotificationPreferences(preferences) {
    this.notification_preferences = {
      ...this.notification_preferences,
      ...preferences
    };
    return this.save();
  }
}

