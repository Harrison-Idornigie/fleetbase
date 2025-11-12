import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class SchoolTransportBusesIndexController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked searchQuery = '';
  @tracked selectedStatus = 'all';
  @tracked sortBy = 'bus_number';
  @tracked sortDirection = 'asc';
  @tracked currentPage = 1;
  @tracked pageSize = 25;
  @tracked selectedBuses = new Set();
  @tracked showFilters = false;

  get buses() {
    return this.model.buses || [];
  }

  get filteredBuses() {
    let buses = this.buses;

    // Search filter
    if (this.searchQuery) {
      const query = this.searchQuery.toLowerCase();
      buses = buses.filter(bus => 
        bus.bus_number?.toLowerCase().includes(query) ||
        bus.make?.toLowerCase().includes(query) ||
        bus.model?.toLowerCase().includes(query) ||
        bus.license_plate?.toLowerCase().includes(query)
      );
    }

    // Status filter
    if (this.selectedStatus !== 'all') {
      buses = buses.filter(bus => bus.status === this.selectedStatus);
    }

    return buses;
  }

  get sortedBuses() {
    const buses = [...this.filteredBuses];
    const direction = this.sortDirection === 'asc' ? 1 : -1;

    return buses.sort((a, b) => {
      const aValue = a[this.sortBy] || '';
      const bValue = b[this.sortBy] || '';

      if (typeof aValue === 'string') {
        return direction * aValue.localeCompare(bValue);
      }

      return direction * (aValue - bValue);
    });
  }

  get paginatedBuses() {
    const start = (this.currentPage - 1) * this.pageSize;
    const end = start + this.pageSize;
    return this.sortedBuses.slice(start, end);
  }

  get totalPages() {
    return Math.ceil(this.sortedBuses.length / this.pageSize);
  }

  get hasSelection() {
    return this.selectedBuses.size > 0;
  }

  @action
  updateSearch(event) {
    this.searchQuery = event.target.value;
    this.currentPage = 1;
  }

  @action
  updateStatusFilter(event) {
    this.selectedStatus = event.target.value;
    this.currentPage = 1;
  }

  @action
  updateSort(field) {
    if (this.sortBy === field) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortBy = field;
      this.sortDirection = 'asc';
    }
  }

  @action
  changePage(page) {
    this.currentPage = page;
  }

  @action
  createBus() {
    this.router.transitionTo('school-transport.buses.new');
  }

  @action
  viewBus(busId) {
    this.router.transitionTo('school-transport.buses.view', busId);
  }

  @action
  editBus(busId) {
    this.router.transitionTo('school-transport.buses.edit', busId);
  }

  @action
  viewMaintenance(busId) {
    // Redirect to FleetOps maintenance for this vehicle
    this.router.transitionTo('console.fleetops.maintenance.work-orders.index', {
      queryParams: { vehicle_uuid: busId }
    });
  }

  @action
  viewFuel(busId) {
    // Redirect to FleetOps analytics with fuel filter for this vehicle
    this.router.transitionTo('console.fleetops.analytics', {
      queryParams: { vehicle_uuid: busId, filter: 'fuel' }
    });
  }

  @action
  viewRoutePlayback(busId) {
    this.router.transitionTo('school-transport.buses.route-playback', busId);
  }

  @action
  async deleteBus(bus) {
    if (!confirm('Are you sure you want to delete this bus?')) {
      return;
    }

    try {
      this.isLoading = true;
      await bus.destroyRecord();
      this.notifications.success('Bus deleted successfully');
      this.send('refreshModel');
    } catch (error) {
      console.error('Error deleting bus:', error);
      this.notifications.error('Failed to delete bus');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  refreshData() {
    this.send('refreshModel');
  }
}