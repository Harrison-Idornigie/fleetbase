import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportAssignmentsEditRoute extends Route {
  @service store;
  @service notifications;

  async model(params) {
    try {
      const [assignment, students, routes, schools] = await Promise.all([
        this.store.findRecord('bus-assignment', params.assignment_id, {
          include: 'student,school_route,school',
          reload: true
        }),
        this.store.query('student', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school'
        }).catch(() => []),
        this.store.query('school-route', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school'
        }).catch(() => []),
        this.store.findAll('school').catch(() => [])
      ]);

      return {
        assignment,
        students,
        routes,
        schools,
        statusOptions: [
          { value: 'pending', label: 'Pending' },
          { value: 'active', label: 'Active' },
          { value: 'suspended', label: 'Suspended' },
          { value: 'completed', label: 'Completed' }
        ],
        typeOptions: [
          { value: 'regular', label: 'Regular Route' },
          { value: 'special', label: 'Special Needs' },
          { value: 'temporary', label: 'Temporary' }
        ]
      };
    } catch (error) {
      console.error('Error loading assignment for editing:', error);
      this.notifications.error('Failed to load assignment');
      this.transitionTo('school-transport.assignments.index');
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Reset controller state
    controller.setProperties({
      isLoading: false,
      errors: {}
    });

    // Initialize form fields from model
    if (model.assignment) {
      controller.setProperties({
        assignment: model.assignment,
        studentId: model.assignment.student_id || '',
        routeId: model.assignment.route_id || '',
        schoolId: model.assignment.school_id || '',
        status: model.assignment.status || 'pending',
        type: model.assignment.type || 'regular',
        academicYear: model.assignment.academic_year || new Date().getFullYear().toString(),
        pickupTime: model.assignment.pickup_time || '',
        dropoffTime: model.assignment.dropoff_time || '',
        pickupStopId: model.assignment.pickup_stop_id || '',
        dropoffStopId: model.assignment.dropoff_stop_id || '',
        boardingArea: model.assignment.boarding_area || '',
        specialInstructions: model.assignment.special_instructions || '',
        startDate: model.assignment.start_date || new Date().toISOString().split('T')[0],
        endDate: model.assignment.end_date || ''
      });
    }
  }

  resetController(controller, isExiting) {
    if (isExiting) {
      // Rollback any unsaved changes
      const assignment = this.modelFor(this.routeName)?.assignment;
      if (assignment && assignment.get('hasDirtyAttributes')) {
        assignment.rollbackAttributes();
      }
    }
  }
}

