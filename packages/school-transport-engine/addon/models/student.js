import Model, { attr, belongsTo, hasMany } from '@ember-data/model';
import { computed } from '@ember/object';

export default class StudentModel extends Model {
  /** @ids */
  @attr('string') public_id;
  @attr('string') student_id;

  /** @attributes */
  @attr('string') first_name;
  @attr('string') last_name;
  @attr('string') grade;
  @attr('string') school;
  @attr('date') date_of_birth;
  @attr('string') gender;
  @attr('string') home_address;
  @attr() home_coordinates;
  @attr('string') parent_name;
  @attr('string') parent_email;
  @attr('string') parent_phone;
  @attr('string') emergency_contact_name;
  @attr('string') emergency_contact_phone;
  @attr() special_needs;
  @attr() medical_info;
  @attr('string') pickup_location;
  @attr() pickup_coordinates;
  @attr('string') dropoff_location;
  @attr() dropoff_coordinates;
  @attr('boolean') is_active;
  @attr('string') photo_url;
  @attr() meta;

  /** @computed */
  @computed('first_name', 'last_name')
  get full_name() {
    return `${this.first_name} ${this.last_name}`;
  }

  @computed('date_of_birth')
  get age() {
    if (!this.date_of_birth) return null;
    const today = new Date();
    const birthDate = new Date(this.date_of_birth);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    
    return age;
  }

  @computed('special_needs')
  get has_special_needs() {
    return this.special_needs && this.special_needs.length > 0;
  }

  @computed('special_needs')
  get requires_wheelchair_access() {
    return this.special_needs && this.special_needs.includes('wheelchair_accessible');
  }

  @computed('pickup_location', 'home_address')
  get formatted_address() {
    return this.pickup_location || this.home_address;
  }

  /** @relationships */
  @hasMany('bus-assignment') bus_assignments;
  @hasMany('attendance') attendance_records;
  @hasMany('communication') communications;

  /** @methods */
  getCurrentRoute() {
    const activeAssignment = this.bus_assignments.find(assignment => 
      assignment.status === 'active' && assignment.is_active
    );
    return activeAssignment ? activeAssignment.route : null;
  }

  getActiveAssignments() {
    return this.bus_assignments.filter(assignment => 
      assignment.status === 'active' && assignment.is_active
    );
  }

  getRecentAttendance(days = 7) {
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - days);
    
    return this.attendance_records
      .filter(record => new Date(record.date) >= cutoffDate)
      .sort((a, b) => new Date(b.date) - new Date(a.date));
  }
}