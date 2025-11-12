import Model, { attr, hasMany } from '@ember-data/model';
import { computed } from '@ember/object';

export default class SchoolModel extends Model {
  /** @ids */
  @attr('string') public_id;

  /** @attributes */
  @attr('string') name;
  @attr('string') district;
  @attr('string') address;
  @attr('string') city;
  @attr('string') state;
  @attr('string') zip_code;
  @attr() coordinates;
  @attr('string') phone;
  @attr('string') email;
  @attr('string') principal_name;
  @attr('string') principal_email;
  @attr('string') principal_phone;
  @attr('string') school_type; // elementary, middle, high, k-12
  @attr() grade_levels; // array of grades offered
  @attr('number') total_students;
  @attr('number') bus_eligible_students;
  @attr() bell_schedule; // object with start/end times
  @attr() special_schedules; // early dismissal, late start, etc.
  @attr('boolean') is_active;
  @attr() contact_info;
  @attr() meta;

  /** @timestamps */
  @attr('date') created_at;
  @attr('date') updated_at;

  /** @computed */
  @computed('name', 'city')
  get display_name() {
    return `${this.name} - ${this.city}`;
  }

  @computed('address', 'city', 'state', 'zip_code')
  get full_address() {
    return `${this.address}, ${this.city}, ${this.state} ${this.zip_code}`;
  }

  @computed('grade_levels')
  get grade_range() {
    if (!this.grade_levels || this.grade_levels.length === 0) return '';
    const sorted = [...this.grade_levels].sort();
    return `${sorted[0]} - ${sorted[sorted.length - 1]}`;
  }

  @computed('total_students', 'bus_eligible_students')
  get bus_utilization_percentage() {
    if (!this.total_students || this.total_students === 0) return 0;
    return Math.round((this.bus_eligible_students / this.total_students) * 100);
  }

  @computed('school_type')
  get school_type_label() {
    const labels = {
      elementary: 'Elementary School',
      middle: 'Middle School',
      high: 'High School',
      'k-12': 'K-12 School',
      charter: 'Charter School',
      private: 'Private School'
    };
    return labels[this.school_type] || this.school_type;
  }

  /** @relationships */
  @hasMany('student') students;
  @hasMany('school-route') routes;

  /** @methods */
  hasGrade(grade) {
    return this.grade_levels && this.grade_levels.includes(grade);
  }

  getBellTime(type = 'start') {
    if (!this.bell_schedule) return null;
    return this.bell_schedule[type] || null;
  }

  isOpenOn(date) {
    // Check if school is open on a given date
    // This would check against special_schedules for holidays, etc.
    if (!this.special_schedules) return true;
    
    const dateStr = date instanceof Date ? date.toISOString().split('T')[0] : date;
    const schedule = this.special_schedules.find(s => s.date === dateStr);
    
    return !schedule || schedule.type !== 'closed';
  }

  getScheduleForDate(date) {
    if (!this.special_schedules) return this.bell_schedule;
    
    const dateStr = date instanceof Date ? date.toISOString().split('T')[0] : date;
    const schedule = this.special_schedules.find(s => s.date === dateStr);
    
    return schedule ? schedule.schedule : this.bell_schedule;
  }
}

