import Model, { attr, belongsTo } from '@ember-data/model';

export default class AlertModel extends Model {
  @attr('string') public_id;
  @attr('string') company_uuid;
  @attr('string') trip_uuid;
  @attr('string') bus_uuid;
  @attr('string') driver_uuid;
  @attr('string') student_uuid;
  @attr('string') route_uuid;
  @attr('string') alert_type;
  @attr('string') severity;
  @attr('string') title;
  @attr('string') description;
  @attr('string') status;
  @attr('') location;
  @attr('date') occurred_at;
  @attr('date') acknowledged_at;
  @attr('string') acknowledged_by;
  @attr('date') resolved_at;
  @attr('string') resolved_by;
  @attr('string') resolution_notes;
  @attr('string') notification_sent_to;
  @attr('date') notification_sent_at;
  @attr('') meta;
  @attr('date') created_at;
  @attr('date') updated_at;
  @attr('date') deleted_at;

  // Relationships
  @belongsTo('trip', { async: true, inverse: 'alerts' }) trip;
  @belongsTo('bus', { async: true, inverse: 'alerts' }) bus;
  @belongsTo('driver', { async: true, inverse: 'alerts' }) driver;
  @belongsTo('student', { async: true, inverse: 'alerts' }) student;
  @belongsTo('school-route', { async: true, inverse: 'alerts' }) route;

  // Computed properties
  get isOpen() {
    return this.status === 'open' || this.status === 'new';
  }

  get isAcknowledged() {
    return this.status === 'acknowledged';
  }

  get isResolved() {
    return this.status === 'resolved';
  }

  get isCritical() {
    return this.severity === 'critical' || this.severity === 'emergency';
  }

  get isHighPriority() {
    return this.severity === 'high' || this.isCritical;
  }

  get severityColor() {
    switch (this.severity) {
      case 'critical':
      case 'emergency':
        return 'red';
      case 'high':
        return 'orange';
      case 'medium':
        return 'yellow';
      case 'low':
        return 'blue';
      default:
        return 'gray';
    }
  }

  get timeSinceOccurred() {
    if (!this.occurred_at) return null;
    const diff = new Date() - new Date(this.occurred_at);
    const minutes = Math.floor(diff / (1000 * 60));
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
  }
}
