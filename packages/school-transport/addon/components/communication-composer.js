import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class CommunicationComposerComponent extends Component {
  @service schoolTransportApi;
  @service notifications;

  @tracked selectedMethod = 'all';
  @tracked showingVariables = false;
  @tracked selectedTemplate = null;
  @tracked recipientCount = 0;

  communicationTypes = [
    'Announcement',
    'Alert',
    'Reminder',
    'Delay Notification',
    'Route Change',
    'Cancellation',
    'Emergency',
    'General'
  ];

  priorityLevels = [
    'Low',
    'Normal',
    'High',
    'Urgent'
  ];

  deliveryChannels = [
    { value: 'email', label: 'Email', icon: 'envelope' },
    { value: 'sms', label: 'SMS', icon: 'mobile' },
    { value: 'push', label: 'Push Notification', icon: 'bell' },
    { value: 'in_app', label: 'In-App', icon: 'comment' }
  ];

  recipientMethods = [
    { value: 'all', label: 'All Parents', icon: 'users' },
    { value: 'students', label: 'By Students', icon: 'user-graduate' },
    { value: 'routes', label: 'By Routes', icon: 'route' },
    { value: 'schools', label: 'By Schools', icon: 'school' },
    { value: 'grades', label: 'By Grades', icon: 'graduation-cap' }
  ];

  gradeOptions = ['K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

  availableVariables = [
    '{student_name}',
    '{student_id}',
    '{parent_name}',
    '{route_name}',
    '{route_number}',
    '{pickup_time}',
    '{dropoff_time}',
    '{stop_name}',
    '{stop_address}',
    '{driver_name}',
    '{vehicle_number}',
    '{school_name}',
    '{current_date}',
    '{current_time}'
  ];

  templates = [];

  get today() {
    return new Date().toISOString().split('T')[0];
  }

  get characterCount() {
    return this.args.communication.message?.length || 0;
  }

  get smsSegments() {
    const length = this.characterCount;
    if (length === 0) return 0;
    if (length <= 160) return 1;
    return Math.ceil(length / 153);
  }

  @action
  toggleChannel(channel) {
    const channels = this.args.communication.channels || [];
    const index = channels.indexOf(channel);
    
    if (index > -1) {
      channels.splice(index, 1);
    } else {
      channels.push(channel);
    }

    this.args.onChange('channels', channels);
  }

  @action
  selectRecipientMethod(method) {
    this.selectedMethod = method;
    this.updateRecipientCount();
  }

  @action
  getTypeIcon(type) {
    const iconMap = {
      'Announcement': 'bullhorn',
      'Alert': 'exclamation-triangle',
      'Reminder': 'clock',
      'Delay Notification': 'hourglass-half',
      'Route Change': 'exchange-alt',
      'Cancellation': 'ban',
      'Emergency': 'exclamation-circle',
      'General': 'comment'
    };
    return iconMap[type] || 'comment';
  }

  @action
  showVariables() {
    this.showingVariables = !this.showingVariables;
  }

  @action
  insertVariable(variable) {
    const currentMessage = this.args.communication.message || '';
    const newMessage = currentMessage + variable;
    this.args.onChange('message', newMessage);
  }

  @action
  applyTemplate(template) {
    if (!template) {
      this.selectedTemplate = null;
      return;
    }

    this.selectedTemplate = template;
    this.args.onChange('subject', template.subject);
    this.args.onChange('message', template.message);
    this.args.onChange('type', template.type);
    
    this.notifications.success('Template applied successfully');
  }

  @action
  handleFileUpload(event) {
    const files = Array.from(event.target.files);
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    const validFiles = files.filter(file => {
      if (file.size > maxSize) {
        this.notifications.error(`File ${file.name} exceeds 10MB limit`);
        return false;
      }
      return true;
    });

    const currentAttachments = this.args.communication.attachments || [];
    this.args.onChange('attachments', [...currentAttachments, ...validFiles]);
  }

  @action
  removeAttachment(index) {
    const attachments = [...this.args.communication.attachments];
    attachments.splice(index, 1);
    this.args.onChange('attachments', attachments);
  }

  @action
  setScheduleType(type) {
    this.args.onChange('schedule_type', type);
  }

  @action
  async updateRecipientCount() {
    try {
      let count = 0;

      switch (this.selectedMethod) {
        case 'all':
          count = await this.schoolTransportApi.getTotalParentCount();
          break;
        case 'students':
          count = this.args.communication.student_ids?.length || 0;
          break;
        case 'routes':
          count = await this.schoolTransportApi.getParentCountByRoutes(
            this.args.communication.route_ids
          );
          break;
        case 'schools':
          count = await this.schoolTransportApi.getParentCountBySchools(
            this.args.communication.school_ids
          );
          break;
        case 'grades':
          count = await this.schoolTransportApi.getParentCountByGrades(
            this.args.communication.grades
          );
          break;
      }

      this.recipientCount = count;
    } catch (error) {
      console.error('Error calculating recipient count:', error);
    }
  }
}
