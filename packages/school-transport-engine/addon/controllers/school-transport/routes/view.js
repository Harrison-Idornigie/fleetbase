import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportRoutesViewController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked showEditModal = false;
  @tracked showDeleteModal = false;
  @tracked showDuplicateModal = false;
  @tracked showAssignModal = false;
  @tracked selectedTab = 'overview';

  get route() {
    return this.model.route;
  }

  get assignments() {
    return this.model.assignments || [];
  }

  get stops() {
    return this.route.stops || [];
  }

  get waypoints() {
    return this.route.waypoints || [];
  }

  get vehicle() {
    return this.model.vehicle;
  }

  get driver() {
    return this.model.driver;
  }

  get activeAssignments() {
    return this.assignments.filter(assignment => assignment.status === 'active');
  }

  get completedAssignments() {
    return this.assignments.filter(assignment => assignment.status === 'completed');
  }

  get routeStats() {
    const assignments = this.assignments;
    const totalAssignments = assignments.length;
    const completedAssignments = assignments.filter(a => a.status === 'completed').length;
    const onTimeDeliveries = assignments.filter(a => a.on_time).length;

    return {
      totalAssignments,
      completedAssignments,
      onTimeDeliveries,
      completionRate: totalAssignments > 0 ? Math.round((completedAssignments / totalAssignments) * 100) : 0,
      onTimeRate: totalAssignments > 0 ? Math.round((onTimeDeliveries / totalAssignments) * 100) : 0,
      averageDuration: this.calculateAverageDuration(assignments)
    };
  }

  get stopDetails() {
    return this.stops.map((stop, index) => ({
      ...stop,
      order: index + 1,
      isFirst: index === 0,
      isLast: index === this.stops.length - 1,
      students: this.getStudentsForStop(stop.id)
    }));
  }

  get routeMapData() {
    const coordinates = this.stops
      .filter(stop => stop.latitude && stop.longitude)
      .map(stop => ({
        lat: parseFloat(stop.latitude),
        lng: parseFloat(stop.longitude),
        title: stop.address,
        type: 'stop'
      }));

    // Add waypoints
    const waypointCoords = this.waypoints
      .filter(waypoint => waypoint.latitude && waypoint.longitude)
      .map(waypoint => ({
        lat: parseFloat(waypoint.latitude),
        lng: parseFloat(waypoint.longitude),
        title: waypoint.address,
        type: 'waypoint'
      }));

    return [...coordinates, ...waypointCoords];
  }

  calculateAverageDuration(assignments) {
    const durations = assignments
      .filter(a => a.actual_duration)
      .map(a => parseFloat(a.actual_duration));

    if (durations.length === 0) return 0;

    const sum = durations.reduce((acc, duration) => acc + duration, 0);
    return Math.round(sum / durations.length);
  }

  getStudentsForStop(stopId) {
    // This would typically come from the backend
    // For now, return mock data
    return [
      { id: '1', name: 'John Doe', grade: '5th', pickup_time: '07:30' },
      { id: '2', name: 'Jane Smith', grade: '4th', pickup_time: '07:35' }
    ];
  }

  @action
  selectTab(tab) {
    this.selectedTab = tab;
  }

  @action
  editRoute() {
    this.router.transitionTo('school-transport.routes.edit', this.route.id);
  }

  @action
  async duplicateRoute() {
    this.isLoading = true;
    try {
      const duplicatedRoute = this.store.createRecord('school-transport/route', {
        name: `${this.route.name} (Copy)`,
        description: this.route.description,
        route_number: null, // Will be auto-generated
        type: this.route.type,
        status: 'inactive',
        distance: this.route.distance,
        estimated_duration: this.route.estimated_duration,
        start_time: this.route.start_time,
        end_time: this.route.end_time,
        stops: this.route.stops?.map(stop => ({ ...stop })) || [],
        waypoints: this.route.waypoints?.map(waypoint => ({ ...waypoint })) || []
      });

      await duplicatedRoute.save();
      this.notifications.success('Route duplicated successfully');
      this.router.transitionTo('school-transport.routes.view', duplicatedRoute.id);
    } catch (error) {
      console.error('Error duplicating route:', error);
      this.notifications.error('Failed to duplicate route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async deleteRoute() {
    if (!confirm('Are you sure you want to delete this route? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      await this.route.destroyRecord();
      this.notifications.success('Route deleted successfully');
      this.router.transitionTo('school-transport.routes.index');
    } catch (error) {
      console.error('Error deleting route:', error);
      this.notifications.error('Failed to delete route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async updateRouteStatus(newStatus) {
    this.isLoading = true;
    try {
      this.route.status = newStatus;
      await this.route.save();
      this.notifications.success(`Route status updated to ${newStatus}`);
    } catch (error) {
      console.error('Error updating route status:', error);
      this.notifications.error('Failed to update route status');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async assignVehicle(vehicleId) {
    this.isLoading = true;
    try {
      this.route.assigned_vehicle_id = vehicleId;
      await this.route.save();
      this.notifications.success('Vehicle assigned successfully');
      this.router.refresh();
    } catch (error) {
      console.error('Error assigning vehicle:', error);
      this.notifications.error('Failed to assign vehicle');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async assignDriver(driverId) {
    this.isLoading = true;
    try {
      this.route.assigned_driver_id = driverId;
      await this.route.save();
      this.notifications.success('Driver assigned successfully');
      this.router.refresh();
    } catch (error) {
      console.error('Error assigning driver:', error);
      this.notifications.error('Failed to assign driver');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async startRoute() {
    if (!confirm('Are you sure you want to start this route?')) {
      return;
    }

    this.isLoading = true;
    try {
      // Create a new assignment for this route
      const assignment = this.store.createRecord('school-transport/assignment', {
        route_id: this.route.id,
        vehicle_id: this.route.assigned_vehicle_id,
        driver_id: this.route.assigned_driver_id,
        status: 'in_progress',
        started_at: new Date().toISOString()
      });

      await assignment.save();
      this.notifications.success('Route started successfully');
      this.router.refresh();
    } catch (error) {
      console.error('Error starting route:', error);
      this.notifications.error('Failed to start route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async completeRoute() {
    if (!confirm('Are you sure you want to mark this route as completed?')) {
      return;
    }

    this.isLoading = true;
    try {
      const activeAssignment = this.activeAssignments[0];
      if (activeAssignment) {
        activeAssignment.status = 'completed';
        activeAssignment.completed_at = new Date().toISOString();
        await activeAssignment.save();
      }

      this.notifications.success('Route completed successfully');
      this.router.refresh();
    } catch (error) {
      console.error('Error completing route:', error);
      this.notifications.error('Failed to complete route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async exportRouteData() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/routes/${this.route.id}/export`, {
        method: 'GET'
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.route.name}-data.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Route data exported successfully');
      } else {
        throw new Error('Export failed');
      }
    } catch (error) {
      console.error('Error exporting route data:', error);
      this.notifications.error('Failed to export route data');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async generateRouteReport() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/routes/${this.route.id}/report`, {
        method: 'GET'
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.route.name}-report.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Route report generated successfully');
      } else {
        throw new Error('Report generation failed');
      }
    } catch (error) {
      console.error('Error generating route report:', error);
      this.notifications.error('Failed to generate route report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async sendRouteNotification(type) {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/routes/${this.route.id}/notify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ type })
      });

      if (response.ok) {
        this.notifications.success('Notification sent successfully');
      } else {
        throw new Error('Notification failed');
      }
    } catch (error) {
      console.error('Error sending notification:', error);
      this.notifications.error('Failed to send notification');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  viewAssignment(assignmentId) {
    this.router.transitionTo('school-transport.assignments.view', assignmentId);
  }

  @action
  createAssignment() {
    this.router.transitionTo('school-transport.assignments.new', {
      queryParams: { route_id: this.route.id }
    });
  }

  @action
  async updateStopStatus(stopId, status) {
    this.isLoading = true;
    try {
      // This would update the stop status in the backend
      const response = await fetch(`/api/school-transport/routes/${this.route.id}/stops/${stopId}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ status })
      });

      if (response.ok) {
        this.notifications.success('Stop status updated');
        this.router.refresh();
      } else {
        throw new Error('Update failed');
      }
    } catch (error) {
      console.error('Error updating stop status:', error);
      this.notifications.error('Failed to update stop status');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async optimizeRoute() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/routes/${this.route.id}/optimize`, {
        method: 'POST'
      });

      if (response.ok) {
        const optimizedRoute = await response.json();
        // Update the route with optimized data
        this.route.stops = optimizedRoute.stops;
        this.route.distance = optimizedRoute.distance;
        this.route.estimated_duration = optimizedRoute.estimated_duration;
        await this.route.save();

        this.notifications.success('Route optimized successfully');
        this.router.refresh();
      } else {
        throw new Error('Optimization failed');
      }
    } catch (error) {
      console.error('Error optimizing route:', error);
      this.notifications.error('Failed to optimize route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async recalculateRoute() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/routes/${this.route.id}/recalculate`, {
        method: 'POST'
      });

      if (response.ok) {
        const routeData = await response.json();
        this.route.distance = routeData.distance;
        this.route.estimated_duration = routeData.estimated_duration;
        await this.route.save();

        this.notifications.success('Route recalculated successfully');
      } else {
        throw new Error('Recalculation failed');
      }
    } catch (error) {
      console.error('Error recalculating route:', error);
      this.notifications.error('Failed to recalculate route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  showEmergencyContacts() {
    // This would show emergency contacts for the route
    this.notifications.info('Emergency contacts feature coming soon');
  }

  @action
  toggleAssignModal() {
    this.showAssignModal = !this.showAssignModal;
  }

  @action
  closeAssignModal() {
    this.showAssignModal = false;
  }
}