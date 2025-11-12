import ApplicationAdapter from './application';

export default class SchoolRouteAdapter extends ApplicationAdapter {
  /**
   * Custom URL for route optimization
   */
  urlForOptimize(id) {
    return `${this.buildURL('school-route', id)}/optimize`;
  }

  /**
   * Custom URL for route calculation
   */
  urlForCalculate() {
    return `${this.buildURL()}/routes/calculate`;
  }

  /**
   * Custom URL for route tracking
   */
  urlForTracking(id) {
    return `${this.buildURL('school-route', id)}/tracking`;
  }

  /**
   * Optimize a route
   */
  async optimizeRoute(id, params = {}) {
    const response = await fetch(this.urlForOptimize(id), {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify(params)
    });

    if (!response.ok) {
      throw new Error('Route optimization failed');
    }

    return await response.json();
  }

  /**
   * Calculate route distance and duration
   */
  async calculateRoute(stops) {
    const response = await fetch(this.urlForCalculate(), {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify({ stops })
    });

    if (!response.ok) {
      throw new Error('Route calculation failed');
    }

    return await response.json();
  }

  /**
   * Get route tracking data
   */
  async getTracking(id) {
    const response = await fetch(this.urlForTracking(id), {
      method: 'GET',
      headers: this.headers
    });

    if (!response.ok) {
      throw new Error('Failed to get tracking data');
    }

    return await response.json();
  }
}
