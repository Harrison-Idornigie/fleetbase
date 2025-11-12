import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportCommunicationsNewController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked currentStep = 1;
  @tracked totalSteps = 3;
  @tracked selectedTab = 'compose';

  // Message details
  @tracked subject = '';
  @tracked message = '';
  @tracked type = 'email';
  @tracked priority = 'normal';
  @tracked scheduledSendDate = '';
  @tracked scheduledSendTime = '';

  // Recipients
  @tracked recipientType = 'students';
  @tracked selectedRecipients = [];
  @tracked availableRecipients = [];
  @tracked selectedRoutes = [];
  @tracked selectedStudents = [];
  @tracked customRecipients = '';

  // Template and personalization
  @tracked selectedTemplate = '';
  @tracked useTemplate = false;
  @tracked personalizationVariables = {};

  // Additional options
  @tracked sendAsSMS = false;
  @tracked sendAsEmail = true;
  @tracked sendAsPush = false;
  @tracked requireReadReceipt = false;
  @tracked allowReplies = true;
  @tracked saveAsTemplate = false;
  @tracked templateName = '';

  // Validation errors
  @tracked errors = {};

  get isFirstStep() {
    return this.currentStep === 1;
  }

  get isLastStep() {
    return this.currentStep === this.totalSteps;
  }

  get progressPercentage() {
    return Math.round((this.currentStep / this.totalSteps) * 100);
  }

  get canProceed() {
    return this.validateCurrentStep();
  }

  get stepTitle() {
    const titles = {
      1: 'Recipients',
      2: 'Compose Message',
      3: 'Review & Send'
    };
    return titles[this.currentStep] || 'Compose Message';
  }

  get stepDescription() {
    const descriptions = {
      1: 'Select who will receive this message',
      2: 'Write your message and choose delivery options',
      3: 'Review and send your communication'
    };
    return descriptions[this.currentStep] || '';
  }

  get typeOptions() {
    return [
      { value: 'email', label: 'Email' },
      { value: 'sms', label: 'SMS Text' },
      { value: 'push', label: 'Push Notification' },
      { value: 'voice', label: 'Voice Call' }
    ];
  }

  get priorityOptions() {
    return [
      { value: 'low', label: 'Low Priority' },
      { value: 'normal', label: 'Normal Priority' },
      { value: 'high', label: 'High Priority' },
      { value: 'emergency', label: 'Emergency' }
    ];
  }

  get recipientTypeOptions() {
    return [
      { value: 'students', label: 'Specific Students' },
      { value: 'parents', label: 'Parents/Guardians' },
      { value: 'routes', label: 'Students on Routes' },
      { value: 'all_students', label: 'All Students' },
      { value: 'all_parents', label: 'All Parents' },
      { value: 'drivers', label: 'Drivers' },
      { value: 'staff', label: 'Staff Members' },
      { value: 'custom', label: 'Custom Recipients' }
    ];
  }

  get availableRoutes() {
    return this.model.routes || [];
  }

  get availableStudents() {
    return this.model.students || [];
  }

  get availableDrivers() {
    return this.model.drivers || [];
  }

  get availableStaff() {
    return this.model.staff || [];
  }

  get availableTemplates() {
    return this.model.templates || [];
  }

  get filteredRecipients() {
    switch (this.recipientType) {
      case 'students':
        return this.availableStudents;
      case 'parents':
        return this.availableStudents.map(student => ({
          id: `parent_${student.id}`,
          name: student.parent_name || `${student.first_name} ${student.last_name}'s Parent`,
          type: 'parent',
          student_name: `${student.first_name} ${student.last_name}`
        }));
      case 'routes':
        return this.selectedRoutes.flatMap(routeId => {
          const route = this.availableRoutes.find(r => r.id === routeId);
          return route?.students || [];
        });
      case 'drivers':
        return this.availableDrivers;
      case 'staff':
        return this.availableStaff;
      case 'all_students':
        return this.availableStudents;
      case 'all_parents':
        return this.availableStudents.map(student => ({
          id: `parent_${student.id}`,
          name: student.parent_name || `${student.first_name} ${student.last_name}'s Parent`,
          type: 'parent',
          student_name: `${student.first_name} ${student.last_name}`
        }));
      default:
        return [];
    }
  }

  get selectedRecipientsCount() {
    if (this.recipientType === 'all_students' || this.recipientType === 'all_parents') {
      return this.filteredRecipients.length;
    }
    return this.selectedRecipients.length;
  }

  get messagePreview() {
    let preview = this.message;

    // Replace personalization variables
    Object.entries(this.personalizationVariables).forEach(([key, value]) => {
      preview = preview.replace(new RegExp(`{{${key}}}`, 'g'), value || `[${key}]`);
    });

    return preview;
  }

  get characterCount() {
    return this.message.length;
  }

  get smsPageCount() {
    // SMS messages are typically 160 characters, but can be longer
    const smsLength = 160;
    return Math.ceil(this.characterCount / smsLength);
  }

  get estimatedCost() {
    if (this.type === 'sms') {
      return this.selectedRecipientsCount * this.smsPageCount * 0.01; // $0.01 per SMS
    }
    return 0;
  }

  constructor() {
    super(...arguments);
    this.initializeFromQueryParams();
    this.loadAvailableRecipients();
  }

  initializeFromQueryParams() {
    const queryParams = this.router.currentRoute.queryParams;

    if (queryParams.recipient_type) {
      this.recipientType = queryParams.recipient_type;
    }

    if (queryParams.recipient_id) {
      this.selectedRecipients = [queryParams.recipient_id];
    }

    if (queryParams.type) {
      this.type = queryParams.type;
      this.priority = queryParams.type === 'emergency' ? 'emergency' : 'normal';

      // Pre-fill message based on type
      this.setDefaultMessage(queryParams.type);
    }

    if (queryParams.template_id) {
      this.useTemplate = true;
      this.selectedTemplate = queryParams.template_id;
      this.loadTemplate(queryParams.template_id);
    }
  }

  setDefaultMessage(type) {
    const defaultMessages = {
      emergency: 'EMERGENCY ALERT: [Please provide emergency details]',
      delay: 'Route Delay Notice: Your child\'s route is experiencing a delay. Expected arrival time: [time]',
      route_change: 'Route Change Notice: There has been a change to your child\'s transportation route. [Details]'
    };

    if (defaultMessages[type]) {
      this.message = defaultMessages[type];
      this.subject = type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) + ' Notice';
    }
  }

  async loadAvailableRecipients() {
    try {
      // This would typically fetch from the backend
      // For now, using mock data
    } catch (error) {
      console.error('Error loading recipients:', error);
    }
  }

  async loadTemplate(templateId) {
    try {
      const template = this.availableTemplates.find(t => t.id === templateId);
      if (template) {
        this.subject = template.subject;
        this.message = template.message;
        this.type = template.type;
      }
    } catch (error) {
      console.error('Error loading template:', error);
    }
  }

  validateCurrentStep() {
    this.errors = {};
    let isValid = true;

    switch (this.currentStep) {
      case 1:
        if (this.selectedRecipientsCount === 0 && this.recipientType !== 'custom') {
          this.errors.recipients = 'Please select at least one recipient';
          isValid = false;
        }
        if (this.recipientType === 'custom' && !this.customRecipients.trim()) {
          this.errors.customRecipients = 'Please enter custom recipient details';
          isValid = false;
        }
        break;

      case 2:
        if (!this.subject.trim()) {
          this.errors.subject = 'Subject is required';
          isValid = false;
        }
        if (!this.message.trim()) {
          this.errors.message = 'Message content is required';
          isValid = false;
        }
        if (this.type === 'sms' && this.characterCount > 1600) {
          this.errors.message = 'SMS messages cannot exceed 1600 characters';
          isValid = false;
        }
        break;

      case 3:
        // Review step - validation already done in previous steps
        break;
    }

    return isValid;
  }

  @action
  selectTab(tab) {
    this.selectedTab = tab;
  }

  @action
  nextStep() {
    if (this.canProceed && !this.isLastStep) {
      this.currentStep++;
    }
  }

  @action
  previousStep() {
    if (!this.isFirstStep) {
      this.currentStep--;
    }
  }

  @action
  goToStep(step) {
    if (step >= 1 && step <= this.totalSteps) {
      this.currentStep = step;
    }
  }

  @action
  updateField(field, value) {
    this[field] = value;
    if (this.errors[field]) {
      delete this.errors[field];
    }
  }

  @action
  updateRecipientType(type) {
    this.recipientType = type;
    this.selectedRecipients = [];
    this.selectedRoutes = [];
    this.selectedStudents = [];
    this.customRecipients = '';
  }

  @action
  toggleRecipient(recipientId, isSelected) {
    if (isSelected) {
      this.selectedRecipients = [...this.selectedRecipients, recipientId];
    } else {
      this.selectedRecipients = this.selectedRecipients.filter(id => id !== recipientId);
    }
  }

  @action
  toggleRoute(routeId, isSelected) {
    if (isSelected) {
      this.selectedRoutes = [...this.selectedRoutes, routeId];
    } else {
      this.selectedRoutes = this.selectedRoutes.filter(id => id !== routeId);
    }
  }

  @action
  selectAllRecipients() {
    if (this.selectedRecipients.length === this.filteredRecipients.length) {
      this.selectedRecipients = [];
    } else {
      this.selectedRecipients = this.filteredRecipients.map(r => r.id);
    }
  }

  @action
  clearRecipients() {
    this.selectedRecipients = [];
    this.selectedRoutes = [];
    this.selectedStudents = [];
  }

  @action
  loadTemplateAction(templateId) {
    this.useTemplate = true;
    this.selectedTemplate = templateId;
    this.loadTemplate(templateId);
  }

  @action
  clearTemplate() {
    this.useTemplate = false;
    this.selectedTemplate = '';
    this.subject = '';
    this.message = '';
  }

  @action
  insertPersonalizationVariable(variable) {
    const textArea = document.querySelector('[data-message-textarea]');
    if (textArea) {
      const start = textArea.selectionStart;
      const end = textArea.selectionEnd;
      const text = this.message;
      const before = text.substring(0, start);
      const after = text.substring(end);
      this.message = `${before}{{${variable}}}${after}`;
    } else {
      this.message += `{{${variable}}}`;
    }
  }

  @action
  updatePersonalizationVariable(key, value) {
    this.personalizationVariables = {
      ...this.personalizationVariables,
      [key]: value
    };
  }

  @action
  async sendMessage() {
    if (!this.validateCurrentStep()) {
      this.notifications.error('Please fix the validation errors before sending');
      return;
    }

    this.isLoading = true;
    try {
      const messageData = {
        subject: this.subject,
        message: this.message,
        type: this.type,
        priority: this.priority,
        recipient_type: this.recipientType,
        recipients: this.selectedRecipients,
        routes: this.selectedRoutes,
        custom_recipients: this.customRecipients,
        scheduled_send_date: this.scheduledSendDate,
        scheduled_send_time: this.scheduledSendTime,
        send_as_sms: this.sendAsSMS,
        send_as_email: this.sendAsEmail,
        send_as_push: this.sendAsPush,
        require_read_receipt: this.requireReadReceipt,
        allow_replies: this.allowReplies,
        personalization_variables: this.personalizationVariables
      };

      const newCommunication = this.store.createRecord('school-transport/communication', messageData);
      await newCommunication.save();

      // Save as template if requested
      if (this.saveAsTemplate && this.templateName.trim()) {
        await this.saveAsTemplateAction();
      }

      this.notifications.success('Message sent successfully');
      this.router.transitionTo('school-transport.communications.index');
    } catch (error) {
      console.error('Error sending message:', error);
      this.notifications.error('Failed to send message');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async saveAsTemplateAction() {
    try {
      const templateData = {
        name: this.templateName,
        subject: this.subject,
        message: this.message,
        type: this.type,
        category: 'custom'
      };

      await this.store.createRecord('school-transport/communication-template', templateData).save();
      this.notifications.success('Template saved successfully');
    } catch (error) {
      console.error('Error saving template:', error);
      this.notifications.error('Failed to save template');
    }
  }

  @action
  cancelComposition() {
    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
      this.router.transitionTo('school-transport.communications.index');
    }
  }

  @action
  async validateAndProceed() {
    if (this.validateCurrentStep()) {
      if (this.isLastStep) {
        await this.sendMessage();
      } else {
        this.nextStep();
      }
    } else {
      this.notifications.error('Please fix the validation errors');
    }
  }

  @action
  async testSend() {
    // Send a test message to the current user
    this.notifications.info('Test send functionality coming soon');
  }

  @action
  async scheduleSend() {
    if (!this.scheduledSendDate || !this.scheduledSendTime) {
      this.notifications.error('Please set both date and time for scheduling');
      return;
    }

    this.isLoading = true;
    try {
      const messageData = {
        subject: this.subject,
        message: this.message,
        type: this.type,
        priority: this.priority,
        recipient_type: this.recipientType,
        recipients: this.selectedRecipients,
        routes: this.selectedRoutes,
        custom_recipients: this.customRecipients,
        scheduled_send_date: this.scheduledSendDate,
        scheduled_send_time: this.scheduledSendTime,
        status: 'scheduled'
      };

      const scheduledCommunication = this.store.createRecord('school-transport/communication', messageData);
      await scheduledCommunication.save();

      this.notifications.success('Message scheduled successfully');
      this.router.transitionTo('school-transport.communications.index');
    } catch (error) {
      console.error('Error scheduling message:', error);
      this.notifications.error('Failed to schedule message');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async previewMessage() {
    // This would open a modal with message preview
    this.notifications.info('Preview functionality coming soon');
  }

  @action
  async checkSpamScore() {
    // This would check the message for spam content
    this.notifications.info('Spam check functionality coming soon');
  }

  @action
  async importRecipientsFromCSV() {
    // This would open a file picker for CSV import
    this.notifications.info('CSV import functionality coming soon');
  }
}