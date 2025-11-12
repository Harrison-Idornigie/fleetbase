import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportRoutesIndexRoute extends Route {
  @service store;

  queryParams = {
    page: { refreshModel: true },
    limit: { refreshModel: true },
    search: { refreshModel: true },
    school: { refreshModel: true },
    type: { refreshModel: true },
    status: { refreshModel: true },
    active: { refreshModel: true }
  };

  async model(params) {
    const query = {
      page: params.page || 1,
      limit: params.limit || 25
    };

    // Add filters if provided
    if (params.search) query.search = params.search;
    if (params.school) query.school = params.school;
    if (params.type) query.type = params.type;
    if (params.status) query.status = params.status;
    if (params.active !== undefined) query.active = params.active;

    const routes = await this.store.query('school-route', query);

    // Load filter options
    const schools = await this.loadSchools();

    return {
      routes,
      schools,
      meta: routes.meta,
      typeOptions: [
        { value: 'pickup', label: 'Pickup Only' },
        { value: 'dropoff', label: 'Dropoff Only' },
        { value: 'both', label: 'Both Pickup & Dropoff' }
      ],
      statusOptions: [
        { value: 'draft', label: 'Draft' },
        { value: 'active', label: 'Active' },
        { value: 'suspended', label: 'Suspended' },
        { value: 'archived', label: 'Archived' }
      ]
    };
  }

  async loadSchools() {
    try {
      const routes = await this.store.query('school-route', { limit: 1000 });
      const schools = [...new Set(routes.map(r => r.school))].filter(Boolean);
      return schools.sort();
    } catch (error) {
      console.error('Error loading schools:', error);
      return [];
    }
  }
}