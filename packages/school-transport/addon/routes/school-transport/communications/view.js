import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportCommunicationsViewRoute extends Route {
  @service store;
  @service notifications;

  async model(params) {
    try {
      const communication = await this.store.findRecord('communication', params.communication_id, {
        include: 'sender,recipients,assignments,students,routes,template'
      });

      // Load delivery status and analytics
      const [deliveryStats, recipientDetails, responses] = await Promise.all([
        fetch(`/api/v1/school-transport/communications/${params.communication_id}/delivery-stats`, {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }).then(r => r.json()).catch(() => ({ data: {} })),
        
        fetch(`/api/v1/school-transport/communications/${params.communication_id}/recipients`, {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }).then(r => r.json()).catch(() => ({ data: [] })),

        fetch(`/api/v1/school-transport/communications/${params.communication_id}/responses`, {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }).then(r => r.json()).catch(() => ({ data: [] }))
      ]);

      return {
        communication,
        deliveryStats: deliveryStats.data || {
          total_recipients: 0,
          sent_count: 0,
          delivered_count: 0,
          failed_count: 0,
          opened_count: 0,
          clicked_count: 0,
          response_count: 0
        },
        recipientDetails: recipientDetails.data || [],
        responses: responses.data || []
      };
    } catch (error) {
      console.error('Error loading communication details:', error);
      this.notifications.error('Communication not found or failed to load');
      this.router.transitionTo('school-transport.communications.index');
      return null;
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    if (model) {
      controller.setCommunicationData(model);
    }
  }
}