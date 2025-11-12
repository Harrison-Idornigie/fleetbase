import Model, { attr } from '@ember-data/model';
import { computed } from '@ember/object';

export default class CommunicationTemplateModel extends Model {
  /** @ids */
  @attr('string') public_id;

  /** @attributes */
  @attr('string') name;
  @attr('string') description;
  @attr('string') category; // alert, reminder, update, general
  @attr('string') type; // email, sms, push, in_app
  @attr('string') subject;
  @attr('string') message_template;
  @attr() variables; // array of available variables
  @attr() default_values; // default values for variables
  @attr('string') language;
  @attr('boolean') is_active;
  @attr('boolean') is_system_template;
  @attr('string') created_by_uuid;
  @attr('number') usage_count;
  @attr('date') last_used_at;
  @attr() tags;
  @attr() meta;

  /** @timestamps */
  @attr('date') created_at;
  @attr('date') updated_at;

  /** @computed */
  @computed('category')
  get category_label() {
    const labels = {
      alert: 'Alert',
      reminder: 'Reminder',
      update: 'Update',
      general: 'General',
      emergency: 'Emergency',
      delay: 'Delay Notification',
      cancellation: 'Cancellation',
      attendance: 'Attendance',
      behavioral: 'Behavioral',
      safety: 'Safety'
    };
    return labels[this.category] || this.category;
  }

  @computed('type')
  get type_label() {
    const labels = {
      email: 'Email',
      sms: 'SMS',
      push: 'Push Notification',
      in_app: 'In-App Message'
    };
    return labels[this.type] || this.type;
  }

  @computed('type')
  get type_icon() {
    const icons = {
      email: 'envelope',
      sms: 'mobile',
      push: 'bell',
      in_app: 'comment'
    };
    return icons[this.type] || 'message';
  }

  @computed('message_template')
  get variable_placeholders() {
    if (!this.message_template) return [];
    
    const regex = /\{(\w+)\}/g;
    const matches = [];
    let match;
    
    while ((match = regex.exec(this.message_template)) !== null) {
      if (!matches.includes(match[1])) {
        matches.push(match[1]);
      }
    }
    
    return matches;
  }

  @computed('usage_count')
  get is_popular() {
    return this.usage_count > 10;
  }

  @computed('last_used_at')
  get recently_used() {
    if (!this.last_used_at) return false;
    const daysSinceUse = Math.floor((new Date() - new Date(this.last_used_at)) / (1000 * 60 * 60 * 24));
    return daysSinceUse <= 7;
  }

  /** @methods */
  renderMessage(data = {}) {
    if (!this.message_template) return '';
    
    let rendered = this.message_template;
    const values = { ...this.default_values, ...data };
    
    Object.keys(values).forEach(key => {
      const regex = new RegExp(`\\{${key}\\}`, 'g');
      rendered = rendered.replace(regex, values[key] || '');
    });
    
    return rendered;
  }

  renderSubject(data = {}) {
    if (!this.subject) return '';
    
    let rendered = this.subject;
    const values = { ...this.default_values, ...data };
    
    Object.keys(values).forEach(key => {
      const regex = new RegExp(`\\{${key}\\}`, 'g');
      rendered = rendered.replace(regex, values[key] || '');
    });
    
    return rendered;
  }

  async incrementUsage() {
    this.usage_count = (this.usage_count || 0) + 1;
    this.last_used_at = new Date();
    return this.save();
  }

  clone() {
    return {
      name: `${this.name} (Copy)`,
      description: this.description,
      category: this.category,
      type: this.type,
      subject: this.subject,
      message_template: this.message_template,
      variables: [...(this.variables || [])],
      default_values: { ...(this.default_values || {}) },
      language: this.language,
      is_active: false,
      is_system_template: false
    };
  }
}

