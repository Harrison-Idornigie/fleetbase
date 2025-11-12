import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportAssignmentsNewRoute extends Route {
  @service store;
  @service notifications;

  async model() {
    try {
      const [students, routes, schools] = await Promise.all([
        this.store.query('student', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school'
        }),
        this.store.query('school-route', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school'
        }),
        this.store.findAll('school')
      ]);

      // Create new assignment model
      const assignment = this.store.createRecord('bus-assignment', {
        status: 'pending',
        academic_year: new Date().getFullYear().toString(),
        pickup_time: null,
        dropoff_time: null,
        boarding_area: '',
        special_instructions: ''
      });

      return {
        assignment,
        students,
        routes,
        schools,
        statusOptions: [
          { value: 'pending', label: 'Pending' },
          { value: 'active', label: 'Active' },
          { value: 'inactive', label: 'Inactive' },
          { value: 'suspended', label: 'Suspended' }
        ],
        academicYears: this.getAcademicYearOptions()
      };
    } catch (error) {
      console.error('Error loading assignment form data:', error);
      this.notifications.error('Failed to load form data');
      return {
        assignment: this.store.createRecord('bus-assignment'),
        students: [],
        routes: [],
        schools: [],
        statusOptions: [],
        academicYears: []
      };
    }
  }

  getAcademicYearOptions() {
    const currentYear = new Date().getFullYear();
    const years = [];
    
    // Generate academic years from current year - 2 to current year + 2
    for (let i = currentYear - 2; i <= currentYear + 2; i++) {
      years.push({
        value: i.toString(),
        label: `${i}-${i + 1}`
      });
    }
    
    return years;
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Set default form state
    controller.resetForm();
  }

  deactivate() {
    super.deactivate();
    
    // Clean up any unsaved changes
    const assignment = this.currentModel?.assignment;
    if (assignment && assignment.isNew) {
      assignment.rollbackAttributes();
    }
  }
}