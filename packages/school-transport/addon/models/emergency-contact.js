import Model, { attr, belongsTo } from '@ember-data/model';
import { computed } from '@ember/object';

export default class EmergencyContactModel extends Model {
  /** @ids */
  @attr('string') public_id;
  @attr('string') student_uuid;

  /** @attributes */
  @attr('string') first_name;
  @attr('string') last_name;
  @attr('string') relationship;
  @attr('string') phone;
  @attr('string') alternate_phone;
  @attr('string') email;
  @attr('string') address;
  @attr('string') city;
  @attr('string') state;
  @attr('string') zip_code;
  @attr('number') priority; // 1 = primary, 2 = secondary, etc.
  @attr('boolean') can_pickup;
  @attr('boolean') lives_with_student;
  @attr('string') workplace;
  @attr('string') work_phone;
  @attr() availability; // when they can be reached
  @attr('string') notes;
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
    if (!this.address) return '';
    return `${this.address}, ${this.city}, ${this.state} ${this.zip_code}`;
  }

  @computed('relationship')
  get relationship_label() {
    const labels = {
      parent: 'Parent',
      guardian: 'Legal Guardian',
      grandparent: 'Grandparent',
      aunt: 'Aunt',
      uncle: 'Uncle',
      sibling: 'Sibling',
      family_friend: 'Family Friend',
      neighbor: 'Neighbor',
      other: 'Other'
    };
    return labels[this.relationship] || this.relationship;
  }

  @computed('priority')
  get priority_label() {
    const labels = {
      1: 'Primary Contact',
      2: 'Secondary Contact',
      3: 'Tertiary Contact'
    };
    return labels[this.priority] || `Contact ${this.priority}`;
  }

  @computed('phone', 'alternate_phone', 'work_phone')
  get all_phones() {
    const phones = [];
    if (this.phone) phones.push({ type: 'Primary', number: this.phone });
    if (this.alternate_phone) phones.push({ type: 'Alternate', number: this.alternate_phone });
    if (this.work_phone) phones.push({ type: 'Work', number: this.work_phone });
    return phones;
  }

  @computed('priority')
  get is_primary() {
    return this.priority === 1;
  }

  /** @relationships */
  @belongsTo('student', { inverse: 'emergency_contacts' }) student;

  /** @methods */
  isAvailableAt(time) {
    if (!this.availability) return true;
    
    // Check if contact is available at given time
    // availability format: { days: ['monday', 'tuesday'], hours: { start: '08:00', end: '17:00' } }
    const now = time || new Date();
    const dayName = now.toLocaleDateString('en-US', { weekday: 'lowercase' });
    
    if (this.availability.days && !this.availability.days.includes(dayName)) {
      return false;
    }
    
    if (this.availability.hours) {
      const currentTime = now.toTimeString().slice(0, 5);
      return currentTime >= this.availability.hours.start && 
             currentTime <= this.availability.hours.end;
    }
    
    return true;
  }

  getBestPhoneNumber() {
    if (this.phone) return this.phone;
    if (this.work_phone) return this.work_phone;
    if (this.alternate_phone) return this.alternate_phone;
    return null;
  }

  async notifyEmergency(message, method = 'phone') {
    // This would integrate with notification service
    // For now, just a placeholder
    return {
      contact: this.full_name,
      method,
      phone: this.getBestPhoneNumber(),
      email: this.email,
      message,
      sent_at: new Date().toISOString()
    };
  }
}

