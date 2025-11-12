import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportReportsCustomRoute extends Route {
  @service store;
  @service notifications;

  queryParams = {
    template_id: { refreshModel: false }
  };

  async model(params) {
    try {
      const [reportTemplates, schools, routes, students] = await Promise.all([
        fetch('/api/v1/school-transport/report-templates', {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }).then(r => r.json()).catch(() => ({ data: [] })),
        
        this.store.findAll('school'),
        
        this.store.query('school-route', { 
          limit: 1000,
          include: 'school'
        }),
        
        this.store.query('student', { 
          limit: 1000,
          include: 'school'
        })
      ]);

      // Create new custom report model
      const customReport = {
        name: '',
        description: '',
        type: 'table',
        filters: {
          date_from: null,
          date_to: null,
          school_ids: [],
          route_ids: [],
          student_ids: []
        },
        columns: [],
        groupBy: null,
        sortBy: null,
        sortDirection: 'asc',
        includeCharts: false,
        chartTypes: [],
        scheduleConfig: {
          enabled: false,
          frequency: 'weekly',
          recipients: [],
          format: 'pdf'
        }
      };

      // Pre-populate if template is specified
      if (params.template_id) {
        const template = reportTemplates.data.find(t => t.id === params.template_id);
        if (template) {
          Object.assign(customReport, template.config || {});
          customReport.name = template.name;
          customReport.description = template.description;
        }
      }

      return {
        customReport,
        reportTemplates: reportTemplates.data || [],
        schools,
        routes,
        students,
        reportTypeOptions: [
          { value: 'table', label: 'Table Report' },
          { value: 'chart', label: 'Chart Report' },
          { value: 'dashboard', label: 'Dashboard Report' },
          { value: 'export', label: 'Export Report' }
        ],
        availableColumns: [
          // Student columns
          { value: 'student.name', label: 'Student Name', category: 'student' },
          { value: 'student.student_id', label: 'Student ID', category: 'student' },
          { value: 'student.grade', label: 'Grade', category: 'student' },
          { value: 'student.school.name', label: 'School', category: 'student' },
          { value: 'student.address', label: 'Address', category: 'student' },
          { value: 'student.special_needs', label: 'Special Needs', category: 'student' },
          
          // Route columns
          { value: 'route.name', label: 'Route Name', category: 'route' },
          { value: 'route.school.name', label: 'Route School', category: 'route' },
          { value: 'route.driver_name', label: 'Driver Name', category: 'route' },
          { value: 'route.bus_number', label: 'Bus Number', category: 'route' },
          { value: 'route.capacity', label: 'Bus Capacity', category: 'route' },
          { value: 'route.current_load', label: 'Current Load', category: 'route' },
          
          // Assignment columns
          { value: 'assignment.status', label: 'Assignment Status', category: 'assignment' },
          { value: 'assignment.pickup_time', label: 'Pickup Time', category: 'assignment' },
          { value: 'assignment.dropoff_time', label: 'Dropoff Time', category: 'assignment' },
          { value: 'assignment.boarding_area', label: 'Boarding Area', category: 'assignment' },
          
          // Attendance columns
          { value: 'attendance.status', label: 'Attendance Status', category: 'attendance' },
          { value: 'attendance.date', label: 'Attendance Date', category: 'attendance' },
          { value: 'attendance.boarding_time', label: 'Boarding Time', category: 'attendance' },
          { value: 'attendance.alighting_time', label: 'Alighting Time', category: 'attendance' },
          
          // Communication columns
          { value: 'communication.type', label: 'Communication Type', category: 'communication' },
          { value: 'communication.subject', label: 'Communication Subject', category: 'communication' },
          { value: 'communication.sent_at', label: 'Sent Date', category: 'communication' },
          { value: 'communication.delivery_status', label: 'Delivery Status', category: 'communication' }
        ],
        chartTypeOptions: [
          { value: 'bar', label: 'Bar Chart' },
          { value: 'line', label: 'Line Chart' },
          { value: 'pie', label: 'Pie Chart' },
          { value: 'area', label: 'Area Chart' },
          { value: 'scatter', label: 'Scatter Plot' }
        ],
        frequencyOptions: [
          { value: 'daily', label: 'Daily' },
          { value: 'weekly', label: 'Weekly' },
          { value: 'monthly', label: 'Monthly' },
          { value: 'quarterly', label: 'Quarterly' }
        ],
        formatOptions: [
          { value: 'pdf', label: 'PDF' },
          { value: 'excel', label: 'Excel' },
          { value: 'csv', label: 'CSV' },
          { value: 'email', label: 'Email Summary' }
        ]
      };
    } catch (error) {
      console.error('Error loading custom report data:', error);
      this.notifications.error('Failed to load report builder');
      return {
        customReport: {},
        reportTemplates: [],
        schools: [],
        routes: [],
        students: [],
        reportTypeOptions: [],
        availableColumns: [],
        chartTypeOptions: [],
        frequencyOptions: [],
        formatOptions: []
      };
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Initialize custom report builder
    controller.initializeReportBuilder(model);
  }
}