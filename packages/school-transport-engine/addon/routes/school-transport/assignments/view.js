import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportAssignmentsViewRoute extends Route {
  @service store;
  @service notifications;

  async model(params) {
    try {
      const assignment = await this.store.findRecord('bus-assignment', params.assignment_id, {
        include: 'student,school_route,student.school,school_route.school,school_route.bus'
      });

      // Load related data
      const [attendanceHistory, communications] = await Promise.all([
        // Get attendance history for this assignment
        fetch(`/api/v1/school-transport/assignments/${params.assignment_id}/attendance`, {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }).then(r => r.json()).catch(() => ({ data: [] })),
        
        // Get communications related to this assignment
        this.store.query('communication', {
          filter: { 
            assignment_id: params.assignment_id 
          },
          sort: '-created_at',
          limit: 10
        }).catch(() => [])
      ]);

      // Get assignment analytics/stats
      const assignmentStats = await fetch(
        `/api/v1/school-transport/assignments/${params.assignment_id}/stats`,
        {
          headers: {
            'Authorization': `Bearer ${this.store.adapterFor('application').session.token}`
          }
        }
      ).then(r => r.json()).catch(() => ({ data: {} }));

      return {
        assignment,
        attendanceHistory: attendanceHistory.data || [],
        communications,
        stats: assignmentStats.data || {
          total_days_assigned: 0,
          days_present: 0,
          days_absent: 0,
          attendance_percentage: 0,
          average_pickup_time: null,
          average_dropoff_time: null,
          total_communications: 0
        }
      };
    } catch (error) {
      console.error('Error loading assignment details:', error);
      this.notifications.error('Assignment not found or failed to load');
      this.router.transitionTo('school-transport.assignments.index');
      return null;
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    if (model) {
      controller.setAssignmentData(model);
    }
  }
}