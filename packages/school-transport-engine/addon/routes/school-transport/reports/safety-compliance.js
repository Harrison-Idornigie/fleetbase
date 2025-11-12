import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportReportsSafetyComplianceRoute extends Route {
  @service store;
  @service notifications;

  queryParams = {
    school_id: { refreshModel: true },
    route_id: { refreshModel: true },
    compliance_type: { refreshModel: true },
    date_from: { refreshModel: true },
    date_to: { refreshModel: true },
    status: { refreshModel: true },
    severity: { refreshModel: true }
  };

  async model(params) {
    // Set default date range (last 60 days for safety compliance)
    const defaultDateTo = new Date().toISOString().split('T')[0];
    const defaultDateFrom = new Date(Date.now() - 60 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    const queryParams = {
      date_from: params.date_from || defaultDateFrom,
      date_to: params.date_to || defaultDateTo,
      compliance_type: params.compliance_type || 'all',
      include_incidents: true,
      include_inspections: true,
      include_violations: true
    };

    // Add filters if provided
    if (params.school_id) {
      queryParams.school_id = params.school_id;
    }

    if (params.route_id) {
      queryParams.route_id = params.route_id;
    }

    if (params.status) {
      queryParams.status = params.status;
    }

    if (params.severity) {
      queryParams.severity = params.severity;
    }

    try {
      const [reportData, schools, routes, drivers] = await Promise.all([
        fetch('/api/v1/school-transport/reports/safety-compliance?' + new URLSearchParams(queryParams), {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }).then(r => r.json()),
        
        this.store.findAll('school'),
        
        this.store.query('school-route', { 
          limit: 1000,
          include: 'school,bus'
        }),

        // Assuming drivers are contacts or users with driver role
        fetch('/api/v1/school-transport/drivers', {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }).then(r => r.json()).catch(() => ({ data: [] }))
      ]);

      return {
        reportData: reportData.data || {
          compliance_score: 0,
          total_incidents: 0,
          total_violations: 0,
          inspection_results: {},
          safety_trends: [],
          critical_issues: [],
          recommendations: [],
          compliance_by_route: [],
          driver_performance: []
        },
        schools,
        routes,
        drivers: drivers.data || [],
        filters: {
          date_from: queryParams.date_from,
          date_to: queryParams.date_to,
          school_id: params.school_id,
          route_id: params.route_id,
          compliance_type: queryParams.compliance_type,
          status: params.status,
          severity: params.severity
        },
        complianceTypeOptions: [
          { value: 'all', label: 'All Compliance Areas' },
          { value: 'vehicle', label: 'Vehicle Inspections' },
          { value: 'driver', label: 'Driver Compliance' },
          { value: 'route', label: 'Route Safety' },
          { value: 'student', label: 'Student Safety' },
          { value: 'emergency', label: 'Emergency Procedures' },
          { value: 'maintenance', label: 'Maintenance Records' }
        ],
        statusOptions: [
          { value: 'compliant', label: 'Compliant' },
          { value: 'warning', label: 'Warning' },
          { value: 'violation', label: 'Violation' },
          { value: 'critical', label: 'Critical Issue' }
        ],
        severityOptions: [
          { value: 'low', label: 'Low' },
          { value: 'medium', label: 'Medium' },
          { value: 'high', label: 'High' },
          { value: 'critical', label: 'Critical' }
        ]
      };
    } catch (error) {
      console.error('Error loading safety compliance report:', error);
      this.notifications.error('Failed to load safety compliance report');
      return {
        reportData: {},
        schools: [],
        routes: [],
        drivers: [],
        filters: {},
        complianceTypeOptions: [],
        statusOptions: [],
        severityOptions: []
      };
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Set up safety compliance analysis options
    controller.setSafetyReportOptions(model);
  }
}