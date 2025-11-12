import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportDashboardRoute extends Route {
  @service store;
  @service currentUser;

  async model() {
    // Load dashboard data in parallel
    const [
      studentsStats,
      routesStats, 
      assignmentsStats,
      recentCommunications,
      todayAttendance
    ] = await Promise.allSettled([
      this.loadStudentsStats(),
      this.loadRoutesStats(), 
      this.loadAssignmentsStats(),
      this.loadRecentCommunications(),
      this.loadTodayAttendance()
    ]);

    return {
      students: studentsStats.value || {},
      routes: routesStats.value || {},
      assignments: assignmentsStats.value || {},
      communications: recentCommunications.value || [],
      attendance: todayAttendance.value || {}
    };
  }

  async loadStudentsStats() {
    try {
      const response = await fetch('/api/school-transport/dashboard/stats');
      return await response.json();
    } catch (error) {
      console.error('Error loading student stats:', error);
      return {};
    }
  }

  async loadRoutesStats() {
    try {
      const response = await fetch('/api/school-transport/dashboard/route-efficiency');
      return await response.json();
    } catch (error) {
      console.error('Error loading route stats:', error);
      return {};
    }
  }

  async loadAssignmentsStats() {
    try {
      const response = await fetch('/api/school-transport/dashboard/student-attendance');
      return await response.json();
    } catch (error) {
      console.error('Error loading assignment stats:', error);
      return {};
    }
  }

  async loadRecentCommunications() {
    try {
      const communications = await this.store.query('communication', {
        limit: 10,
        sort: '-created_at'
      });
      return communications.toArray();
    } catch (error) {
      console.error('Error loading communications:', error);
      return [];
    }
  }

  async loadTodayAttendance() {
    try {
      const today = new Date().toISOString().split('T')[0];
      const response = await fetch(`/api/school-transport/assignments/attendance-report?start_date=${today}&end_date=${today}`);
      return await response.json();
    } catch (error) {
      console.error('Error loading today attendance:', error);
      return {};
    }
  }
}