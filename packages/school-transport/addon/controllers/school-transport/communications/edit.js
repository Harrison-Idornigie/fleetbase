import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportCommunicationsEditController extends Controller {
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

  // Original communication data
  @tracked originalCommunication = null;
  @tracked hasUnsavedChanges = false;

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
      3: 'Review & Update'
    };
    return titles[this.currentStep] || 'Edit Message';
  }

  get stepDescription() {
    const descriptions = {
      1: 'Update who will receive this message',
      2: 'Edit your message and delivery options',
      3: 'Review changes and update communication'
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
    const smsLength = 160;
    return Math.ceil(this.characterCount / smsLength);
  }

  get estimatedCost() {
    if (this.type === 'sms') {
      return this.selectedRecipientsCount * this.smsPageCount * 0.01;
    }
    return 0;
  }

  get canSaveDraft() {
    return this.originalCommunication?.status === 'draft';
  }

  get canSchedule() {
    return this.originalCommunication?.status !== 'sent';
  }

  get canSendNow() {
    return this.originalCommunication?.status !== 'sent';
  }

  get hasChanges() {
    if (!this.originalCommunication) return false;

    return (
      this.subject !== this.originalCommunication.subject ||
      this.message !== this.originalCommunication.message ||
      this.type !== this.originalCommunication.type ||
      this.priority !== this.originalCommunication.priority ||
      this.recipientType !== this.originalCommunication.recipient_type ||
      JSON.stringify(this.selectedRecipients) !== JSON.stringify(this.originalCommunication.recipients || []) ||
      this.scheduledSendDate !== this.originalCommunication.scheduled_send_date ||
      this.scheduledSendTime !== this.originalCommunication.scheduled_send_time ||
      this.sendAsSMS !== this.originalCommunication.send_as_sms ||
      this.sendAsEmail !== this.originalCommunication.send_as_email ||
      this.sendAsPush !== this.originalCommunication.send_as_push ||
      this.requireReadReceipt !== this.originalCommunication.require_read_receipt ||
      this.allowReplies !== this.originalCommunication.allow_replies
    );
  }

  constructor() {
    super(...arguments);
    this.originalCommunication = this.model.communication;
    this.initializeFromCommunication();
    this.loadAvailableRecipients();
  }

  initializeFromCommunication() {
    if (!this.originalCommunication) return;

    this.subject = this.originalCommunication.subject || '';
    this.message = this.originalCommunication.message || '';
    this.type = this.originalCommunication.type || 'email';
    this.priority = this.originalCommunication.priority || 'normal';
    this.recipientType = this.originalCommunication.recipient_type || 'students';
    this.selectedRecipients = this.originalCommunication.recipients || [];
    this.selectedRoutes = this.originalCommunication.routes || [];
    this.customRecipients = this.originalCommunication.custom_recipients || '';
    this.scheduledSendDate = this.originalCommunication.scheduled_send_date || '';
    this.scheduledSendTime = this.originalCommunication.scheduled_send_time || '';
    this.sendAsSMS = this.originalCommunication.send_as_sms || false;
    this.sendAsEmail = this.originalCommunication.send_as_email || true;
    this.sendAsPush = this.originalCommunication.send_as_push || false;
    this.requireReadReceipt = this.originalCommunication.require_read_receipt || false;
    this.allowReplies = this.originalCommunication.allow_replies || true;
    this.personalizationVariables = this.originalCommunication.personalization_variables || {};
  }

  async loadAvailableRecipients() {
    try {
      // This would typically fetch from the backend
      // For now, using mock data
    } catch (error) {
      console.error('Error loading recipients:', error);
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
    this.hasUnsavedChanges = this.hasChanges;
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
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  toggleRecipient(recipientId, isSelected) {
    if (isSelected) {
      this.selectedRecipients = [...this.selectedRecipients, recipientId];
    } else {
      this.selectedRecipients = this.selectedRecipients.filter(id => id !== recipientId);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  toggleRoute(routeId, isSelected) {
    if (isSelected) {
      this.selectedRoutes = [...this.selectedRoutes, routeId];
    } else {
      this.selectedRoutes = this.selectedRoutes.filter(id => id !== routeId);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  selectAllRecipients() {
    if (this.selectedRecipients.length === this.filteredRecipients.length) {
      this.selectedRecipients = [];
    } else {
      this.selectedRecipients = this.filteredRecipients.map(r => r.id);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  clearRecipients() {
    this.selectedRecipients = [];
    this.selectedRoutes = [];
    this.selectedStudents = [];
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  loadTemplateAction(templateId) {
    this.useTemplate = true;
    this.selectedTemplate = templateId;
    this.loadTemplate(templateId);
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  clearTemplate() {
    this.useTemplate = false;
    this.selectedTemplate = '';
    this.subject = '';
    this.message = '';
    this.hasUnsavedChanges = this.hasChanges;
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
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  updatePersonalizationVariable(key, value) {
    this.personalizationVariables = {
      ...this.personalizationVariables,
      [key]: value
    };
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  async saveDraft() {
    if (!this.canSaveDraft) {
      this.notifications.error('Cannot save draft for sent communications');
      return;
    }

    this.isLoading = true;
    try {
      await this.updateCommunication('draft');
      this.notifications.success('Draft saved successfully');
      this.hasUnsavedChanges = false;
    } catch (error) {
      console.error('Error saving draft:', error);
      this.notifications.error('Failed to save draft');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async updateCommunication(status = null) {
    if (!this.validateCurrentStep()) {
      this.notifications.error('Please fix the validation errors before updating');
      return;
    }

    this.isLoading = true;
    try {
      const updateData = {
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

      if (status) {
        updateData.status = status;
      }

      Object.assign(this.originalCommunication, updateData);
      await this.originalCommunication.save();

      // Save as template if requested
      if (this.saveAsTemplate && this.templateName.trim()) {
        await this.saveAsTemplateAction();
      }

      this.notifications.success('Communication updated successfully');
      this.hasUnsavedChanges = false;

      if (status === 'sent') {
        this.router.transitionTo('school-transport.communications.view', this.originalCommunication.id);
      }
    } catch (error) {
      console.error('Error updating communication:', error);
      this.notifications.error('Failed to update communication');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async sendNow() {
    if (!this.canSendNow) {
      this.notifications.error('Cannot send already sent communications');
      return;
    }

    if (!confirm('Are you sure you want to send this communication now?')) {
      return;
    }

    await this.updateCommunication('sent');
  }

  @action
  async scheduleSend() {
    if (!this.canSchedule) {
      this.notifications.error('Cannot schedule already sent communications');
      return;
    }

    if (!this.scheduledSendDate || !this.scheduledSendTime) {
      this.notifications.error('Please set both date and time for scheduling');
      return;
    }

    await this.updateCommunication('scheduled');
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
  cancelEdit() {
    if (this.hasUnsavedChanges) {
      if (!confirm('You have unsaved changes. Are you sure you want to cancel?')) {
        return;
      }
    }

    this.router.transitionTo('school-transport.communications.view', this.originalCommunication.id);
  }

  @action
  async validateAndProceed() {
    if (this.validateCurrentStep()) {
      if (this.isLastStep) {
        await this.updateCommunication();
      } else {
        this.nextStep();
      }
    } else {
      this.notifications.error('Please fix the validation errors');
    }
  }

  @action
  async testSend() {
    this.notifications.info('Test send functionality coming soon');
  }

  @action
  async previewMessage() {
    this.notifications.info('Preview functionality coming soon');
  }

  @action
  async checkSpamScore() {
    this.notifications.info('Spam check functionality coming soon');
  }

  @action
  async importRecipientsFromCSV() {
    this.notifications.info('CSV import functionality coming soon');
  }

  @action
  async duplicateCommunication() {
    this.router.transitionTo('school-transport.communications.new', {
      queryParams: {
        duplicate_id: this.originalCommunication.id
      }
    });
  }

  @action
  async deleteCommunication() {
    if (!confirm('Are you sure you want to delete this communication? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      await this.originalCommunication.destroyRecord();

      this.notifications.success('Communication deleted successfully');
      this.router.transitionTo('school-transport.communications.index');
    } catch (error) {
      console.error('Error deleting communication:', error);
      this.notifications.error('Failed to delete communication');
    } finally {
      this.isLoading = false;
    }
  }
}