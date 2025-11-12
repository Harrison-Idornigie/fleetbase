import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportReportsRouteEfficiencyRoute extends Route {
  @service store;
  @service notifications;

  queryParams = {
    school_id: { refreshModel: true },
    route_id: { refreshModel: true },
    date_from: { refreshModel: true },
    date_to: { refreshModel: true },
    metric_type: { refreshModel: true },
    threshold: { refreshModel: true }
  };

  async model(params) {
    // Set default date range (last 90 days for efficiency analysis)
    const defaultDateTo = new Date().toISOString().split('T')[0];
    const defaultDateFrom = new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    const queryParams = {
      date_from: params.date_from || defaultDateFrom,
      date_to: params.date_to || defaultDateTo,
      metric_type: params.metric_type || 'overall',
      threshold: params.threshold || 75, // Efficiency threshold percentage
      include_tracking_data: true,
      include_fuel_data: true
    };

    // Add filters if provided
    if (params.school_id) {
      queryParams.school_id = params.school_id;
    }

    if (params.route_id) {
      queryParams.route_id = params.route_id;
    }

    try {
      const [reportData, schools, routes] = await Promise.all([
        fetch('/api/v1/school-transport/reports/route-efficiency?' + new URLSearchParams(queryParams), {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }).then(r => r.json()),
        
        this.store.findAll('school'),
        
        this.store.query('school-route', { 
          limit: 1000,
          include: 'school,bus'
        })
      ]);

      return {
        reportData: reportData.data || {
          overall_efficiency: 0,
          routes_analyzed: 0,
          top_performers: [],
          improvement_opportunities: [],
          efficiency_trends: [],
          fuel_consumption: {},
          route_metrics: []
        },
        schools,
        routes,
        filters: {
          date_from: queryParams.date_from,
          date_to: queryParams.date_to,
          school_id: params.school_id,
          route_id: params.route_id,
          metric_type: queryParams.metric_type,
          threshold: queryParams.threshold
        },
        metricTypeOptions: [
          { value: 'overall', label: 'Overall Efficiency' },
          { value: 'fuel', label: 'Fuel Efficiency' },
          { value: 'time', label: 'Time Efficiency' },
          { value: 'distance', label: 'Distance Optimization' },
          { value: 'utilization', label: 'Route Utilization' },
          { value: 'reliability', label: 'Schedule Reliability' }
        ],
        thresholdOptions: [
          { value: 50, label: '50% - Basic' },
          { value: 65, label: '65% - Good' },
          { value: 75, label: '75% - Very Good' },
          { value: 85, label: '85% - Excellent' },
          { value: 95, label: '95% - Outstanding' }
        ]
      };
    } catch (error) {
      console.error('Error loading route efficiency report:', error);
      this.notifications.error('Failed to load route efficiency report');
      return {
        reportData: {},
        schools: [],
        routes: [],
        filters: {},
        metricTypeOptions: [],
        thresholdOptions: []
      };
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Set up efficiency analysis options
    controller.setEfficiencyReportOptions(model);
  }
}