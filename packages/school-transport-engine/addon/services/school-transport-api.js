import Service, { inject as service } from '@ember/service';

export default class SchoolTransportApiService extends Service {
  @service fetch;
  @service notifications;

  /**
   * Base API URL for school transport endpoints
   */
  get baseUrl() {
    return '/school-transport/api/v1';
  }

  /**
   * Generic API request handler
   * @param {String} endpoint - API endpoint
   * @param {Object} options - Fetch options
   * @returns {Promise} - API response
   */
  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const defaultOptions = {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    };

    const mergedOptions = { ...defaultOptions, ...options };

    try {
      const response = await this.fetch.fetch(url, mergedOptions);
      
      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'API request failed');
      }

      return await response.json();
    } catch (error) {
      console.error('API Request Error:', error);
      throw error;
    }
  }

  /**
   * GET request
   */
  async get(endpoint, params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const url = queryString ? `${endpoint}?${queryString}` : endpoint;
    
    return await this.request(url, {
      method: 'GET'
    });
  }

  /**
   * POST request
   */
  async post(endpoint, data) {
    return await this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  /**
   * PUT request
   */
  async put(endpoint, data) {
    return await this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  /**
   * PATCH request
   */
  async patch(endpoint, data) {
    return await this.request(endpoint, {
      method: 'PATCH',
      body: JSON.stringify(data)
    });
  }

  /**
   * DELETE request
   */
  async delete(endpoint) {
    return await this.request(endpoint, {
      method: 'DELETE'
    });
  }

  // ===== Student API Methods =====

  /**
   * Get all students
   */
  async getStudents(params = {}) {
    return await this.get('/students', params);
  }

  /**
   * Get single student
   */
  async getStudent(id) {
    return await this.get(`/students/${id}`);
  }

  /**
   * Create new student
   */
  async createStudent(data) {
    return await this.post('/students', data);
  }

  /**
   * Update student
   */
  async updateStudent(id, data) {
    return await this.put(`/students/${id}`, data);
  }

  /**
   * Delete student
   */
  async deleteStudent(id) {
    return await this.delete(`/students/${id}`);
  }

  /**
   * Bulk delete students
   */
  async bulkDeleteStudents(ids) {
    return await this.post('/students/bulk-delete', { ids });
  }

  /**
   * Export students
   */
  async exportStudents(format = 'csv', params = {}) {
    return await this.get('/students/export', { format, ...params });
  }

  /**
   * Import students from file
   */
  async importStudents(file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(`${this.baseUrl}/students/import`, {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error('Import failed');
    }

    return await response.json();
  }

  // ===== Route API Methods =====

  /**
   * Get all routes
   */
  async getRoutes(params = {}) {
    return await this.get('/routes', params);
  }

  /**
   * Get single route
   */
  async getRoute(id) {
    return await this.get(`/routes/${id}`);
  }

  /**
   * Create new route
   */
  async createRoute(data) {
    return await this.post('/routes', data);
  }

  /**
   * Update route
   */
  async updateRoute(id, data) {
    return await this.put(`/routes/${id}`, data);
  }

  /**
   * Delete route
   */
  async deleteRoute(id) {
    return await this.delete(`/routes/${id}`);
  }

  /**
   * Optimize route
   */
  async optimizeRoute(id, params = {}) {
    return await this.post(`/routes/${id}/optimize`, params);
  }

  /**
   * Calculate route distance and duration
   */
  async calculateRoute(stops) {
    return await this.post('/routes/calculate', { stops });
  }

  /**
   * Get route tracking data
   */
  async getRouteTracking(id) {
    return await this.get(`/routes/${id}/tracking`);
  }

  // ===== Assignment API Methods =====

  /**
   * Get all assignments
   */
  async getAssignments(params = {}) {
    return await this.get('/assignments', params);
  }

  /**
   * Get single assignment
   */
  async getAssignment(id) {
    return await this.get(`/assignments/${id}`);
  }

  /**
   * Create new assignment
   */
  async createAssignment(data) {
    return await this.post('/assignments', data);
  }

  /**
   * Update assignment
   */
  async updateAssignment(id, data) {
    return await this.put(`/assignments/${id}`, data);
  }

  /**
   * Delete assignment
   */
  async deleteAssignment(id) {
    return await this.delete(`/assignments/${id}`);
  }

  /**
   * Mark attendance for assignment
   */
  async markAttendance(id, data) {
    return await this.post(`/assignments/${id}/attendance`, data);
  }

  /**
   * Get attendance history
   */
  async getAttendanceHistory(id, params = {}) {
    return await this.get(`/assignments/${id}/attendance`, params);
  }

  /**
   * Check assignment conflicts
   */
  async checkConflicts(data) {
    return await this.post('/assignments/check-conflicts', data);
  }

  // ===== Communication API Methods =====

  /**
   * Get all communications
   */
  async getCommunications(params = {}) {
    return await this.get('/communications', params);
  }

  /**
   * Get single communication
   */
  async getCommunication(id) {
    return await this.get(`/communications/${id}`);
  }

  /**
   * Send communication
   */
  async sendCommunication(data) {
    return await this.post('/communications', data);
  }

  /**
   * Update communication
   */
  async updateCommunication(id, data) {
    return await this.put(`/communications/${id}`, data);
  }

  /**
   * Delete communication
   */
  async deleteCommunication(id) {
    return await this.delete(`/communications/${id}`);
  }

  /**
   * Get communication delivery stats
   */
  async getDeliveryStats(id) {
    return await this.get(`/communications/${id}/stats`);
  }

  /**
   * Resend communication
   */
  async resendCommunication(id, recipientIds) {
    return await this.post(`/communications/${id}/resend`, { recipient_ids: recipientIds });
  }

  /**
   * Get communication templates
   */
  async getTemplates(params = {}) {
    return await this.get('/communication-templates', params);
  }

  // ===== Report API Methods =====

  /**
   * Get all reports
   */
  async getReports(params = {}) {
    return await this.get('/reports', params);
  }

  /**
   * Get single report
   */
  async getReport(id) {
    return await this.get(`/reports/${id}`);
  }

  /**
   * Generate report
   */
  async generateReport(data) {
    return await this.post('/reports', data);
  }

  /**
   * Update report
   */
  async updateReport(id, data) {
    return await this.put(`/reports/${id}`, data);
  }

  /**
   * Delete report
   */
  async deleteReport(id) {
    return await this.delete(`/reports/${id}`);
  }

  /**
   * Download report
   */
  async downloadReport(id, format = 'pdf') {
    const response = await fetch(`${this.baseUrl}/reports/${id}/download?format=${format}`, {
      method: 'GET',
      headers: {
        'Accept': 'application/octet-stream'
      }
    });

    if (!response.ok) {
      throw new Error('Download failed');
    }

    return await response.blob();
  }

  /**
   * Get report data
   */
  async getReportData(id) {
    return await this.get(`/reports/${id}/data`);
  }

  /**
   * Schedule report
   */
  async scheduleReport(data) {
    return await this.post('/scheduled-reports', data);
  }

  // ===== Real-time Tracking API Methods =====

  /**
   * Get vehicle location
   */
  async getVehicleLocation(vehicleId) {
    return await this.get(`/tracking/vehicles/${vehicleId}/location`);
  }

  /**
   * Get route progress
   */
  async getRouteProgress(routeId) {
    return await this.get(`/tracking/routes/${routeId}/progress`);
  }

  /**
   * Update vehicle location
   */
  async updateVehicleLocation(vehicleId, data) {
    return await this.post(`/tracking/vehicles/${vehicleId}/location`, data);
  }

  /**
   * Get ETA for stop
   */
  async getStopETA(routeId, stopId) {
    return await this.get(`/tracking/routes/${routeId}/stops/${stopId}/eta`);
  }

  // ===== Parent Portal API Methods =====

  /**
   * Get parent dashboard data
   */
  async getParentDashboard(parentId) {
    return await this.get(`/parents/${parentId}/dashboard`);
  }

  /**
   * Get student location for parent
   */
  async getStudentLocation(studentId) {
    return await this.get(`/parents/students/${studentId}/location`);
  }

  /**
   * Get arrival notifications
   */
  async getArrivalNotifications(parentId, params = {}) {
    return await this.get(`/parents/${parentId}/notifications`, params);
  }

  /**
   * Update notification preferences
   */
  async updateNotificationPreferences(parentId, data) {
    return await this.put(`/parents/${parentId}/notification-preferences`, data);
  }

  // ===== Safety & Compliance API Methods =====

  /**
   * Get driver certifications
   */
  async getDriverCertifications(driverId) {
    return await this.get(`/drivers/${driverId}/certifications`);
  }

  /**
   * Get vehicle inspections
   */
  async getVehicleInspections(vehicleId, params = {}) {
    return await this.get(`/vehicles/${vehicleId}/inspections`, params);
  }

  /**
   * Create incident report
   */
  async createIncidentReport(data) {
    return await this.post('/incidents', data);
  }

  /**
   * Get incident reports
   */
  async getIncidentReports(params = {}) {
    return await this.get('/incidents', params);
  }

  /**
   * Get compliance status
   */
  async getComplianceStatus(params = {}) {
    return await this.get('/compliance/status', params);
  }

  // ===== Analytics API Methods =====

  /**
   * Get dashboard metrics
   */
  async getDashboardMetrics(params = {}) {
    return await this.get('/analytics/dashboard', params);
  }

  /**
   * Get attendance statistics
   */
  async getAttendanceStats(params = {}) {
    return await this.get('/analytics/attendance', params);
  }

  /**
   * Get route efficiency metrics
   */
  async getRouteEfficiency(params = {}) {
    return await this.get('/analytics/route-efficiency', params);
  }

  /**
   * Get safety metrics
   */
  async getSafetyMetrics(params = {}) {
    return await this.get('/analytics/safety', params);
  }
}
