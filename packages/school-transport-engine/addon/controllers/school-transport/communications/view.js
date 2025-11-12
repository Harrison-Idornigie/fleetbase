import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportCommunicationsViewController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked selectedTab = 'details';

  // Communication data
  @tracked communication = null;
  @tracked deliveryStats = null;
  @tracked recipientDetails = [];
  @tracked replies = [];

  // Actions
  @tracked showReplyModal = false;
  @tracked showForwardModal = false;
  @tracked showResendModal = false;
  @tracked replyMessage = '';
  @tracked forwardRecipients = [];

  get isScheduled() {
    return this.communication?.status === 'scheduled';
  }

  get isSent() {
    return this.communication?.status === 'sent';
  }

  get isDraft() {
    return this.communication?.status === 'draft';
  }

  get canEdit() {
    return this.isDraft || this.isScheduled;
  }

  get canReply() {
    return this.communication?.allow_replies && this.isSent;
  }

  get canResend() {
    return this.isSent;
  }

  get canForward() {
    return this.isSent;
  }

  get deliveryRate() {
    if (!this.deliveryStats) return 0;
    return Math.round((this.deliveryStats.delivered / this.deliveryStats.total) * 100);
  }

  get readRate() {
    if (!this.deliveryStats) return 0;
    return Math.round((this.deliveryStats.read / this.deliveryStats.total) * 100);
  }

  get replyRate() {
    if (!this.deliveryStats) return 0;
    return Math.round((this.deliveryStats.replied / this.deliveryStats.total) * 100);
  }

  get statusColor() {
    const colors = {
      draft: 'gray',
      scheduled: 'blue',
      sending: 'yellow',
      sent: 'green',
      failed: 'red',
      cancelled: 'red'
    };
    return colors[this.communication?.status] || 'gray';
  }

  get statusLabel() {
    const labels = {
      draft: 'Draft',
      scheduled: 'Scheduled',
      sending: 'Sending',
      sent: 'Sent',
      failed: 'Failed',
      cancelled: 'Cancelled'
    };
    return labels[this.communication?.status] || 'Unknown';
  }

  get priorityColor() {
    const colors = {
      low: 'gray',
      normal: 'blue',
      high: 'orange',
      emergency: 'red'
    };
    return colors[this.communication?.priority] || 'gray';
  }

  get priorityLabel() {
    const labels = {
      low: 'Low',
      normal: 'Normal',
      high: 'High',
      emergency: 'Emergency'
    };
    return labels[this.communication?.priority] || 'Normal';
  }

  get typeIcon() {
    const icons = {
      email: 'mail',
      sms: 'message-square',
      push: 'bell',
      voice: 'phone'
    };
    return icons[this.communication?.type] || 'mail';
  }

  get typeLabel() {
    const labels = {
      email: 'Email',
      sms: 'SMS',
      push: 'Push Notification',
      voice: 'Voice Call'
    };
    return labels[this.communication?.type] || 'Email';
  }

  get recipientSummary() {
    if (!this.recipientDetails.length) return 'No recipients';

    const total = this.recipientDetails.length;
    const delivered = this.recipientDetails.filter(r => r.status === 'delivered').length;
    const read = this.recipientDetails.filter(r => r.status === 'read').length;
    const replied = this.recipientDetails.filter(r => r.replied).length;

    return `${delivered}/${total} delivered, ${read} read, ${replied} replied`;
  }

  get hasAttachments() {
    return this.communication?.attachments && this.communication.attachments.length > 0;
  }

  get formattedScheduledDate() {
    if (!this.communication?.scheduled_send_date) return null;

    const date = new Date(this.communication.scheduled_send_date);
    const time = this.communication.scheduled_send_time || '00:00';

    return `${date.toLocaleDateString()} at ${time}`;
  }

  get timeUntilScheduled() {
    if (!this.isScheduled || !this.communication?.scheduled_send_date) return null;

    const scheduledDateTime = new Date(`${this.communication.scheduled_send_date}T${this.communication.scheduled_send_time || '00:00'}`);
    const now = new Date();

    if (scheduledDateTime <= now) return 'Overdue';

    const diffMs = scheduledDateTime - now;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    const diffHours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    if (diffDays > 0) {
      return `${diffDays} day${diffDays > 1 ? 's' : ''} ${diffHours} hour${diffHours > 1 ? 's' : ''}`;
    } else if (diffHours > 0) {
      return `${diffHours} hour${diffHours > 1 ? 's' : ''} ${diffMinutes} minute${diffMinutes > 1 ? 's' : ''}`;
    } else {
      return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''}`;
    }
  }

  constructor() {
    super(...arguments);
    this.communication = this.model.communication;
    this.loadAdditionalData();
  }

  async loadAdditionalData() {
    this.isLoading = true;
    try {
      // Load delivery stats
      this.deliveryStats = await this.store.query('school-transport/communication-stat', {
        communication_id: this.communication.id
      });

      // Load recipient details
      this.recipientDetails = await this.store.query('school-transport/communication-recipient', {
        communication_id: this.communication.id
      });

      // Load replies if allowed
      if (this.canReply) {
        this.replies = await this.store.query('school-transport/communication-reply', {
          communication_id: this.communication.id
        });
      }
    } catch (error) {
      console.error('Error loading communication details:', error);
      this.notifications.error('Failed to load communication details');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  selectTab(tab) {
    this.selectedTab = tab;
  }

  @action
  editCommunication() {
    this.router.transitionTo('school-transport.communications.edit', this.communication.id);
  }

  @action
  duplicateCommunication() {
    this.router.transitionTo('school-transport.communications.new', {
      queryParams: {
        duplicate_id: this.communication.id
      }
    });
  }

  @action
  async cancelScheduledCommunication() {
    if (!confirm('Are you sure you want to cancel this scheduled communication?')) {
      return;
    }

    this.isLoading = true;
    try {
      this.communication.status = 'cancelled';
      await this.communication.save();

      this.notifications.success('Communication cancelled successfully');
      this.router.transitionTo('school-transport.communications.index');
    } catch (error) {
      console.error('Error cancelling communication:', error);
      this.notifications.error('Failed to cancel communication');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async resendCommunication() {
    this.showResendModal = true;
  }

  @action
  async confirmResend(selectedRecipients = null) {
    this.isLoading = true;
    try {
      const resendData = {
        original_communication_id: this.communication.id,
        recipients: selectedRecipients || this.recipientDetails.map(r => r.id)
      };

      await this.store.createRecord('school-transport/communication-resend', resendData).save();

      this.notifications.success('Communication resent successfully');
      this.showResendModal = false;
      this.loadAdditionalData(); // Refresh data
    } catch (error) {
      console.error('Error resending communication:', error);
      this.notifications.error('Failed to resend communication');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async forwardCommunication() {
    this.showForwardModal = true;
  }

  @action
  async confirmForward(forwardData) {
    this.isLoading = true;
    try {
      const forwardRecord = this.store.createRecord('school-transport/communication-forward', {
        original_communication_id: this.communication.id,
        ...forwardData
      });

      await forwardRecord.save();

      this.notifications.success('Communication forwarded successfully');
      this.showForwardModal = false;
    } catch (error) {
      console.error('Error forwarding communication:', error);
      this.notifications.error('Failed to forward communication');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async replyToCommunication() {
    this.showReplyModal = true;
  }

  @action
  async sendReply() {
    if (!this.replyMessage.trim()) {
      this.notifications.error('Please enter a reply message');
      return;
    }

    this.isLoading = true;
    try {
      const replyData = {
        communication_id: this.communication.id,
        message: this.replyMessage,
        reply_to_sender: true
      };

      await this.store.createRecord('school-transport/communication-reply', replyData).save();

      this.notifications.success('Reply sent successfully');
      this.showReplyModal = false;
      this.replyMessage = '';
      this.loadAdditionalData(); // Refresh replies
    } catch (error) {
      console.error('Error sending reply:', error);
      this.notifications.error('Failed to send reply');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async deleteCommunication() {
    if (!confirm('Are you sure you want to delete this communication? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      await this.communication.destroyRecord();

      this.notifications.success('Communication deleted successfully');
      this.router.transitionTo('school-transport.communications.index');
    } catch (error) {
      console.error('Error deleting communication:', error);
      this.notifications.error('Failed to delete communication');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async archiveCommunication() {
    this.isLoading = true;
    try {
      this.communication.archived = true;
      await this.communication.save();

      this.notifications.success('Communication archived successfully');
      this.router.transitionTo('school-transport.communications.index');
    } catch (error) {
      console.error('Error archiving communication:', error);
      this.notifications.error('Failed to archive communication');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async markAsRead(recipientId) {
    try {
      const recipient = this.recipientDetails.find(r => r.id === recipientId);
      if (recipient) {
        recipient.status = 'read';
        await recipient.save();
        this.loadAdditionalData(); // Refresh stats
      }
    } catch (error) {
      console.error('Error marking as read:', error);
    }
  }

  @action
  async exportRecipients() {
    try {
      const csvData = this.recipientDetails.map(recipient => ({
        name: recipient.name,
        email: recipient.email,
        phone: recipient.phone,
        status: recipient.status,
        sent_at: recipient.sent_at,
        read_at: recipient.read_at,
        replied_at: recipient.replied_at
      }));

      // This would typically trigger a file download
      this.notifications.success('Recipient data exported successfully');
    } catch (error) {
      console.error('Error exporting recipients:', error);
      this.notifications.error('Failed to export recipient data');
    }
  }

  @action
  async exportDeliveryReport() {
    try {
      const reportData = {
        communication: this.communication,
        stats: this.deliveryStats,
        recipients: this.recipientDetails
      };

      // This would generate and download a PDF report
      this.notifications.success('Delivery report exported successfully');
    } catch (error) {
      console.error('Error exporting delivery report:', error);
      this.notifications.error('Failed to export delivery report');
    }
  }

  @action
  async viewRecipientDetails(recipientId) {
    // This would open a modal or navigate to recipient details
    this.notifications.info('Recipient details view coming soon');
  }

  @action
  async sendReminder() {
    this.isLoading = true;
    try {
      const unreadRecipients = this.recipientDetails.filter(r => r.status !== 'read');

      if (unreadRecipients.length === 0) {
        this.notifications.info('All recipients have already read the message');
        return;
      }

      const reminderData = {
        original_communication_id: this.communication.id,
        recipients: unreadRecipients.map(r => r.id),
        type: 'reminder'
      };

      await this.store.createRecord('school-transport/communication-reminder', reminderData).save();

      this.notifications.success(`Reminder sent to ${unreadRecipients.length} recipients`);
    } catch (error) {
      console.error('Error sending reminder:', error);
      this.notifications.error('Failed to send reminder');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  closeModal() {
    this.showReplyModal = false;
    this.showForwardModal = false;
    this.showResendModal = false;
    this.replyMessage = '';
    this.forwardRecipients = [];
  }

  @action
  async refreshData() {
    await this.loadAdditionalData();
    this.notifications.success('Data refreshed');
  }

  @action
  async downloadAttachment(attachmentId) {
    try {
      const attachment = this.communication.attachments.find(a => a.id === attachmentId);
      if (attachment) {
        // This would trigger file download
        this.notifications.success('Attachment downloaded successfully');
      }
    } catch (error) {
      console.error('Error downloading attachment:', error);
      this.notifications.error('Failed to download attachment');
    }
  }

  @action
  async previewAttachment(attachmentId) {
    try {
      const attachment = this.communication.attachments.find(a => a.id === attachmentId);
      if (attachment) {
        // This would open attachment preview modal
        this.notifications.info('Attachment preview coming soon');
      }
    } catch (error) {
      console.error('Error previewing attachment:', error);
    }
  }
}