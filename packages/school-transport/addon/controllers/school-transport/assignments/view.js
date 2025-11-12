import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportAssignmentsViewController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked showEditModal = false;
  @tracked showDeleteModal = false;
  @tracked showStartModal = false;
  @tracked showCompleteModal = false;
  @tracked selectedTab = 'overview';

  get assignment() {
    return this.model.assignment;
  }

  get route() {
    return this.model.route;
  }

  get vehicle() {
    return this.model.vehicle;
  }

  get driver() {
    return this.model.driver;
  }

  get assignedStudents() {
    return this.model.assignedStudents || [];
  }

  get stops() {
    return this.route?.stops || [];
  }

  get assignmentProgress() {
    const stops = this.stops;
    if (stops.length === 0) return { completed: 0, total: 0, percentage: 0 };

    // This would typically come from real-time tracking
    const completed = stops.filter(stop => stop.status === 'completed').length;
    return {
      completed,
      total: stops.length,
      percentage: Math.round((completed / stops.length) * 100)
    };
  }

  get currentStop() {
    return this.stops.find(stop => stop.status === 'in_progress');
  }

  get nextStop() {
    const currentIndex = this.stops.findIndex(stop => stop.status === 'in_progress');
    return currentIndex >= 0 && currentIndex < this.stops.length - 1 ? this.stops[currentIndex + 1] : null;
  }

  get assignmentDuration() {
    if (!this.assignment.started_at) return null;

    const start = new Date(this.assignment.started_at);
    const end = this.assignment.completed_at ? new Date(this.assignment.completed_at) : new Date();
    const durationMs = end - start;

    const hours = Math.floor(durationMs / (1000 * 60 * 60));
    const minutes = Math.floor((durationMs % (1000 * 60 * 60)) / (1000 * 60));

    return `${hours}h ${minutes}m`;
  }

  get estimatedVsActualDuration() {
    if (!this.assignment.estimated_duration || !this.assignmentDuration) return null;

    const estimated = this.parseDuration(this.assignment.estimated_duration);
    const actual = this.parseDuration(this.assignmentDuration);

    if (!estimated || !actual) return null;

    const difference = actual - estimated;
    const isOver = difference > 0;

    return {
      estimated: this.formatDuration(estimated),
      actual: this.formatDuration(actual),
      difference: Math.abs(difference),
      isOver,
      status: isOver ? 'over' : 'under'
    };
  }

  get studentAttendance() {
    // This would come from real-time tracking
    return this.assignedStudents.map(student => ({
      ...student,
      status: Math.random() > 0.1 ? 'present' : 'absent', // Mock data
      pickup_time: this.assignment.started_at ? new Date(Date.parse(this.assignment.started_at) + Math.random() * 3600000).toLocaleTimeString() : null,
      dropoff_time: this.assignment.status === 'completed' ? new Date(Date.parse(this.assignment.completed_at) - Math.random() * 3600000).toLocaleTimeString() : null
    }));
  }

  get attendanceSummary() {
    const attendance = this.studentAttendance;
    const present = attendance.filter(s => s.status === 'present').length;
    const absent = attendance.filter(s => s.status === 'absent').length;

    return {
      total: attendance.length,
      present,
      absent,
      presentPercentage: attendance.length > 0 ? Math.round((present / attendance.length) * 100) : 0,
      absentPercentage: attendance.length > 0 ? Math.round((absent / attendance.length) * 100) : 0
    };
  }

  parseDuration(durationStr) {
    // Parse duration string like "2h 30m" into minutes
    const hoursMatch = durationStr.match(/(\d+)h/);
    const minutesMatch = durationStr.match(/(\d+)m/);

    const hours = hoursMatch ? parseInt(hoursMatch[1]) : 0;
    const minutes = minutesMatch ? parseInt(minutesMatch[1]) : 0;

    return hours * 60 + minutes;
  }

  formatDuration(minutes) {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  }

  @action
  selectTab(tab) {
    this.selectedTab = tab;
  }

  @action
  editAssignment() {
    this.router.transitionTo('school-transport.assignments.edit', this.assignment.id);
  }

  @action
  async startAssignment() {
    if (!confirm('Are you sure you want to start this assignment?')) {
      return;
    }

    this.isLoading = true;
    try {
      this.assignment.status = 'in_progress';
      this.assignment.started_at = new Date().toISOString();
      await this.assignment.save();

      // Send notification to driver
      await this.sendNotification('assignment_started');

      this.notifications.success('Assignment started successfully');
      this.router.refresh();
    } catch (error) {
      console.error('Error starting assignment:', error);
      this.notifications.error('Failed to start assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async completeAssignment() {
    if (!confirm('Are you sure you want to mark this assignment as completed?')) {
      return;
    }

    this.isLoading = true;
    try {
      this.assignment.status = 'completed';
      this.assignment.completed_at = new Date().toISOString();
      await this.assignment.save();

      // Send completion notification
      await this.sendNotification('assignment_completed');

      this.notifications.success('Assignment completed successfully');
      this.router.refresh();
    } catch (error) {
      console.error('Error completing assignment:', error);
      this.notifications.error('Failed to complete assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async cancelAssignment() {
    if (!confirm('Are you sure you want to cancel this assignment?')) {
      return;
    }

    this.isLoading = true;
    try {
      this.assignment.status = 'cancelled';
      await this.assignment.save();

      // Send cancellation notification
      await this.sendNotification('assignment_cancelled');

      this.notifications.success('Assignment cancelled successfully');
      this.router.refresh();
    } catch (error) {
      console.error('Error cancelling assignment:', error);
      this.notifications.error('Failed to cancel assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async deleteAssignment() {
    if (!confirm('Are you sure you want to delete this assignment? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      await this.assignment.destroyRecord();
      this.notifications.success('Assignment deleted successfully');
      this.router.transitionTo('school-transport.assignments.index');
    } catch (error) {
      console.error('Error deleting assignment:', error);
      this.notifications.error('Failed to delete assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async updateStopStatus(stopId, status) {
    this.isLoading = true;
    try {
      // Update stop status in the backend
      const response = await fetch(`/api/school-transport/assignments/${this.assignment.id}/stops/${stopId}`, {
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
  async markStudentAttendance(studentId, status) {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/assignments/${this.assignment.id}/attendance`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          student_id: studentId,
          status,
          timestamp: new Date().toISOString()
        })
      });

      if (response.ok) {
        this.notifications.success('Attendance marked successfully');
        this.router.refresh();
      } else {
        throw new Error('Attendance update failed');
      }
    } catch (error) {
      console.error('Error marking attendance:', error);
      this.notifications.error('Failed to mark attendance');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async sendNotification(type) {
    try {
      const response = await fetch(`/api/school-transport/assignments/${this.assignment.id}/notify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ type })
      });

      if (!response.ok) {
        console.error('Failed to send notification');
      }
    } catch (error) {
      console.error('Error sending notification:', error);
    }
  }

  @action
  async callDriver() {
    if (this.driver?.phone) {
      window.open(`tel:${this.driver.phone}`);
    } else {
      this.notifications.error('Driver phone number not available');
    }
  }

  @action
  async messageDriver() {
    if (this.driver?.phone) {
      window.open(`sms:${this.driver.phone}`);
    } else {
      this.notifications.error('Driver phone number not available');
    }
  }

  @action
  async callParent(studentId) {
    const student = this.assignedStudents.find(s => s.id === studentId);
    if (student?.parent_phone) {
      window.open(`tel:${student.parent_phone}`);
    } else {
      this.notifications.error('Parent phone number not available');
    }
  }

  @action
  async exportAssignmentReport() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/assignments/${this.assignment.id}/report`, {
        method: 'GET'
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `assignment-${this.assignment.id}-report.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Report exported successfully');
      } else {
        throw new Error('Export failed');
      }
    } catch (error) {
      console.error('Error exporting report:', error);
      this.notifications.error('Failed to export report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async generateRouteManifest() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/assignments/${this.assignment.id}/manifest`, {
        method: 'GET'
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `route-manifest-${this.assignment.id}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Route manifest generated successfully');
      } else {
        throw new Error('Manifest generation failed');
      }
    } catch (error) {
      console.error('Error generating manifest:', error);
      this.notifications.error('Failed to generate route manifest');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async duplicateAssignment() {
    this.isLoading = true;
    try {
      const duplicatedAssignment = this.store.createRecord('school-transport/assignment', {
        route_id: this.assignment.route_id,
        vehicle_id: this.assignment.vehicle_id,
        driver_id: this.assignment.driver_id,
        scheduled_date: null, // Will be set by user
        scheduled_time: this.assignment.scheduled_time,
        estimated_duration: this.assignment.estimated_duration,
        status: 'scheduled',
        notes: this.assignment.notes,
        special_instructions: this.assignment.special_instructions,
        weather_dependent: this.assignment.weather_dependent,
        assigned_students: [...(this.assignment.assigned_students || [])]
      });

      await duplicatedAssignment.save();
      this.notifications.success('Assignment duplicated successfully');
      this.router.transitionTo('school-transport.assignments.view', duplicatedAssignment.id);
    } catch (error) {
      console.error('Error duplicating assignment:', error);
      this.notifications.error('Failed to duplicate assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async createIncidentReport() {
    this.router.transitionTo('school-transport.incidents.new', {
      queryParams: { assignment_id: this.assignment.id }
    });
  }

  @action
  viewRoute() {
    this.router.transitionTo('school-transport.routes.view', this.assignment.route_id);
  }

  @action
  viewVehicle() {
    // This would transition to vehicle details
    this.notifications.info('Vehicle details view coming soon');
  }

  @action
  viewDriver() {
    // This would transition to driver details
    this.notifications.info('Driver details view coming soon');
  }

  @action
  async updateAssignmentNotes(notes) {
    this.isLoading = true;
    try {
      this.assignment.notes = notes;
      await this.assignment.save();
      this.notifications.success('Notes updated successfully');
    } catch (error) {
      console.error('Error updating notes:', error);
      this.notifications.error('Failed to update notes');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async addTimeLog(action, details = '') {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/assignments/${this.assignment.id}/time-log`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action,
          details,
          timestamp: new Date().toISOString()
        })
      });

      if (response.ok) {
        this.notifications.success('Time log added successfully');
        this.router.refresh();
      } else {
        throw new Error('Time log failed');
      }
    } catch (error) {
      console.error('Error adding time log:', error);
      this.notifications.error('Failed to add time log');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  showEmergencyContacts() {
    // This would show emergency contacts modal
    this.notifications.info('Emergency contacts feature coming soon');
  }

  @action
  async requestBackup() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/assignments/${this.assignment.id}/request-backup`, {
        method: 'POST'
      });

      if (response.ok) {
        this.notifications.success('Backup request sent successfully');
      } else {
        throw new Error('Backup request failed');
      }
    } catch (error) {
      console.error('Error requesting backup:', error);
      this.notifications.error('Failed to request backup');
    } finally {
      this.isLoading = false;
    }
  }
}