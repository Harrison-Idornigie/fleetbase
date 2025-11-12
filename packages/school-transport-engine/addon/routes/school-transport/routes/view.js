import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportRoutesViewRoute extends Route {
  @service store;

  async model(params) {
    const route = await this.store.findRecord('school-route', params.route_id, {
      include: 'bus_assignments,bus_assignments.student,attendance_records,communications'
    });

    // Load related data
    const [assignedStudents, routeTracking, efficiencyData] = await Promise.all([
      this.loadAssignedStudents(route.id),
      this.loadRouteTracking(route.id),
      this.loadRouteEfficiency(route.id)
    ]);

    return {
      route,
      assignedStudents,
      routeTracking,
      efficiencyData
    };
  }

  async loadAssignedStudents(routeId) {
    try {
      const response = await fetch(`/api/school-transport/routes/${routeId}/students`);
      return await response.json();
    } catch (error) {
      console.error('Error loading assigned students:', error);
      return { students: [] };
    }
  }

  async loadRouteTracking(routeId) {
    try {
      const response = await fetch(`/api/school-transport/routes/${routeId}/tracking`);
      return await response.json();
    } catch (error) {
      console.error('Error loading route tracking:', error);
      return { tracking: null };
    }
  }

  async loadRouteEfficiency(routeId) {
    try {
      // Calculate efficiency metrics for this route
      const route = await this.store.peekRecord('school-route', routeId);
      
      return {
        utilization_percentage: route?.utilization_percentage || 0,
        efficiency_score: route?.efficiency_score || 0,
        assigned_students: route?.assigned_students_count || 0,
        capacity: route?.capacity || 0,
        is_overutilized: route?.is_overutilized || false
      };
    } catch (error) {
      console.error('Error loading route efficiency:', error);
      return {};
    }
  }
}