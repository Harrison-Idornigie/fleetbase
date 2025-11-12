import Model, { attr, belongsTo, hasMany } from '@ember-data/model';
import { computed } from '@ember/object';

export default class BusAssignmentModel extends Model {
  /** @ids */
  @attr('string') public_id;

  /** @attributes */
  @attr('number') stop_sequence;
  @attr('string') pickup_stop;
  @attr() pickup_coordinates;
  @attr('string') pickup_time;
  @attr('string') dropoff_stop;
  @attr() dropoff_coordinates;
  @attr('string') dropoff_time;
  @attr('string') assignment_type;
  @attr('date') effective_date;
  @attr('date') end_date;
  @attr('boolean') requires_assistance;
  @attr('string') special_instructions;
  @attr('string') status;
  @attr() attendance_tracking;
  @attr() meta;

  /** @computed */
  @computed('status', 'effective_date', 'end_date')
  get is_active() {
    if (this.status !== 'active') return false;
    
    const today = new Date().toISOString().split('T')[0];
    
    if (this.effective_date && this.effective_date > today) return false;
    if (this.end_date && this.end_date < today) return false;
    
    return true;
  }

  @computed('effective_date', 'end_date')
  get duration_in_days() {
    if (!this.effective_date) return 0;
    
    const endDate = this.end_date ? new Date(this.end_date) : new Date();
    const startDate = new Date(this.effective_date);
    
    return Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
  }

  @computed('attendance_records.@each.present')
  get attendance_rate() {
    const totalDays = this.attendance_records.length;
    if (totalDays === 0) return 0;
    
    const presentDays = this.attendance_records.filter(record => record.present).length;
    return Math.round((presentDays / totalDays) * 100);
  }

  @computed('attendance_records.@each.date', 'attendance_records.@each.present')
  get recent_attendance() {
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
    
    return this.attendance_records
      .filter(record => new Date(record.date) >= sevenDaysAgo)
      .sort((a, b) => new Date(b.date) - new Date(a.date))
      .slice(0, 7)
      .map(record => ({
        date: record.date,
        present: record.present,
        event_type: record.event_type,
        notes: record.notes
      }));
  }

  @computed('pickup_stop', 'stop_sequence')
  get pickup_location_display() {
    return this.pickup_stop || `Stop #${this.stop_sequence}`;
  }

  @computed('dropoff_stop', 'route.school')
  get dropoff_location_display() {
    return this.dropoff_stop || this.route?.school || 'School';
  }

  @computed('pickup_time', 'route.start_time', 'stop_sequence')
  get estimated_pickup_time() {
    if (this.pickup_time) return this.pickup_time;
    
    if (!this.route?.start_time || !this.stop_sequence) return null;
    
    const minutesFromStart = (this.stop_sequence - 1) * 5;
    const startTime = new Date(`1970-01-01T${this.route.start_time}:00`);
    startTime.setMinutes(startTime.getMinutes() + minutesFromStart);
    
    return startTime.toTimeString().slice(0, 5);
  }

  @computed('route.operates_today', 'estimated_pickup_time')
  get upcoming_pickup_time() {
    if (!this.route?.operates_today) return null;
    return this.estimated_pickup_time;
  }

  @computed('student.has_special_needs')
  get is_for_special_needs_student() {
    return this.student?.has_special_needs || false;
  }

  /** @relationships */
  @belongsTo('student') student;
  @belongsTo('school-route') route;
  @hasMany('attendance') attendance_records;

  /** @methods */
  wasPresentToday() {
    const today = new Date().toISOString().split('T')[0];
    const todayAttendance = this.attendance_records.find(record => record.date === today);
    return todayAttendance ? todayAttendance.present : null;
  }

  overlapsWith(otherAssignment) {
    if (this.student.id !== otherAssignment.student.id) return false;
    
    const thisStart = new Date(this.effective_date);
    const thisEnd = this.end_date ? new Date(this.end_date) : new Date('9999-12-31');
    const otherStart = new Date(otherAssignment.effective_date);
    const otherEnd = otherAssignment.end_date ? new Date(otherAssignment.end_date) : new Date('9999-12-31');
    
    return thisStart <= otherEnd && thisEnd >= otherStart;
  }

  async markAttendance(date, present, eventType = 'pickup', notes = null) {
    // This would typically make an API call to record attendance
    const attendanceData = {
      student_id: this.student.id,
      route_id: this.route.id,
      assignment_id: this.id,
      date,
      present,
      event_type: eventType,
      notes,
      actual_time: new Date().toISOString(),
      status: 'completed'
    };
    
    // Return promise that resolves to attendance record
    return attendanceData;
  }

  async extend(newEndDate = null) {
    this.end_date = newEndDate;
    return this.save();
  }

  async deactivate(endDate = null) {
    this.status = 'inactive';
    this.end_date = endDate || new Date().toISOString().split('T')[0];
    return this.save();
  }

  getDaysOfWeek() {
    return this.route?.days_of_week || [];
  }

  getExpectedAttendanceDays(startDate, endDate) {
    if (!this.route?.days_of_week) return [];
    
    const days = [];
    const current = new Date(startDate);
    const end = new Date(endDate);
    
    while (current <= end) {
      const dayName = current.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
      if (this.route.days_of_week.includes(dayName)) {
        days.push(current.toISOString().split('T')[0]);
      }
      current.setDate(current.getDate() + 1);
    }
    
    return days;
  }
}