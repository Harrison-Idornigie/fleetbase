import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportDashboardController extends Controller {
  @service notifications;
  @service router;

  @tracked isLoadingWidget = false;
  @tracked selectedDateRange = '30'; // days

  get totalStudents() {
    return this.model.students.total_students || 0;
  }

  get activeStudents() {
    return this.model.students.active_students || 0;
  }

  get specialNeedsStudents() {
    return this.model.students.students_with_special_needs || 0;
  }

  get totalRoutes() {
    return this.model.routes.total_routes || 0;
  }

  get activeRoutes() {
    return this.model.routes.routes_by_efficiency?.filter(r => r.efficiency_score > 0).length || 0;
  }

  get overutilizedRoutes() {
    return this.model.routes.overutilized_routes || 0;
  }

  get todayAttendanceRate() {
    const summary = this.model.attendance.summary || {};
    return summary.attendance_rate || 0;
  }

  get todayAbsentCount() {
    const summary = this.model.attendance.summary || {};
    return summary.total_absent || 0;
  }

  get recentCommunications() {
    return this.model.communications || [];
  }

  get studentsBySchool() {
    const studentsBySchool = this.model.students.students_by_school || [];
    return studentsBySchool.map(item => ({
      name: item.school,
      count: item.count
    }));
  }

  get studentsByGrade() {
    const studentsByGrade = this.model.students.students_by_grade || [];
    return studentsByGrade.map(item => ({
      name: item.grade,
      count: item.count
    }));
  }

  get topPerformingRoutes() {
    const routes = this.model.routes.routes_by_efficiency || [];
    return routes.slice(0, 5).map(route => ({
      name: route.name,
      school: route.school,
      efficiency: route.efficiency_score,
      utilization: route.utilization_percentage
    }));
  }

  get routeEfficiencyData() {
    const routes = this.model.routes.routes_by_efficiency || [];
    return {
      labels: routes.map(r => r.name),
      datasets: [{
        label: 'Efficiency Score',
        data: routes.map(r => r.efficiency_score),
        backgroundColor: 'rgba(59, 130, 246, 0.6)'
      }]
    };
  }

  get attendanceTrendData() {
    // Mock data for demonstration - in real app would come from API
    const last7Days = [];
    for (let i = 6; i >= 0; i--) {
      const date = new Date();
      date.setDate(date.getDate() - i);
      last7Days.push({
        date: date.toLocaleDateString('en-US', { weekday: 'short' }),
        attendance: Math.floor(Math.random() * 20) + 80 // Mock 80-100% attendance
      });
    }

    return {
      labels: last7Days.map(d => d.date),
      datasets: [{
        label: 'Attendance Rate (%)',
        data: last7Days.map(d => d.attendance),
        borderColor: 'rgba(34, 197, 94, 1)',
        backgroundColor: 'rgba(34, 197, 94, 0.1)',
        tension: 0.4
      }]
    };
  }

  @action
  async refreshDashboard() {
    this.isLoadingWidget = true;
    try {
      await this.router.refresh();
      this.notifications.success('Dashboard refreshed successfully');
    } catch (error) {
      console.error('Error refreshing dashboard:', error);
      this.notifications.error('Failed to refresh dashboard');
    } finally {
      this.isLoadingWidget = false;
    }
  }

  @action
  navigateToStudents() {
    this.router.transitionTo('school-transport.students.index');
  }

  @action
  navigateToRoutes() {
    this.router.transitionTo('school-transport.routes.index');
  }

  @action
  navigateToAssignments() {
    this.router.transitionTo('school-transport.assignments.index');
  }

  @action
  navigateToCommunications() {
    this.router.transitionTo('school-transport.communications.index');
  }

  @action
  navigateToReports() {
    this.router.transitionTo('school-transport.reports.attendance');
  }

  @action
  viewStudent(studentId) {
    this.router.transitionTo('school-transport.students.view', studentId);
  }

  @action
  viewRoute(routeId) {
    this.router.transitionTo('school-transport.routes.view', routeId);
  }

  @action
  viewCommunication(communicationId) {
    this.router.transitionTo('school-transport.communications.view', communicationId);
  }

  @action
  async createEmergencyAlert() {
    try {
      // This would open a modal to create emergency communication
      this.router.transitionTo('school-transport.communications.new', {
        queryParams: { type: 'emergency' }
      });
    } catch (error) {
      console.error('Error creating emergency alert:', error);
      this.notifications.error('Failed to create emergency alert');
    }
  }

  @action
  changeDateRange(range) {
    this.selectedDateRange = range;
    // This would trigger data reload with new date range
    this.refreshDashboard();
  }
}