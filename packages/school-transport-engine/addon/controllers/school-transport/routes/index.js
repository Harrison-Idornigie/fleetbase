import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportRoutesIndexController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked searchQuery = '';
  @tracked selectedStatus = 'all';
  @tracked selectedType = 'all';
  @tracked sortBy = 'name';
  @tracked sortDirection = 'asc';
  @tracked currentPage = 1;
  @tracked pageSize = 25;
  @tracked showBulkActions = false;
  @tracked selectedRoutes = new Set();
  @tracked showFilters = false;
  @tracked showExportModal = false;

  get routes() {
    return this.model.routes || [];
  }

  get filteredRoutes() {
    let filtered = this.routes;

    // Search filter
    if (this.searchQuery) {
      const query = this.searchQuery.toLowerCase();
      filtered = filtered.filter(route =>
        route.name?.toLowerCase().includes(query) ||
        route.description?.toLowerCase().includes(query) ||
        route.route_number?.toString().includes(query)
      );
    }

    // Status filter
    if (this.selectedStatus !== 'all') {
      filtered = filtered.filter(route => route.status === this.selectedStatus);
    }

    // Type filter
    if (this.selectedType !== 'all') {
      filtered = filtered.filter(route => route.type === this.selectedType);
    }

    return filtered;
  }

  get sortedRoutes() {
    const sorted = [...this.filteredRoutes];

    sorted.sort((a, b) => {
      let aValue = a[this.sortBy];
      let bValue = b[this.sortBy];

      // Handle null/undefined values
      if (aValue == null && bValue == null) return 0;
      if (aValue == null) return 1;
      if (bValue == null) return -1;

      // Convert to strings for comparison if needed
      if (typeof aValue === 'string') aValue = aValue.toLowerCase();
      if (typeof bValue === 'string') bValue = bValue.toLowerCase();

      if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
      if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
      return 0;
    });

    return sorted;
  }

  get paginatedRoutes() {
    const start = (this.currentPage - 1) * this.pageSize;
    const end = start + this.pageSize;
    return this.sortedRoutes.slice(start, end);
  }

  get totalPages() {
    return Math.ceil(this.sortedRoutes.length / this.pageSize);
  }

  get hasSelection() {
    return this.selectedRoutes.size > 0;
  }

  get selectedRoutesArray() {
    return Array.from(this.selectedRoutes);
  }

  get statusOptions() {
    return [
      { value: 'all', label: 'All Statuses' },
      { value: 'active', label: 'Active' },
      { value: 'inactive', label: 'Inactive' },
      { value: 'maintenance', label: 'Maintenance' },
      { value: 'cancelled', label: 'Cancelled' }
    ];
  }

  get typeOptions() {
    return [
      { value: 'all', label: 'All Types' },
      { value: 'morning', label: 'Morning Pickup' },
      { value: 'afternoon', label: 'Afternoon Drop-off' },
      { value: 'special', label: 'Special Route' },
      { value: 'field_trip', label: 'Field Trip' }
    ];
  }

  get sortOptions() {
    return [
      { value: 'name', label: 'Route Name' },
      { value: 'route_number', label: 'Route Number' },
      { value: 'status', label: 'Status' },
      { value: 'type', label: 'Type' },
      { value: 'created_at', label: 'Created Date' },
      { value: 'updated_at', label: 'Last Updated' }
    ];
  }

  get routeStats() {
    const routes = this.routes;
    return {
      total: routes.length,
      active: routes.filter(r => r.status === 'active').length,
      inactive: routes.filter(r => r.status === 'inactive').length,
      morning: routes.filter(r => r.type === 'morning').length,
      afternoon: routes.filter(r => r.type === 'afternoon').length
    };
  }

  @action
  updateSearch(query) {
    this.searchQuery = query;
    this.currentPage = 1; // Reset to first page
    this.selectedRoutes.clear(); // Clear selection
  }

  @action
  updateStatusFilter(status) {
    this.selectedStatus = status;
    this.currentPage = 1;
    this.selectedRoutes.clear();
  }

  @action
  updateTypeFilter(type) {
    this.selectedType = type;
    this.currentPage = 1;
    this.selectedRoutes.clear();
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
    this.selectedRoutes.clear();
  }

  @action
  toggleRouteSelection(routeId, isSelected) {
    if (isSelected) {
      this.selectedRoutes.add(routeId);
    } else {
      this.selectedRoutes.delete(routeId);
    }
  }

  @action
  selectAllRoutes() {
    if (this.selectedRoutes.size === this.paginatedRoutes.length) {
      this.selectedRoutes.clear();
    } else {
      this.paginatedRoutes.forEach(route => {
        this.selectedRoutes.add(route.id);
      });
    }
  }

  @action
  clearSelection() {
    this.selectedRoutes.clear();
  }

  @action
  toggleFilters() {
    this.showFilters = !this.showFilters;
  }

  @action
  resetFilters() {
    this.searchQuery = '';
    this.selectedStatus = 'all';
    this.selectedType = 'all';
    this.sortBy = 'name';
    this.sortDirection = 'asc';
    this.currentPage = 1;
    this.selectedRoutes.clear();
  }

  @action
  createRoute() {
    this.router.transitionTo('school-transport.routes.new');
  }

  @action
  viewRoute(routeId) {
    this.router.transitionTo('school-transport.routes.view', routeId);
  }

  @action
  editRoute(routeId) {
    this.router.transitionTo('school-transport.routes.edit', routeId);
  }

  @action
  async duplicateRoute(routeId) {
    this.isLoading = true;
    try {
      const originalRoute = this.routes.find(r => r.id === routeId);
      if (!originalRoute) return;

      const duplicatedRoute = this.store.createRecord('school-transport/route', {
        name: `${originalRoute.name} (Copy)`,
        description: originalRoute.description,
        route_number: null, // Will be auto-generated
        type: originalRoute.type,
        status: 'inactive',
        distance: originalRoute.distance,
        estimated_duration: originalRoute.estimated_duration,
        start_time: originalRoute.start_time,
        end_time: originalRoute.end_time,
        stops: originalRoute.stops?.map(stop => ({ ...stop })) || []
      });

      await duplicatedRoute.save();
      this.notifications.success('Route duplicated successfully');
      this.router.refresh();
    } catch (error) {
      console.error('Error duplicating route:', error);
      this.notifications.error('Failed to duplicate route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async deleteRoute(routeId) {
    if (!confirm('Are you sure you want to delete this route? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      const route = this.routes.find(r => r.id === routeId);
      if (route) {
        await route.destroyRecord();
        this.notifications.success('Route deleted successfully');
        this.selectedRoutes.delete(routeId);
        this.router.refresh();
      }
    } catch (error) {
      console.error('Error deleting route:', error);
      this.notifications.error('Failed to delete route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async bulkDeleteRoutes() {
    if (this.selectedRoutes.size === 0) return;

    const count = this.selectedRoutes.size;
    if (!confirm(`Are you sure you want to delete ${count} route(s)? This action cannot be undone.`)) {
      return;
    }

    this.isLoading = true;
    try {
      const deletePromises = this.selectedRoutesArray.map(routeId => {
        const route = this.routes.find(r => r.id === routeId);
        return route ? route.destroyRecord() : Promise.resolve();
      });

      await Promise.all(deletePromises);
      this.notifications.success(`${count} route(s) deleted successfully`);
      this.selectedRoutes.clear();
      this.router.refresh();
    } catch (error) {
      console.error('Error bulk deleting routes:', error);
      this.notifications.error('Failed to delete some routes');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async bulkUpdateStatus(newStatus) {
    if (this.selectedRoutes.size === 0) return;

    this.isLoading = true;
    try {
      const updatePromises = this.selectedRoutesArray.map(routeId => {
        const route = this.routes.find(r => r.id === routeId);
        if (route) {
          route.status = newStatus;
          return route.save();
        }
        return Promise.resolve();
      });

      await Promise.all(updatePromises);
      this.notifications.success(`${this.selectedRoutes.size} route(s) updated successfully`);
      this.selectedRoutes.clear();
      this.router.refresh();
    } catch (error) {
      console.error('Error bulk updating routes:', error);
      this.notifications.error('Failed to update some routes');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async exportRoutes() {
    this.isLoading = true;
    try {
      const exportData = {
        routes: this.selectedRoutes.size > 0 ? this.selectedRoutesArray : this.sortedRoutes.map(r => r.id),
        format: 'csv'
      };

      const response = await fetch('/api/school-transport/routes/export', {
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
        a.download = 'routes-export.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Routes exported successfully');
      } else {
        throw new Error('Export failed');
      }
    } catch (error) {
      console.error('Error exporting routes:', error);
      this.notifications.error('Failed to export routes');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async importRoutes() {
    // This would typically open a file picker and handle CSV import
    this.notifications.info('Import functionality coming soon');
  }

  @action
  toggleExportModal() {
    this.showExportModal = !this.showExportModal;
  }

  @action
  refreshData() {
    this.router.refresh();
  }
}