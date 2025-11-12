import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportBusesIndexRoute extends Route {
  @service store;
  @service notifications;

  queryParams = {
    page: { refreshModel: true },
    limit: { refreshModel: true },
    search: { refreshModel: true },
    status: { refreshModel: true },
    sort: { refreshModel: true }
  };

  async model(params) {
    try {
      const query = {
        page: params.page || 1,
        limit: params.limit || 25,
        include: 'maintenance_schedules,fuel_reports,equipment'
      };

      // Add filters if provided
      if (params.search) query.search = params.search;
      if (params.status) query.filter = { status: params.status };
      if (params.sort) query.sort = params.sort;

      // Query buses as vehicles with school bus characteristics 
      const buses = await this.store.query('vehicle', {
        ...query,
        filter: { 
          ...query.filter,
          vehicle_type: 'bus',
          'bus_number:exists': true 
        },
        include: 'maintenances,fuel_reports,work_orders'
      });

      return {
        buses,
        meta: buses.meta,
        statusOptions: [
          { value: 'all', label: 'All Statuses' },
          { value: 'active', label: 'Active' },
          { value: 'maintenance', label: 'In Maintenance' },
          { value: 'out_of_service', label: 'Out of Service' },
          { value: 'retired', label: 'Retired' }
        ],
        sortOptions: [
          { value: 'bus_number', label: 'Bus Number' },
          { value: 'make', label: 'Make/Model' },
          { value: 'year', label: 'Year' },
          { value: 'status', label: 'Status' },
          { value: 'last_maintenance', label: 'Last Maintenance' },
          { value: 'created_at', label: 'Date Added' }
        ]
      };
    } catch (error) {
      console.error('Error loading buses:', error);
      this.notifications.error('Failed to load buses');
      return {
        buses: [],
        meta: {},
        statusOptions: [],
        sortOptions: []
      };
    }
  }
}