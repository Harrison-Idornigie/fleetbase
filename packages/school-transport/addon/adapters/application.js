import JSONAPIAdapter from '@ember-data/adapter/json-api';
import { inject as service } from '@ember/service';

export default class ApplicationAdapter extends JSONAPIAdapter {
  @service session;

  /**
   * Namespace for all API requests
   */
  get namespace() {
    return 'school-transport/api/v1';
  }

  /**
   * Add authorization header to all requests
   */
  get headers() {
    const headers = {};
    
    if (this.session.isAuthenticated) {
      headers['Authorization'] = `Bearer ${this.session.data.authenticated.token}`;
    }
    
    headers['Content-Type'] = 'application/vnd.api+json';
    headers['Accept'] = 'application/vnd.api+json';
    
    return headers;
  }

  /**
   * Handle query parameters
   */
  buildQuery(snapshot) {
    const query = super.buildQuery(snapshot);
    
    // Add any default query parameters
    query['include'] = this.getDefaultIncludes(snapshot.modelName);
    
    return query;
  }

  /**
   * Get default includes for a model
   */
  getDefaultIncludes(modelName) {
    const includesMap = {
      'student': 'bus_assignments,attendance_records',
      'school-route': 'bus_assignments,students',
      'bus-assignment': 'student,route',
      'communication': 'recipients',
      'report': 'generated_by'
    };
    
    return includesMap[modelName] || '';
  }

  /**
   * Handle errors from API
   */
  handleResponse(status, headers, payload, requestData) {
    if (status === 401) {
      // Unauthorized - redirect to login
      this.session.invalidate();
    }
    
    return super.handleResponse(status, headers, payload, requestData);
  }

  /**
   * URL for finding a record
   */
  urlForFindRecord(id, modelName, snapshot) {
    return super.urlForFindRecord(id, modelName, snapshot);
  }

  /**
   * URL for querying records
   */
  urlForQuery(query, modelName) {
    return super.urlForQuery(query, modelName);
  }

  /**
   * URL for creating a record
   */
  urlForCreateRecord(modelName, snapshot) {
    return super.urlForCreateRecord(modelName, snapshot);
  }

  /**
   * URL for updating a record
   */
  urlForUpdateRecord(id, modelName, snapshot) {
    return super.urlForUpdateRecord(id, modelName, snapshot);
  }

  /**
   * URL for deleting a record
   */
  urlForDeleteRecord(id, modelName, snapshot) {
    return super.urlForDeleteRecord(id, modelName, snapshot);
  }
}
