import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportRoutesEditRoute extends Route {
  @service store;
  @service notifications;

  async model(params) {
    try {
      const [route, schools, vehicles, drivers] = await Promise.all([
        this.store.findRecord('school-route', params.route_id, {
          include: 'school,vehicle,driver',
          reload: true
        }),
        this.store.findAll('school').catch(() => []),
        this.store.query('vehicle', { 
          limit: 1000,
          filter: { status: 'active' },
          sort: 'name'
        }).catch(() => []),
        this.store.query('driver', { 
          limit: 1000,
          filter: { status: 'active' },
          sort: 'name'
        }).catch(() => [])
      ]);

      return {
        route,
        schools,
        vehicles,
        drivers,
        typeOptions: [
          { value: 'morning', label: 'Morning Pickup' },
          { value: 'afternoon', label: 'Afternoon Drop-off' },
          { value: 'special', label: 'Special Route' },
          { value: 'field_trip', label: 'Field Trip' }
        ],
        statusOptions: [
          { value: 'draft', label: 'Draft' },
          { value: 'active', label: 'Active' },
          { value: 'suspended', label: 'Suspended' },
          { value: 'archived', label: 'Archived' }
        ]
      };
    } catch (error) {
      console.error('Error loading route for editing:', error);
      this.notifications.error('Failed to load route');
      this.transitionTo('school-transport.routes.index');
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Reset controller state
    controller.setProperties({
      currentStep: 1,
      isLoading: false,
      errors: {}
    });

    // Initialize form fields from model
    if (model.route) {
      controller.setProperties({
        route: model.route,
        name: model.route.name || '',
        description: model.route.description || '',
        routeNumber: model.route.route_number || '',
        type: model.route.type || 'morning',
        status: model.route.status || 'draft',
        distance: model.route.distance || '',
        estimatedDuration: model.route.estimated_duration || '',
        startTime: model.route.start_time || '',
        endTime: model.route.end_time || '',
        stops: model.route.stops ? [...model.route.stops] : [],
        waypoints: model.route.waypoints ? [...model.route.waypoints] : [],
        assignedVehicleId: model.route.assigned_vehicle_id || '',
        assignedDriverId: model.route.assigned_driver_id || '',
        isRecurring: model.route.is_recurring !== false,
        recurrencePattern: model.route.recurrence_pattern || 'weekly',
        recurrenceDays: model.route.recurrence_days ? [...model.route.recurrence_days] : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        startDate: model.route.start_date || new Date().toISOString().split('T')[0],
        endDate: model.route.end_date || '',
        maxCapacity: model.route.max_capacity || '',
        specialInstructions: model.route.special_instructions || '',
        weatherDependent: model.route.weather_dependent || false,
        backupRouteId: model.route.backup_route_id || ''
      });
    }
  }

  resetController(controller, isExiting) {
    if (isExiting) {
      // Rollback any unsaved changes
      const route = this.modelFor(this.routeName)?.route;
      if (route && route.get('hasDirtyAttributes')) {
        route.rollbackAttributes();
      }
    }
  }
}

