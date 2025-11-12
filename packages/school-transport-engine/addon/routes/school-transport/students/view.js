import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportStudentsViewRoute extends Route {
  @service store;

  async model(params) {
    const student = await this.store.findRecord('student', params.student_id, {
      include: 'bus_assignments,bus_assignments.route,attendance_records,communications'
    });

    // Load related data
    const [assignmentsHistory, recentAttendance] = await Promise.all([
      this.loadAssignmentsHistory(student.id),
      this.loadRecentAttendance(student.id)
    ]);

    return {
      student,
      assignmentsHistory,
      recentAttendance
    };
  }

  async loadAssignmentsHistory(studentId) {
    try {
      const response = await fetch(`/api/school-transport/students/${studentId}/assignments`);
      return await response.json();
    } catch (error) {
      console.error('Error loading assignments history:', error);
      return { assignments: [] };
    }
  }

  async loadRecentAttendance(studentId) {
    try {
      const endDate = new Date().toISOString().split('T')[0];
      const startDate = new Date();
      startDate.setDate(startDate.getDate() - 30);
      const startDateStr = startDate.toISOString().split('T')[0];

      const response = await fetch(
        `/api/school-transport/assignments/attendance-report?student_id=${studentId}&start_date=${startDateStr}&end_date=${endDate}`
      );
      return await response.json();
    } catch (error) {
      console.error('Error loading recent attendance:', error);
      return { summary: {}, records: [] };
    }
  }
}