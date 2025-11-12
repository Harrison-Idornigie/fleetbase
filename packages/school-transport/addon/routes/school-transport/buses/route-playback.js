import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportBusesRoutePlaybackRoute extends Route {
  @service store;
  @service notifications;

  async model(params) {
    try {
      const bus = await this.store.findRecord('vehicle', params.bus_id, {
        include: 'fuel_reports,maintenance_schedules'
      });

      // Get date range for route history (last 30 days by default)
      const endDate = new Date();
      const startDate = new Date();
      startDate.setDate(startDate.getDate() - 30);

      return {
        bus,
        startDate: startDate.toISOString().split('T')[0],
        endDate: endDate.toISOString().split('T')[0],
        routeData: null // Will be loaded by component
      };
    } catch (error) {
      console.error('Error loading bus for route playback:', error);
      this.notifications.error('Failed to load bus information');
      this.router.transitionTo('school-transport.buses.index');
      return {};
    }
  }
}