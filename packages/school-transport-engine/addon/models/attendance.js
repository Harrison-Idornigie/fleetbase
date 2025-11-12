import Model, { attr, belongsTo } from '@ember-data/model';
import { computed } from '@ember/object';

export default class AttendanceModel extends Model {
  /** @ids */
  @attr('string') public_id;

  /** @attributes */
  @attr('date') date;
  @attr('string') session; // morning, afternoon
  @attr('string') event_type; // pickup, dropoff, no_show, early_dismissal
  @attr('date') scheduled_time;
  @attr('date') actual_time;
  @attr('boolean') present;
  @attr('string') notes;
  @attr('string') recorded_by_uuid;
  @attr('string') location;
  @attr() coordinates;
  @attr('string') status; // scheduled, completed, missed, cancelled
  @attr() parent_notification;
  @attr() meta;

  /** @computed */
  @computed('scheduled_time', 'actual_time')
  get delay_minutes() {
    if (!this.scheduled_time || !this.actual_time) return null;
    
    const scheduled = new Date(this.scheduled_time);
    const actual = new Date(this.actual_time);
    
    return Math.round((actual - scheduled) / (1000 * 60));
  }

  @computed('delay_minutes')
  get is_on_time() {
    const delay = this.delay_minutes;
    return delay !== null && delay >= -2 && delay <= 5; // 2 min early to 5 min late
  }

  @computed('delay_minutes')
  get is_late() {
    const delay = this.delay_minutes;
    return delay !== null && delay > 5;
  }

  @computed('delay_minutes')
  get is_early() {
    const delay = this.delay_minutes;
    return delay !== null && delay < -2;
  }

  @computed('actual_time', 'scheduled_time')
  get formatted_time() {
    if (this.actual_time) {
      return new Date(this.actual_time).toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit' 
      });
    }
    
    if (this.scheduled_time) {
      return new Date(this.scheduled_time).toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit' 
      });
    }
    
    return 'Not recorded';
  }

  @computed('delay_minutes')
  get delay_status() {
    const delay = this.delay_minutes;
    
    if (delay === null) return 'unknown';
    if (delay < -2) return 'early';
    if (delay >= -2 && delay <= 5) return 'on-time';
    if (delay > 5 && delay <= 15) return 'late';
    
    return 'very-late';
  }

  @computed('delay_status')
  get delay_status_color() {
    const colors = {
      'early': 'text-blue-600',
      'on-time': 'text-green-600',
      'late': 'text-yellow-600',
      'very-late': 'text-red-600',
      'unknown': 'text-gray-600'
    };
    
    return colors[this.delay_status] || 'text-gray-600';
  }

  @computed('event_type')
  get event_type_display() {
    const types = {
      pickup: 'Pick-up',
      dropoff: 'Drop-off',
      no_show: 'No Show',
      early_dismissal: 'Early Dismissal'
    };
    
    return types[this.event_type] || this.event_type;
  }

  @computed('session')
  get session_display() {
    const sessions = {
      morning: 'Morning',
      afternoon: 'Afternoon'
    };
    
    return sessions[this.session] || this.session;
  }

  @computed('status')
  get status_display() {
    const statuses = {
      scheduled: 'Scheduled',
      completed: 'Completed',
      missed: 'Missed',
      cancelled: 'Cancelled'
    };
    
    return statuses[this.status] || this.status;
  }

  @computed('status')
  get status_color() {
    const colors = {
      scheduled: 'text-blue-600',
      completed: 'text-green-600',
      missed: 'text-red-600',
      cancelled: 'text-gray-600'
    };
    
    return colors[this.status] || 'text-gray-600';
  }

  @computed('present', 'event_type')
  get attendance_icon() {
    if (this.event_type === 'no_show') return 'times-circle';
    if (this.present) return 'check-circle';
    return 'exclamation-circle';
  }

  @computed('present', 'event_type')
  get attendance_color() {
    if (this.event_type === 'no_show') return 'text-red-600';
    if (this.present) return 'text-green-600';
    return 'text-yellow-600';
  }

  @computed('student.has_special_needs', 'present', 'parent_notification')
  get affects_safety_compliance() {
    return this.student?.has_special_needs && 
           !this.present && 
           (!this.parent_notification || this.parent_notification.length === 0);
  }

  /** @relationships */
  @belongsTo('student') student;
  @belongsTo('school-route') route;
  @belongsTo('bus-assignment') assignment;

  /** @methods */
  async markPresent(location = null, coordinates = null) {
    this.present = true;
    this.actual_time = new Date();
    this.location = location;
    this.coordinates = coordinates;
    this.status = 'completed';
    
    return this.save();
  }

  async markAbsent(reason = 'no_show') {
    this.present = false;
    this.event_type = reason;
    this.status = 'missed';
    
    if (this.notes) {
      this.notes = `${this.notes}; ${reason}`;
    } else {
      this.notes = reason;
    }
    
    return this.save();
  }

  async sendParentNotification(type = 'absence') {
    if (!this.student?.parent_email) return false;
    
    const notification = {
      type,
      sent_at: new Date().toISOString(),
      recipient: this.student.parent_email,
      message: this.generateNotificationMessage(type)
    };
    
    const notifications = this.parent_notification || [];
    notifications.push(notification);
    this.parent_notification = notifications;
    
    return this.save();
  }

  generateNotificationMessage(type) {
    const studentName = this.student?.full_name;
    const routeName = this.route?.route_name;
    const date = new Date(this.date).toLocaleDateString();
    const session = this.session_display;
    
    switch (type) {
      case 'absence':
        return `${studentName} was not present at their ${session} pickup for route ${routeName} on ${date}.`;
      case 'late':
        const delayMinutes = this.delay_minutes;
        return `${studentName} was ${delayMinutes} minutes late for their ${session} pickup on route ${routeName} on ${date}.`;
      case 'early':
        return `${studentName} was picked up early for their ${session} pickup on route ${routeName} on ${date}.`;
      default:
        return `Attendance update for ${studentName} on route ${routeName} on ${date}.`;
    }
  }

  getFormattedDate() {
    return new Date(this.date).toLocaleDateString();
  }

  getFormattedDateTime() {
    return new Date(this.date).toLocaleString();
  }

  isToday() {
    const today = new Date().toISOString().split('T')[0];
    return this.date === today;
  }

  isWithinRange(startDate, endDate) {
    const recordDate = new Date(this.date);
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    return recordDate >= start && recordDate <= end;
  }

  async updateNotes(notes) {
    this.notes = notes;
    return this.save();
  }

  async cancel() {
    this.status = 'cancelled';
    return this.save();
  }

  canBeModified() {
    return ['scheduled', 'completed'].includes(this.status);
  }

  wasParentNotified() {
    return this.parent_notification && this.parent_notification.length > 0;
  }
}