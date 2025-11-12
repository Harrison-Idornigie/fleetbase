import ApplicationAdapter from './application';

export default class BusAssignmentAdapter extends ApplicationAdapter {
  /**
   * Custom URL for marking attendance
   */
  urlForAttendance(id) {
    return `${this.buildURL('bus-assignment', id)}/attendance`;
  }

  /**
   * Custom URL for checking conflicts
   */
  urlForCheckConflicts() {
    return `${this.buildURL()}/assignments/check-conflicts`;
  }

  /**
   * Mark attendance for an assignment
   */
  async markAttendance(id, data) {
    const response = await fetch(this.urlForAttendance(id), {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify(data)
    });

    if (!response.ok) {
      throw new Error('Failed to mark attendance');
    }

    return await response.json();
  }

  /**
   * Get attendance history for an assignment
   */
  async getAttendanceHistory(id, params = {}) {
    const url = this.urlForAttendance(id);
    const queryParams = new URLSearchParams(params);
    const response = await fetch(`${url}?${queryParams.toString()}`, {
      method: 'GET',
      headers: this.headers
    });

    if (!response.ok) {
      throw new Error('Failed to get attendance history');
    }

    return await response.json();
  }

  /**
   * Check for assignment conflicts
   */
  async checkConflicts(data) {
    const response = await fetch(this.urlForCheckConflicts(), {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify(data)
    });

    if (!response.ok) {
      throw new Error('Failed to check conflicts');
    }

    return await response.json();
  }
}
