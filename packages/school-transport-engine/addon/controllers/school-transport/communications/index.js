import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportCommunicationsIndexController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked searchQuery = '';
  @tracked selectedType = 'all';
  @tracked selectedStatus = 'all';
  @tracked selectedRecipientType = 'all';
  @tracked selectedDateRange = 'all';
  @tracked sortBy = 'created_at';
  @tracked sortDirection = 'desc';
  @tracked currentPage = 1;
  @tracked pageSize = 25;
  @tracked showBulkActions = false;
  @tracked selectedCommunications = new Set();
  @tracked showFilters = false;
  @tracked showComposeModal = false;
  @tracked showTemplatesModal = false;

  get communications() {
    return this.model.communications || [];
  }

  get filteredCommunications() {
    let filtered = this.communications;

    // Search filter
    if (this.searchQuery) {
      const query = this.searchQuery.toLowerCase();
      filtered = filtered.filter(comm =>
        comm.subject?.toLowerCase().includes(query) ||
        comm.message?.toLowerCase().includes(query) ||
        comm.recipient_name?.toLowerCase().includes(query) ||
        comm.sender_name?.toLowerCase().includes(query)
      );
    }

    // Type filter
    if (this.selectedType !== 'all') {
      filtered = filtered.filter(comm => comm.type === this.selectedType);
    }

    // Status filter
    if (this.selectedStatus !== 'all') {
      filtered = filtered.filter(comm => comm.status === this.selectedStatus);
    }

    // Recipient type filter
    if (this.selectedRecipientType !== 'all') {
      filtered = filtered.filter(comm => comm.recipient_type === this.selectedRecipientType);
    }

    // Date range filter
    if (this.selectedDateRange !== 'all') {
      const now = new Date();
      let startDate;

      switch (this.selectedDateRange) {
        case 'today':
          startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
          break;
        case 'week':
          startDate = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
          break;
        case 'month':
          startDate = new Date(now.getFullYear(), now.getMonth(), 1);
          break;
        default:
          startDate = null;
      }

      if (startDate) {
        filtered = filtered.filter(comm => {
          const commDate = new Date(comm.created_at);
          return commDate >= startDate;
        });
      }
    }

    return filtered;
  }

  get sortedCommunications() {
    const sorted = [...this.filteredCommunications];

    sorted.sort((a, b) => {
      let aValue = a[this.sortBy];
      let bValue = b[this.sortBy];

      if (aValue == null && bValue == null) return 0;
      if (aValue == null) return 1;
      if (bValue == null) return -1;

      if (typeof aValue === 'string') aValue = aValue.toLowerCase();
      if (typeof bValue === 'string') bValue = bValue.toLowerCase();

      if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
      if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
      return 0;
    });

    return sorted;
  }

  get paginatedCommunications() {
    const start = (this.currentPage - 1) * this.pageSize;
    const end = start + this.pageSize;
    return this.sortedCommunications.slice(start, end);
  }

  get totalPages() {
    return Math.ceil(this.sortedCommunications.length / this.pageSize);
  }

  get hasSelection() {
    return this.selectedCommunications.size > 0;
  }

  get selectedCommunicationsArray() {
    return Array.from(this.selectedCommunications);
  }

  get typeOptions() {
    return [
      { value: 'all', label: 'All Types' },
      { value: 'email', label: 'Email' },
      { value: 'sms', label: 'SMS' },
      { value: 'push', label: 'Push Notification' },
      { value: 'voice', label: 'Voice Call' }
    ];
  }

  get statusOptions() {
    return [
      { value: 'all', label: 'All Statuses' },
      { value: 'sent', label: 'Sent' },
      { value: 'delivered', label: 'Delivered' },
      { value: 'read', label: 'Read' },
      { value: 'failed', label: 'Failed' },
      { value: 'pending', label: 'Pending' }
    ];
  }

  get recipientTypeOptions() {
    return [
      { value: 'all', label: 'All Recipients' },
      { value: 'student', label: 'Students' },
      { value: 'parent', label: 'Parents' },
      { value: 'driver', label: 'Drivers' },
      { value: 'staff', label: 'Staff' },
      { value: 'all_parents', label: 'All Parents' },
      { value: 'route_parents', label: 'Route Parents' }
    ];
  }

  get dateRangeOptions() {
    return [
      { value: 'all', label: 'All Dates' },
      { value: 'today', label: 'Today' },
      { value: 'week', label: 'This Week' },
      { value: 'month', label: 'This Month' }
    ];
  }

  get sortOptions() {
    return [
      { value: 'created_at', label: 'Date Sent' },
      { value: 'subject', label: 'Subject' },
      { value: 'recipient_name', label: 'Recipient' },
      { value: 'type', label: 'Type' },
      { value: 'status', label: 'Status' }
    ];
  }

  get communicationStats() {
    const comms = this.communications;
    return {
      total: comms.length,
      sent: comms.filter(c => c.status === 'sent' || c.status === 'delivered' || c.status === 'read').length,
      failed: comms.filter(c => c.status === 'failed').length,
      pending: comms.filter(c => c.status === 'pending').length,
      emails: comms.filter(c => c.type === 'email').length,
      sms: comms.filter(c => c.type === 'sms').length,
      read: comms.filter(c => c.status === 'read').length
    };
  }

  get recentCommunications() {
    return this.sortedCommunications.slice(0, 5);
  }

  @action
  updateSearch(query) {
    this.searchQuery = query;
    this.currentPage = 1;
    this.selectedCommunications.clear();
  }

  @action
  updateTypeFilter(type) {
    this.selectedType = type;
    this.currentPage = 1;
    this.selectedCommunications.clear();
  }

  @action
  updateStatusFilter(status) {
    this.selectedStatus = status;
    this.currentPage = 1;
    this.selectedCommunications.clear();
  }

  @action
  updateRecipientTypeFilter(recipientType) {
    this.selectedRecipientType = recipientType;
    this.currentPage = 1;
    this.selectedCommunications.clear();
  }

  @action
  updateDateRangeFilter(dateRange) {
    this.selectedDateRange = dateRange;
    this.currentPage = 1;
    this.selectedCommunications.clear();
  }

  @action
  updateSort(sortBy) {
    if (this.sortBy === sortBy) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortBy = sortBy;
      this.sortDirection = 'asc';
    }
    this.currentPage = 1;
  }

  @action
  goToPage(page) {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
    }
  }

  @action
  updatePageSize(size) {
    this.pageSize = size;
    this.currentPage = 1;
    this.selectedCommunications.clear();
  }

  @action
  toggleCommunicationSelection(commId, isSelected) {
    if (isSelected) {
      this.selectedCommunications.add(commId);
    } else {
      this.selectedCommunications.delete(commId);
    }
  }

  @action
  selectAllCommunications() {
    if (this.selectedCommunications.size === this.paginatedCommunications.length) {
      this.selectedCommunications.clear();
    } else {
      this.paginatedCommunications.forEach(comm => {
        this.selectedCommunications.add(comm.id);
      });
    }
  }

  @action
  clearSelection() {
    this.selectedCommunications.clear();
  }

  @action
  toggleFilters() {
    this.showFilters = !this.showFilters;
  }

  @action
  resetFilters() {
    this.searchQuery = '';
    this.selectedType = 'all';
    this.selectedStatus = 'all';
    this.selectedRecipientType = 'all';
    this.selectedDateRange = 'all';
    this.sortBy = 'created_at';
    this.sortDirection = 'desc';
    this.currentPage = 1;
    this.selectedCommunications.clear();
  }

  @action
  composeMessage() {
    this.router.transitionTo('school-transport.communications.new');
  }

  @action
  viewCommunication(commId) {
    this.router.transitionTo('school-transport.communications.view', commId);
  }

  @action
  editCommunication(commId) {
    this.router.transitionTo('school-transport.communications.edit', commId);
  }

  @action
  async resendCommunication(commId) {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/communications/${commId}/resend`, {
        method: 'POST'
      });

      if (response.ok) {
        this.notifications.success('Communication resent successfully');
        this.router.refresh();
      } else {
        throw new Error('Resend failed');
      }
    } catch (error) {
      console.error('Error resending communication:', error);
      this.notifications.error('Failed to resend communication');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async deleteCommunication(commId) {
    if (!confirm('Are you sure you want to delete this communication? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      const comm = this.communications.find(c => c.id === commId);
      if (comm) {
        await comm.destroyRecord();
        this.notifications.success('Communication deleted successfully');
        this.selectedCommunications.delete(commId);
        this.router.refresh();
      }
    } catch (error) {
      console.error('Error deleting communication:', error);
      this.notifications.error('Failed to delete communication');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async bulkDeleteCommunications() {
    if (this.selectedCommunications.size === 0) return;

    const count = this.selectedCommunications.size;
    if (!confirm(`Are you sure you want to delete ${count} communication(s)? This action cannot be undone.`)) {
      return;
    }

    this.isLoading = true;
    try {
      const deletePromises = this.selectedCommunicationsArray.map(commId => {
        const comm = this.communications.find(c => c.id === commId);
        return comm ? comm.destroyRecord() : Promise.resolve();
      });

      await Promise.all(deletePromises);
      this.notifications.success(`${count} communication(s) deleted successfully`);
      this.selectedCommunications.clear();
      this.router.refresh();
    } catch (error) {
      console.error('Error bulk deleting communications:', error);
      this.notifications.error('Failed to delete some communications');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async bulkResendCommunications() {
    if (this.selectedCommunications.size === 0) return;

    this.isLoading = true;
    try {
      const resendPromises = this.selectedCommunicationsArray.map(commId =>
        fetch(`/api/school-transport/communications/${commId}/resend`, {
          method: 'POST'
        })
      );

      await Promise.all(resendPromises);
      this.notifications.success(`${this.selectedCommunications.size} communication(s) resent successfully`);
      this.selectedCommunications.clear();
      this.router.refresh();
    } catch (error) {
      console.error('Error bulk resending communications:', error);
      this.notifications.error('Failed to resend some communications');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async exportCommunications() {
    this.isLoading = true;
    try {
      const exportData = {
        communications: this.selectedCommunications.size > 0 ? this.selectedCommunicationsArray : this.sortedCommunications.map(c => c.id),
        format: 'csv'
      };

      const response = await fetch('/api/school-transport/communications/export', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(exportData)
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'communications-export.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Communications exported successfully');
      } else {
        throw new Error('Export failed');
      }
    } catch (error) {
      console.error('Error exporting communications:', error);
      this.notifications.error('Failed to export communications');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async sendBulkNotifications(type) {
    if (this.selectedCommunications.size === 0) return;

    this.isLoading = true;
    try {
      const response = await fetch('/api/school-transport/communications/bulk-notify', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          communication_ids: this.selectedCommunicationsArray,
          notification_type: type
        })
      });

      if (response.ok) {
        this.notifications.success(`Notifications sent for ${this.selectedCommunications.size} communication(s)`);
      } else {
        throw new Error('Bulk notification failed');
      }
    } catch (error) {
      console.error('Error sending bulk notifications:', error);
      this.notifications.error('Failed to send bulk notifications');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  showTemplates() {
    this.router.transitionTo('school-transport.communications.templates');
  }

  @action
  async createFromTemplate(templateId) {
    this.router.transitionTo('school-transport.communications.new', {
      queryParams: { template_id: templateId }
    });
  }

  @action
  async sendEmergencyAlert() {
    this.router.transitionTo('school-transport.communications.new', {
      queryParams: { type: 'emergency' }
    });
  }

  @action
  async sendDelayNotification() {
    this.router.transitionTo('school-transport.communications.new', {
      queryParams: { type: 'delay' }
    });
  }

  @action
  async sendRouteChangeNotification() {
    this.router.transitionTo('school-transport.communications.new', {
      queryParams: { type: 'route_change' }
    });
  }

  @action
  toggleComposeModal() {
    this.showComposeModal = !this.showComposeModal;
  }

  @action
  toggleTemplatesModal() {
    this.showTemplatesModal = !this.showTemplatesModal;
  }

  @action
  refreshData() {
    this.router.refresh();
  }

  @action
  async markAsRead(commId) {
    this.isLoading = true;
    try {
      const comm = this.communications.find(c => c.id === commId);
      if (comm && comm.status !== 'read') {
        comm.status = 'read';
        await comm.save();
        this.notifications.success('Marked as read');
        this.router.refresh();
      }
    } catch (error) {
      console.error('Error marking as read:', error);
      this.notifications.error('Failed to mark as read');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async archiveCommunication(commId) {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/communications/${commId}/archive`, {
        method: 'POST'
      });

      if (response.ok) {
        this.notifications.success('Communication archived successfully');
        this.router.refresh();
      } else {
        throw new Error('Archive failed');
      }
    } catch (error) {
      console.error('Error archiving communication:', error);
      this.notifications.error('Failed to archive communication');
    } finally {
      this.isLoading = false;
    }
  }
}