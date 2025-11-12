import Model, { attr, belongsTo } from '@ember-data/model';
import { computed } from '@ember/object';

export default class CommunicationModel extends Model {
  /** @ids */
  @attr('string') public_id;

  /** @attributes */
  @attr('string') type;
  @attr('string') title;
  @attr('string') message;
  @attr() recipients;
  @attr() delivery_channels;
  @attr('string') priority;
  @attr('string') status;
  @attr('date') scheduled_at;
  @attr('date') sent_at;
  @attr() delivery_status;
  @attr() template_data;
  @attr('boolean') requires_acknowledgment;
  @attr() acknowledgments;
  @attr('string') created_by_uuid;
  @attr() meta;

  /** @computed */
  @computed('status', 'scheduled_at')
  get is_scheduled() {
    return this.status === 'scheduled' && this.scheduled_at && new Date(this.scheduled_at) > new Date();
  }

  @computed('status')
  get is_sent() {
    return ['sent', 'delivered'].includes(this.status);
  }

  @computed('status')
  get is_pending() {
    return ['draft', 'scheduled'].includes(this.status);
  }

  @computed('delivery_status')
  get delivery_rate() {
    if (!this.delivery_status || !Array.isArray(this.delivery_status)) return 0;
    
    const total = this.delivery_status.length;
    const delivered = this.delivery_status.filter(status => status.status === 'delivered').length;
    
    return total > 0 ? Math.round((delivered / total) * 100) : 0;
  }

  @computed('requires_acknowledgment', 'acknowledgments', 'recipients')
  get acknowledgment_rate() {
    if (!this.requires_acknowledgment || !this.acknowledgments) return 0;
    
    const total = Array.isArray(this.recipients) ? this.recipients.length : 0;
    const acknowledged = Object.keys(this.acknowledgments).length;
    
    return total > 0 ? Math.round((acknowledged / total) * 100) : 0;
  }

  @computed('message', 'template_data')
  get formatted_message() {
    let message = this.message || '';
    
    if (this.template_data) {
      Object.entries(this.template_data).forEach(([key, value]) => {
        message = message.replace(new RegExp(`{${key}}`, 'g'), value || '');
      });
    }
    
    return message;
  }

  @computed('recipients')
  get recipients_count() {
    return Array.isArray(this.recipients) ? this.recipients.length : 0;
  }

  @computed('requires_acknowledgment', 'acknowledgments', 'recipients')
  get unacknowledged_recipients() {
    if (!this.requires_acknowledgment || !Array.isArray(this.recipients)) return [];
    
    const acknowledgedIds = this.acknowledgments ? Object.keys(this.acknowledgments) : [];
    return this.recipients.filter(id => !acknowledgedIds.includes(id));
  }

  @computed('priority')
  get is_high_priority() {
    return ['high', 'urgent'].includes(this.priority);
  }

  @computed('type')
  get type_display() {
    return this.type ? this.type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : '';
  }

  @computed('priority')
  get priority_display() {
    const priorities = {
      low: 'Low',
      normal: 'Normal',
      high: 'High',
      urgent: 'Urgent'
    };
    return priorities[this.priority] || this.priority;
  }

  @computed('priority')
  get priority_color() {
    const colors = {
      low: 'text-gray-600',
      normal: 'text-blue-600',
      high: 'text-orange-600',
      urgent: 'text-red-600'
    };
    return colors[this.priority] || 'text-gray-600';
  }

  @computed('status')
  get status_display() {
    const statuses = {
      draft: 'Draft',
      scheduled: 'Scheduled',
      sent: 'Sent',
      delivered: 'Delivered',
      failed: 'Failed'
    };
    return statuses[this.status] || this.status;
  }

  @computed('status')
  get status_color() {
    const colors = {
      draft: 'text-gray-600',
      scheduled: 'text-blue-600',
      sent: 'text-indigo-600',
      delivered: 'text-green-600',
      failed: 'text-red-600'
    };
    return colors[this.status] || 'text-gray-600';
  }

  /** @relationships */
  @belongsTo('school-route', { inverse: null }) route;
  @belongsTo('student', { inverse: null }) student;

  /** @methods */
  async send() {
    if (!['draft', 'scheduled'].includes(this.status)) return false;
    
    try {
      this.status = 'sent';
      this.sent_at = new Date();
      await this.save();
      return true;
    } catch (error) {
      this.status = 'failed';
      await this.save();
      return false;
    }
  }

  async schedule(scheduledAt) {
    this.status = 'scheduled';
    this.scheduled_at = scheduledAt;
    return this.save();
  }

  async acknowledge(recipientId, data = null) {
    if (!this.requires_acknowledgment) return false;
    
    const acknowledgments = this.acknowledgments || {};
    acknowledgments[recipientId] = {
      acknowledged_at: new Date().toISOString(),
      data
    };
    
    this.acknowledgments = acknowledgments;
    return this.save();
  }

  isAcknowledgedBy(recipientId) {
    return this.acknowledgments && this.acknowledgments[recipientId] !== undefined;
  }

  applyTemplate(templateData) {
    this.template_data = { ...this.template_data, ...templateData };
    
    // Apply template to message if it contains template variables
    let message = this.message;
    Object.entries(templateData).forEach(([key, value]) => {
      message = message.replace(new RegExp(`{${key}}`, 'g'), value || '');
    });
    
    this.message = message;
    return this;
  }

  getDeliveryStatusFor(recipientId) {
    if (!this.delivery_status || !Array.isArray(this.delivery_status)) return null;
    return this.delivery_status.find(status => status.recipient === recipientId);
  }

  getFormattedScheduledTime() {
    if (!this.scheduled_at) return null;
    return new Date(this.scheduled_at).toLocaleString();
  }

  getFormattedSentTime() {
    if (!this.sent_at) return null;
    return new Date(this.sent_at).toLocaleString();
  }

  canBeEdited() {
    return ['draft'].includes(this.status);
  }

  canBeSent() {
    return ['draft', 'scheduled'].includes(this.status);
  }

  canBeScheduled() {
    return ['draft'].includes(this.status);
  }

  canBeCancelled() {
    return ['scheduled'].includes(this.status);
  }
}