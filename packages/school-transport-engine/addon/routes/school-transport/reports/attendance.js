import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportReportsAttendanceRoute extends Route {
  @service store;
  @service notifications;

  queryParams = {
    school_id: { refreshModel: true },
    route_id: { refreshModel: true },
    student_id: { refreshModel: true },
    date_from: { refreshModel: true },
    date_to: { refreshModel: true },
    report_type: { refreshModel: true },
    format: { refreshModel: false }
  };

  async model(params) {
    // Set default date range (last 30 days)
    const defaultDateTo = new Date().toISOString().split('T')[0];
    const defaultDateFrom = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    const queryParams = {
      date_from: params.date_from || defaultDateFrom,
      date_to: params.date_to || defaultDateTo,
      report_type: params.report_type || 'summary',
      include_details: true
    };

    // Add filters if provided
    if (params.school_id) {
      queryParams.school_id = params.school_id;
    }

    if (params.route_id) {
      queryParams.route_id = params.route_id;
    }

    if (params.student_id) {
      queryParams.student_id = params.student_id;
    }

    try {
      const [reportData, schools, routes, students] = await Promise.all([
        fetch('/api/v1/school-transport/reports/attendance?' + new URLSearchParams(queryParams), {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }).then(r => r.json()),
        
        this.store.findAll('school'),
        
        this.store.query('school-route', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school'
        }),
        
        this.store.query('student', { 
          limit: 1000,
          filter: { status: 'active' },
          include: 'school'
        })
      ]);

      return {
        reportData: reportData.data || {},
        schools,
        routes,
        students,
        filters: {
          date_from: queryParams.date_from,
          date_to: queryParams.date_to,
          school_id: params.school_id,
          route_id: params.route_id,
          student_id: params.student_id,
          report_type: queryParams.report_type
        },
        reportTypeOptions: [
          { value: 'summary', label: 'Summary Report' },
          { value: 'detailed', label: 'Detailed Report' },
          { value: 'daily', label: 'Daily Breakdown' },
          { value: 'trends', label: 'Attendance Trends' },
          { value: 'alerts', label: 'Attendance Alerts' }
        ]
      };
    } catch (error) {
      console.error('Error loading attendance report:', error);
      this.notifications.error('Failed to load attendance report');
      return {
        reportData: {},
        schools: [],
        routes: [],
        students: [],
        filters: {},
        reportTypeOptions: []
      };
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Set up report generation options
    controller.setReportOptions(model);
  }
}