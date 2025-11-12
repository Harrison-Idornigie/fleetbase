import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportCommunicationsIndexRoute extends Route {
  @service store;
  @service notifications;

  queryParams = {
    page: { refreshModel: true },
    limit: { refreshModel: true },
    search: { refreshModel: true },
    type: { refreshModel: true },
    status: { refreshModel: true },
    priority: { refreshModel: true },
    recipient_type: { refreshModel: true },
    date_from: { refreshModel: true },
    date_to: { refreshModel: true },
    sort: { refreshModel: true }
  };

  async model(params) {
    const queryParams = {
      page: params.page || 1,
      limit: params.limit || 15,
      sort: params.sort || '-created_at',
      include: 'sender,recipients,assignments,students,routes'
    };

    // Add filters if provided
    if (params.search) {
      queryParams.search = params.search;
    }

    if (params.type) {
      queryParams.type = params.type;
    }

    if (params.status) {
      queryParams.status = params.status;
    }

    if (params.priority) {
      queryParams.priority = params.priority;
    }

    if (params.recipient_type) {
      queryParams.recipient_type = params.recipient_type;
    }

    if (params.date_from) {
      queryParams.date_from = params.date_from;
    }

    if (params.date_to) {
      queryParams.date_to = params.date_to;
    }

    try {
      const [communications, stats, templates] = await Promise.all([
        this.store.query('communication', queryParams),
        fetch('/api/v1/school-transport/communications/stats').then(r => r.json()),
        this.store.query('communication-template', { 
          filter: { is_active: true },
          sort: 'name' 
        }).catch(() => [])
      ]);

      return {
        communications,
        stats: stats.data || {},
        templates,
        meta: communications.meta || {},
        filterOptions: {
          types: [
            { value: 'general', label: 'General' },
            { value: 'emergency', label: 'Emergency' },
            { value: 'delay', label: 'Route Delay' },
            { value: 'cancellation', label: 'Route Cancellation' },
            { value: 'attendance', label: 'Attendance Alert' },
            { value: 'behavioral', label: 'Behavioral Notice' },
            { value: 'safety', label: 'Safety Notice' }
          ],
          statuses: [
            { value: 'draft', label: 'Draft' },
            { value: 'scheduled', label: 'Scheduled' },
            { value: 'sending', label: 'Sending' },
            { value: 'sent', label: 'Sent' },
            { value: 'failed', label: 'Failed' }
          ],
          priorities: [
            { value: 'low', label: 'Low' },
            { value: 'normal', label: 'Normal' },
            { value: 'high', label: 'High' },
            { value: 'urgent', label: 'Urgent' }
          ],
          recipientTypes: [
            { value: 'parents', label: 'Parents/Guardians' },
            { value: 'students', label: 'Students' },
            { value: 'drivers', label: 'Drivers' },
            { value: 'schools', label: 'School Staff' },
            { value: 'administrators', label: 'Administrators' }
          ]
        }
      };
    } catch (error) {
      console.error('Error loading communications:', error);
      this.notifications.error('Failed to load communications');
      return {
        communications: [],
        stats: {},
        templates: [],
        meta: {},
        filterOptions: {}
      };
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Set up filter state
    controller.setFilterOptions(model.filterOptions);
  }
}