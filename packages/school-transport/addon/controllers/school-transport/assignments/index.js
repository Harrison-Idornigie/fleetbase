import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportAssignmentsIndexController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked searchQuery = '';
  @tracked selectedStatus = 'all';
  @tracked selectedRoute = 'all';
  @tracked selectedDriver = 'all';
  @tracked selectedDateRange = 'all';
  @tracked sortBy = 'scheduled_date';
  @tracked sortDirection = 'desc';
  @tracked currentPage = 1;
  @tracked pageSize = 25;
  @tracked showBulkActions = false;
  @tracked selectedAssignments = new Set();
  @tracked showFilters = false;
  @tracked showExportModal = false;

  get assignments() {
    return this.model.assignments || [];
  }

  get filteredAssignments() {
    let filtered = this.assignments;

    // Search filter
    if (this.searchQuery) {
      const query = this.searchQuery.toLowerCase();
      filtered = filtered.filter(assignment =>
        assignment.route_name?.toLowerCase().includes(query) ||
        assignment.driver_name?.toLowerCase().includes(query) ||
        assignment.vehicle_name?.toLowerCase().includes(query) ||
        assignment.id?.toString().includes(query)
      );
    }

    // Status filter
    if (this.selectedStatus !== 'all') {
      filtered = filtered.filter(assignment => assignment.status === this.selectedStatus);
    }

    // Route filter
    if (this.selectedRoute !== 'all') {
      filtered = filtered.filter(assignment => assignment.route_id === this.selectedRoute);
    }

    // Driver filter
    if (this.selectedDriver !== 'all') {
      filtered = filtered.filter(assignment => assignment.driver_id === this.selectedDriver);
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
        filtered = filtered.filter(assignment => {
          const assignmentDate = new Date(assignment.scheduled_date);
          return assignmentDate >= startDate;
        });
      }
    }

    return filtered;
  }

  get sortedAssignments() {
    const sorted = [...this.filteredAssignments];

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

  get paginatedAssignments() {
    const start = (this.currentPage - 1) * this.pageSize;
    const end = start + this.pageSize;
    return this.sortedAssignments.slice(start, end);
  }

  get totalPages() {
    return Math.ceil(this.sortedAssignments.length / this.pageSize);
  }

  get hasSelection() {
    return this.selectedAssignments.size > 0;
  }

  get selectedAssignmentsArray() {
    return Array.from(this.selectedAssignments);
  }

  get statusOptions() {
    return [
      { value: 'all', label: 'All Statuses' },
      { value: 'scheduled', label: 'Scheduled' },
      { value: 'in_progress', label: 'In Progress' },
      { value: 'completed', label: 'Completed' },
      { value: 'cancelled', label: 'Cancelled' },
      { value: 'delayed', label: 'Delayed' }
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
      { value: 'scheduled_date', label: 'Scheduled Date' },
      { value: 'route_name', label: 'Route Name' },
      { value: 'driver_name', label: 'Driver Name' },
      { value: 'status', label: 'Status' },
      { value: 'created_at', label: 'Created Date' }
    ];
  }

  get assignmentStats() {
    const assignments = this.assignments;
    return {
      total: assignments.length,
      scheduled: assignments.filter(a => a.status === 'scheduled').length,
      inProgress: assignments.filter(a => a.status === 'in_progress').length,
      completed: assignments.filter(a => a.status === 'completed').length,
      cancelled: assignments.filter(a => a.status === 'cancelled').length,
      onTime: assignments.filter(a => a.on_time).length,
      delayed: assignments.filter(a => a.status === 'delayed').length
    };
  }

  get availableRoutes() {
    const routes = [...new Set(this.assignments.map(a => a.route_id))];
    return routes.map(routeId => {
      const assignment = this.assignments.find(a => a.route_id === routeId);
      return {
        id: routeId,
        name: assignment?.route_name || `Route ${routeId}`
      };
    });
  }

  get availableDrivers() {
    const drivers = [...new Set(this.assignments.map(a => a.driver_id))];
    return drivers.map(driverId => {
      const assignment = this.assignments.find(a => a.driver_id === driverId);
      return {
        id: driverId,
        name: assignment?.driver_name || `Driver ${driverId}`
      };
    });
  }

  @action
  updateSearch(query) {
    this.searchQuery = query;
    this.currentPage = 1;
    this.selectedAssignments.clear();
  }

  @action
  updateStatusFilter(status) {
    this.selectedStatus = status;
    this.currentPage = 1;
    this.selectedAssignments.clear();
  }

  @action
  updateRouteFilter(route) {
    this.selectedRoute = route;
    this.currentPage = 1;
    this.selectedAssignments.clear();
  }

  @action
  updateDriverFilter(driver) {
    this.selectedDriver = driver;
    this.currentPage = 1;
    this.selectedAssignments.clear();
  }

  @action
  updateDateRangeFilter(dateRange) {
    this.selectedDateRange = dateRange;
    this.currentPage = 1;
    this.selectedAssignments.clear();
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
    this.selectedAssignments.clear();
  }

  @action
  toggleAssignmentSelection(assignmentId, isSelected) {
    if (isSelected) {
      this.selectedAssignments.add(assignmentId);
    } else {
      this.selectedAssignments.delete(assignmentId);
    }
  }

  @action
  selectAllAssignments() {
    if (this.selectedAssignments.size === this.paginatedAssignments.length) {
      this.selectedAssignments.clear();
    } else {
      this.paginatedAssignments.forEach(assignment => {
        this.selectedAssignments.add(assignment.id);
      });
    }
  }

  @action
  clearSelection() {
    this.selectedAssignments.clear();
  }

  @action
  toggleFilters() {
    this.showFilters = !this.showFilters;
  }

  @action
  resetFilters() {
    this.searchQuery = '';
    this.selectedStatus = 'all';
    this.selectedRoute = 'all';
    this.selectedDriver = 'all';
    this.selectedDateRange = 'all';
    this.sortBy = 'scheduled_date';
    this.sortDirection = 'desc';
    this.currentPage = 1;
    this.selectedAssignments.clear();
  }

  @action
  createAssignment() {
    this.router.transitionTo('school-transport.assignments.new');
  }

  @action
  viewAssignment(assignmentId) {
    this.router.transitionTo('school-transport.assignments.view', assignmentId);
  }

  @action
  editAssignment(assignmentId) {
    this.router.transitionTo('school-transport.assignments.edit', assignmentId);
  }

  @action
  async startAssignment(assignmentId) {
    this.isLoading = true;
    try {
      const assignment = this.assignments.find(a => a.id === assignmentId);
      if (assignment) {
        assignment.status = 'in_progress';
        assignment.started_at = new Date().toISOString();
        await assignment.save();
        this.notifications.success('Assignment started successfully');
        this.router.refresh();
      }
    } catch (error) {
      console.error('Error starting assignment:', error);
      this.notifications.error('Failed to start assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async completeAssignment(assignmentId) {
    this.isLoading = true;
    try {
      const assignment = this.assignments.find(a => a.id === assignmentId);
      if (assignment) {
        assignment.status = 'completed';
        assignment.completed_at = new Date().toISOString();
        await assignment.save();
        this.notifications.success('Assignment completed successfully');
        this.router.refresh();
      }
    } catch (error) {
      console.error('Error completing assignment:', error);
      this.notifications.error('Failed to complete assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async cancelAssignment(assignmentId) {
    if (!confirm('Are you sure you want to cancel this assignment?')) {
      return;
    }

    this.isLoading = true;
    try {
      const assignment = this.assignments.find(a => a.id === assignmentId);
      if (assignment) {
        assignment.status = 'cancelled';
        await assignment.save();
        this.notifications.success('Assignment cancelled successfully');
        this.router.refresh();
      }
    } catch (error) {
      console.error('Error cancelling assignment:', error);
      this.notifications.error('Failed to cancel assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async deleteAssignment(assignmentId) {
    if (!confirm('Are you sure you want to delete this assignment? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      const assignment = this.assignments.find(a => a.id === assignmentId);
      if (assignment) {
        await assignment.destroyRecord();
        this.notifications.success('Assignment deleted successfully');
        this.selectedAssignments.delete(assignmentId);
        this.router.refresh();
      }
    } catch (error) {
      console.error('Error deleting assignment:', error);
      this.notifications.error('Failed to delete assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async bulkUpdateStatus(newStatus) {
    if (this.selectedAssignments.size === 0) return;

    this.isLoading = true;
    try {
      const updatePromises = this.selectedAssignmentsArray.map(assignmentId => {
        const assignment = this.assignments.find(a => a.id === assignmentId);
        if (assignment) {
          assignment.status = newStatus;
          if (newStatus === 'in_progress' && !assignment.started_at) {
            assignment.started_at = new Date().toISOString();
          } else if (newStatus === 'completed' && !assignment.completed_at) {
            assignment.completed_at = new Date().toISOString();
          }
          return assignment.save();
        }
        return Promise.resolve();
      });

      await Promise.all(updatePromises);
      this.notifications.success(`${this.selectedAssignments.size} assignment(s) updated successfully`);
      this.selectedAssignments.clear();
      this.router.refresh();
    } catch (error) {
      console.error('Error bulk updating assignments:', error);
      this.notifications.error('Failed to update some assignments');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async bulkDeleteAssignments() {
    if (this.selectedAssignments.size === 0) return;

    const count = this.selectedAssignments.size;
    if (!confirm(`Are you sure you want to delete ${count} assignment(s)? This action cannot be undone.`)) {
      return;
    }

    this.isLoading = true;
    try {
      const deletePromises = this.selectedAssignmentsArray.map(assignmentId => {
        const assignment = this.assignments.find(a => a.id === assignmentId);
        return assignment ? assignment.destroyRecord() : Promise.resolve();
      });

      await Promise.all(deletePromises);
      this.notifications.success(`${count} assignment(s) deleted successfully`);
      this.selectedAssignments.clear();
      this.router.refresh();
    } catch (error) {
      console.error('Error bulk deleting assignments:', error);
      this.notifications.error('Failed to delete some assignments');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async exportAssignments() {
    this.isLoading = true;
    try {
      const exportData = {
        assignments: this.selectedAssignments.size > 0 ? this.selectedAssignmentsArray : this.sortedAssignments.map(a => a.id),
        format: 'csv'
      };

      const response = await fetch('/api/school-transport/assignments/export', {
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
        a.download = 'assignments-export.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Assignments exported successfully');
      } else {
        throw new Error('Export failed');
      }
    } catch (error) {
      console.error('Error exporting assignments:', error);
      this.notifications.error('Failed to export assignments');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async generateBulkReports() {
    if (this.selectedAssignments.size === 0) return;

    this.isLoading = true;
    try {
      const response = await fetch('/api/school-transport/assignments/bulk-report', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          assignment_ids: this.selectedAssignmentsArray
        })
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'bulk-assignment-report.pdf';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Bulk report generated successfully');
      } else {
        throw new Error('Report generation failed');
      }
    } catch (error) {
      console.error('Error generating bulk report:', error);
      this.notifications.error('Failed to generate bulk report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  toggleExportModal() {
    this.showExportModal = !this.showExportModal;
  }

  @action
  refreshData() {
    this.router.refresh();
  }

  @action
  async sendBulkNotifications(type) {
    if (this.selectedAssignments.size === 0) return;

    this.isLoading = true;
    try {
      const response = await fetch('/api/school-transport/assignments/bulk-notify', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          assignment_ids: this.selectedAssignmentsArray,
          notification_type: type
        })
      });

      if (response.ok) {
        this.notifications.success(`Notifications sent to ${this.selectedAssignments.size} assignment(s)`);
      } else {
        throw new Error('Notification failed');
      }
    } catch (error) {
      console.error('Error sending bulk notifications:', error);
      this.notifications.error('Failed to send notifications');
    } finally {
      this.isLoading = false;
    }
  }
}