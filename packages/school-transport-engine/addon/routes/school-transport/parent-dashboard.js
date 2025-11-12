import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ParentDashboardRoute extends Route {
  @service schoolTransportApi;
  @service session;

  async model() {
    // Ensure authenticated parent session
    if (!this.session.user) {
      this.transitionTo('login');
      return {};
    }

    // Get parent dashboard payload from API
    const data = await this.schoolTransportApi.getParentDashboard();
    return data;
  }
}
