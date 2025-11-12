import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportCommunicationsNewRoute extends Route {
  @service store;
  @service notifications;

  queryParams = {
    type: { refreshModel: false },
    template_id: { refreshModel: false },
    recipient_type: { refreshModel: false },
    assignment_id: { refreshModel: false },
    route_id: { refreshModel: false },
    student_id: { refreshModel: false }
  };

  async model(params) {
    try {
      const [templates, assignments, routes, students, schools] = await Promise.all([
        this.store.query('communication-template', { 
          filter: { is_active: true },
          sort: 'name' 
        }).catch(() => []),
        this.store.query('bus-assignment', { 
          limit: 1000,
          include: 'student,school_route',
          sort: 'student.last_name'
        }).catch(() => []),
        this.store.query('school-route', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school',
          sort: 'school.name,name'
        }).catch(() => []),
        this.store.query('student', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school',
          sort: 'school.name,last_name'
        }).catch(() => []),
        this.store.findAll('school').catch(() => [])
      ]);

      // Create new communication model
      const communication = this.store.createRecord('communication', {
        type: params.type || 'general',
        priority: 'normal',
        status: 'draft',
        subject: '',
        message: '',
        recipient_type: params.recipient_type || 'parents',
        send_immediately: false,
        scheduled_at: null,
        send_sms: true,
        send_email: true,
        send_push: false
      });

      // Pre-populate if template is specified
      if (params.template_id) {
        const template = templates.find(t => t.id === params.template_id);
        if (template) {
          communication.setProperties({
            subject: template.subject,
            message: template.message,
            type: template.type || communication.type
          });
        }
      }

      // Pre-populate recipients based on query params
      if (params.assignment_id) {
        const assignment = assignments.find(a => a.id === params.assignment_id);
        if (assignment) {
          communication.set('assignments', [assignment]);
        }
      }

      if (params.route_id) {
        const route = routes.find(r => r.id === params.route_id);
        if (route) {
          communication.set('routes', [route]);
        }
      }

      if (params.student_id) {
        const student = students.find(s => s.id === params.student_id);
        if (student) {
          communication.set('students', [student]);
        }
      }

      return {
        communication,
        templates,
        assignments,
        routes,
        students,
        schools,
        typeOptions: [
          { value: 'general', label: 'General Information' },
          { value: 'emergency', label: 'Emergency Alert' },
          { value: 'delay', label: 'Route Delay' },
          { value: 'cancellation', label: 'Route Cancellation' },
          { value: 'attendance', label: 'Attendance Alert' },
          { value: 'behavioral', label: 'Behavioral Notice' },
          { value: 'safety', label: 'Safety Notice' },
          { value: 'maintenance', label: 'Maintenance Notice' },
          { value: 'weather', label: 'Weather Update' }
        ],
        priorityOptions: [
          { value: 'low', label: 'Low Priority' },
          { value: 'normal', label: 'Normal Priority' },
          { value: 'high', label: 'High Priority' },
          { value: 'urgent', label: 'Urgent' }
        ],
        recipientTypeOptions: [
          { value: 'parents', label: 'Parents/Guardians' },
          { value: 'students', label: 'Students' },
          { value: 'drivers', label: 'Bus Drivers' },
          { value: 'schools', label: 'School Staff' },
          { value: 'administrators', label: 'Transportation Administrators' },
          { value: 'custom', label: 'Custom Recipients' }
        ]
      };
    } catch (error) {
      console.error('Error loading communication form data:', error);
      this.notifications.error('Failed to load form data');
      return {
        communication: this.store.createRecord('communication'),
        templates: [],
        assignments: [],
        routes: [],
        students: [],
        schools: [],
        typeOptions: [],
        priorityOptions: [],
        recipientTypeOptions: []
      };
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Initialize form state
    controller.initializeForm(model);
  }

  deactivate() {
    super.deactivate();
    
    // Clean up any unsaved changes
    const communication = this.currentModel?.communication;
    if (communication && communication.isNew) {
      communication.rollbackAttributes();
    }
  }
}