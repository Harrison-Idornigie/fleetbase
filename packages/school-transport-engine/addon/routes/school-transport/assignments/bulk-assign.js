import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportAssignmentsBulkAssignRoute extends Route {
  @service store;
  @service notifications;

  async model() {
    try {
      const [students, routes, schools, templates] = await Promise.all([
        this.store.query('student', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school',
          sort: 'school.name,grade,last_name'
        }),
        this.store.query('school-route', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school',
          sort: 'school.name,name'
        }),
        this.store.findAll('school'),
        // Load assignment templates if they exist
        fetch('/api/v1/school-transport/assignment-templates').then(r => r.json()).catch(() => ({ data: [] }))
      ]);

      return {
        students,
        routes,
        schools,
        templates: templates.data || [],
        bulkAssignments: [], // Will hold the bulk assignment data
        assignmentCriteria: {
          school_id: null,
          grade: null,
          special_needs_only: false,
          by_address: false,
          address_radius: 0.5, // miles
          academic_year: new Date().getFullYear().toString()
        }
      };
    } catch (error) {
      console.error('Error loading bulk assignment data:', error);
      this.notifications.error('Failed to load bulk assignment data');
      return {
        students: [],
        routes: [],
        schools: [],
        templates: [],
        bulkAssignments: [],
        assignmentCriteria: {}
      };
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Initialize bulk assignment state
    controller.initializeBulkAssignment(model);
  }
}