import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportAssignmentsIndexRoute extends Route {
  @service store;
  @service notifications;

  queryParams = {
    page: { refreshModel: true },
    limit: { refreshModel: true },
    search: { refreshModel: true },
    student_id: { refreshModel: true },
    route_id: { refreshModel: true },
    school_id: { refreshModel: true },
    status: { refreshModel: true },
    academic_year: { refreshModel: true },
    sort: { refreshModel: true }
  };

  async model(params) {
    const queryParams = {
      page: params.page || 1,
      limit: params.limit || 15,
      sort: params.sort || '-created_at',
      include: 'student,school_route,student.school,school_route.school'
    };

    // Add filters if provided
    if (params.search) {
      queryParams.search = params.search;
    }

    if (params.student_id) {
      queryParams.student_id = params.student_id;
    }

    if (params.route_id) {
      queryParams.route_id = params.route_id;
    }

    if (params.school_id) {
      queryParams.school_id = params.school_id;
    }

    if (params.status) {
      queryParams.status = params.status;
    }

    if (params.academic_year) {
      queryParams.academic_year = params.academic_year;
    }

    try {
      const [assignments, students, routes, schools, stats] = await Promise.all([
        this.store.query('bus-assignment', queryParams),
        this.store.findAll('student'),
        this.store.findAll('school-route'),
        this.store.findAll('school'), // Assuming schools model exists
        fetch('/api/v1/school-transport/assignments/stats').then(r => r.json())
      ]);

      return {
        assignments,
        students,
        routes,
        schools,
        stats: stats.data || {},
        meta: assignments.meta || {}
      };
    } catch (error) {
      console.error('Error loading assignments:', error);
      this.notifications.error('Failed to load assignments');
      return {
        assignments: [],
        students: [],
        routes: [],
        schools: [],
        stats: {},
        meta: {}
      };
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Set up filter options from loaded data
    controller.setFilterOptions({
      students: model.students,
      routes: model.routes,
      schools: model.schools
    });
  }
}