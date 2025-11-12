import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportRoutesNewRoute extends Route {
  @service store;
  @service notifications;

  async model() {
    try {
      const [schools, vehicles, drivers] = await Promise.all([
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

      // Create new route model
      const route = this.store.createRecord('school-route', {
        status: 'draft',
        type: 'morning',
        stops: [],
        waypoints: [],
        is_recurring: true,
        recurrence_pattern: 'weekly',
        recurrence_days: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
      });

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
          { value: 'suspended', label: 'Suspended' }
        ]
      };
    } catch (error) {
      console.error('Error loading route creation data:', error);
      this.notifications.error('Failed to load route creation form');
      return {
        route: null,
        schools: [],
        vehicles: [],
        drivers: [],
        typeOptions: [],
        statusOptions: []
      };
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
        name: model.route.name || '',
        description: model.route.description || '',
        routeNumber: model.route.route_number || '',
        type: model.route.type || 'morning',
        status: model.route.status || 'draft',
        distance: model.route.distance || '',
        estimatedDuration: model.route.estimated_duration || '',
        startTime: model.route.start_time || '',
        endTime: model.route.end_time || '',
        stops: model.route.stops || [],
        waypoints: model.route.waypoints || [],
        assignedVehicleId: model.route.assigned_vehicle_id || '',
        assignedDriverId: model.route.assigned_driver_id || '',
        isRecurring: model.route.is_recurring !== false,
        recurrencePattern: model.route.recurrence_pattern || 'weekly',
        recurrenceDays: model.route.recurrence_days || ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
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
      // Clean up any unsaved records
      const route = this.modelFor(this.routeName)?.route;
      if (route && route.get('isNew')) {
        route.rollbackAttributes();
      }
    }
  }
}

