import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class ParentDashboardComponent extends Component {
  @service schoolTransportApi;
  @service realTimeTracking;
  @service notifications;

  @tracked students = [];
  @tracked assignments = [];
  @tracked preferences = {};
  @tracked selectedStudent = null;
  @tracked selectedVehicle = null;
  @tracked metrics = {
    childrenCount: 0,
    activeRoutesCount: 0,
    nearbyVehicles: 0,
    activeAlerts: 0
  };

  assignmentColumns = [
    { key: 'student', label: 'Student' },
    { key: 'route', label: 'Route' },
    { key: 'pickup', label: 'Pickup' },
    { key: 'dropoff', label: 'Dropoff' },
    { key: 'status', label: 'Status' }
  ];

  constructor() {
    super(...arguments);
    this.loadDashboard();
  }

  async loadDashboard() {
    try {
      const data = await this.schoolTransportApi.getParentDashboard();
      this.students = data.students || [];
      this.assignments = data.assignments || [];
      this.preferences = data.preferences || {};
      this.metrics = data.metrics || this.metrics;
      if (!this.metrics.routeEfficiencyChart) {
        // default chart data for Route Efficiency if API doesn't provide
        this.metrics.routeEfficiencyChart = {
          labels: ['Mon','Tue','Wed','Thu','Fri'],
          datasets: [{
            label: 'On-time %',
            data: [95, 92, 94, 90, 96],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.1)'
          }]
        };
      }
    } catch (error) {
      console.error('Error loading parent dashboard:', error);
      this.notifications.error('Unable to load parent dashboard');
    }
  }

  @action
  selectStudent(student) {
    this.selectedStudent = student;

    // Subscribe to the student's vehicle location if assigned
    if (student.current_vehicle_id) {
      this.realTimeTracking.startVehicleTracking(student.current_vehicle_id);
      this.selectedVehicle = student.current_vehicle_id;
    }
  }

  @action
  selectVehicle(vehicleId) {
    this.selectedVehicle = vehicleId;
  }

  @action
  openAssignment(row) {
    // If row is assignment, navigate or show assignment details
    if (row && row.id) {
      // trigger route or modal using router service if available
      this.args.onOpenAssignment?.(row);
    }
  }

  @action
  savePreferences(updatedPrefs) {
    this.preferences = updatedPrefs;
    this.schoolTransportApi.updateNotificationPreferences(updatedPrefs)
      .then(() => this.notifications.success('Preferences saved'))
      .catch(err => {
        console.error('Error saving preferences:', err);
        this.notifications.error('Unable to save preferences');
      });
  }

  @action
  getStudentStatus(student) {
    // returns a small status string
    if (!student.assignments || student.assignments.length === 0) return 'Unassigned';
    return student.assignments[0].status || 'Active';
  }

  @action
  getNextPickupTime(student) {
    const next = (student.assignments || []).find(a => a.type === 'Pickup');
    return next?.pickup_time || 'TBD';
  }

  @action
  openSupport() {
    this.args.onOpenSupport?.();
  }

  @action
  openSafetyReports() {
    this.args.onOpenSafetyReports?.();
  }

  @action
  contactDriver() {
    if (!this.selectedVehicle) {
      this.notifications.warning('Select a vehicle to contact the driver');
      return;
    }
    this.args.onContactDriver?.(this.selectedVehicle);
  }
}
