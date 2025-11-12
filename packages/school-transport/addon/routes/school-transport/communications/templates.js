import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportCommunicationsTemplatesRoute extends Route {
  @service store;
  @service notifications;

  queryParams = {
    page: { refreshModel: true },
    limit: { refreshModel: true },
    search: { refreshModel: true },
    type: { refreshModel: true },
    is_active: { refreshModel: true },
    sort: { refreshModel: true }
  };

  async model(params) {
    const queryParams = {
      page: params.page || 1,
      limit: params.limit || 15,
      sort: params.sort || 'name'
    };

    // Add filters if provided
    if (params.search) {
      queryParams.search = params.search;
    }

    if (params.type) {
      queryParams.type = params.type;
    }

    if (params.is_active !== undefined) {
      queryParams.is_active = params.is_active;
    }

    try {
      const templates = await this.store.query('communication-template', queryParams);

      return {
        templates,
        meta: templates.meta || {},
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
        ]
      };
    } catch (error) {
      console.error('Error loading communication templates:', error);
      this.notifications.error('Failed to load communication templates');
      return {
        templates: [],
        meta: {},
        typeOptions: []
      };
    }
  }
}