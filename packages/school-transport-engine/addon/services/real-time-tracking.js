import Service, { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import Evented from '@ember/object/evented';

export default class RealTimeTrackingService extends Service.extend(Evented) {
  @service schoolTransportApi;
  @service notifications;

  @tracked isConnected = false;
  @tracked activeTracking = new Map();
  @tracked vehicleLocations = new Map();
  @tracked routeProgress = new Map();

  websocket = null;
  reconnectAttempts = 0;
  maxReconnectAttempts = 5;
  reconnectDelay = 3000;
  reconnectTimer = null;
  pingInterval = null;

  /**
   * Initialize WebSocket connection
   */
  connect(authToken) {
    if (this.websocket?.readyState === WebSocket.OPEN) {
      console.log('WebSocket already connected');
      return;
    }

    const wsUrl = this.getWebSocketUrl();
    this.websocket = new WebSocket(wsUrl);

    this.websocket.onopen = () => {
      console.log('WebSocket connected');
      this.isConnected = true;
      this.reconnectAttempts = 0;

      // Authenticate
      this.send({
        type: 'auth',
        token: authToken
      });

      // Start ping interval to keep connection alive
      this.startPingInterval();

      this.trigger('connected');
    };

    this.websocket.onmessage = (event) => {
      this.handleMessage(JSON.parse(event.data));
    };

    this.websocket.onerror = (error) => {
      console.error('WebSocket error:', error);
      this.trigger('error', error);
    };

    this.websocket.onclose = () => {
      console.log('WebSocket disconnected');
      this.isConnected = false;
      this.stopPingInterval();
      this.trigger('disconnected');

      // Attempt to reconnect
      this.attemptReconnect(authToken);
    };
  }

  /**
   * Disconnect WebSocket
   */
  disconnect() {
    if (this.websocket) {
      this.websocket.close();
      this.websocket = null;
    }
    this.stopPingInterval();
    this.clearReconnectTimer();
    this.isConnected = false;
  }

  /**
   * Get WebSocket URL
   */
  getWebSocketUrl() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = window.location.host;
    return `${protocol}//${host}/school-transport/ws`;
  }

  /**
   * Send message through WebSocket
   */
  send(data) {
    if (this.websocket?.readyState === WebSocket.OPEN) {
      this.websocket.send(JSON.stringify(data));
    } else {
      console.error('WebSocket not connected');
    }
  }

  /**
   * Handle incoming WebSocket messages
   */
  handleMessage(data) {
    switch (data.type) {
      case 'location_update':
        this.handleLocationUpdate(data);
        break;
      case 'route_progress':
        this.handleRouteProgress(data);
        break;
      case 'eta_update':
        this.handleETAUpdate(data);
        break;
      case 'alert':
        this.handleAlert(data);
        break;
      case 'pong':
        // Keep-alive response
        break;
      default:
        console.log('Unknown message type:', data.type);
    }
  }

  /**
   * Handle location update
   */
  handleLocationUpdate(data) {
    const { vehicle_id, location, timestamp } = data;
    
    this.vehicleLocations.set(vehicle_id, {
      latitude: location.latitude,
      longitude: location.longitude,
      speed: location.speed,
      heading: location.heading,
      timestamp: timestamp
    });

    this.trigger('location-update', {
      vehicleId: vehicle_id,
      location: location,
      timestamp: timestamp
    });
  }

  /**
   * Handle route progress update
   */
  handleRouteProgress(data) {
    const { route_id, progress } = data;
    
    this.routeProgress.set(route_id, progress);

    this.trigger('progress-update', {
      routeId: route_id,
      progress: progress
    });
  }

  /**
   * Handle ETA update
   */
  handleETAUpdate(data) {
    const { route_id, stop_id, eta } = data;

    this.trigger('eta-update', {
      routeId: route_id,
      stopId: stop_id,
      eta: eta
    });

    // Send notification to parents if enabled
    if (eta <= 5) { // 5 minutes warning
      this.notifications.info(`Bus arriving at stop in ${eta} minutes`);
    }
  }

  /**
   * Handle alert/notification
   */
  handleAlert(data) {
    const { severity, message, route_id, vehicle_id } = data;

    this.trigger('alert', {
      severity: severity,
      message: message,
      routeId: route_id,
      vehicleId: vehicle_id
    });

    // Show notification based on severity
    switch (severity) {
      case 'emergency':
        this.notifications.error(message);
        break;
      case 'warning':
        this.notifications.warning(message);
        break;
      case 'info':
        this.notifications.info(message);
        break;
    }
  }

  /**
   * Start tracking a vehicle
   */
  startVehicleTracking(vehicleId) {
    this.send({
      type: 'subscribe',
      resource: 'vehicle',
      id: vehicleId
    });

    this.activeTracking.set(`vehicle-${vehicleId}`, true);
  }

  /**
   * Stop tracking a vehicle
   */
  stopVehicleTracking(vehicleId) {
    this.send({
      type: 'unsubscribe',
      resource: 'vehicle',
      id: vehicleId
    });

    this.activeTracking.delete(`vehicle-${vehicleId}`);
    this.vehicleLocations.delete(vehicleId);
  }

  /**
   * Start tracking a route
   */
  startRouteTracking(routeId) {
    this.send({
      type: 'subscribe',
      resource: 'route',
      id: routeId
    });

    this.activeTracking.set(`route-${routeId}`, true);
  }

  /**
   * Stop tracking a route
   */
  stopRouteTracking(routeId) {
    this.send({
      type: 'unsubscribe',
      resource: 'route',
      id: routeId
    });

    this.activeTracking.delete(`route-${routeId}`);
    this.routeProgress.delete(routeId);
  }

  /**
   * Get current vehicle location
   */
  getVehicleLocation(vehicleId) {
    return this.vehicleLocations.get(vehicleId);
  }

  /**
   * Get current route progress
   */
  getRouteProgress(routeId) {
    return this.routeProgress.get(routeId);
  }

  /**
   * Request ETA for specific stop
   */
  async requestStopETA(routeId, stopId) {
    try {
      const eta = await this.schoolTransportApi.getStopETA(routeId, stopId);
      return eta;
    } catch (error) {
      console.error('Error fetching ETA:', error);
      return null;
    }
  }

  /**
   * Start ping interval to keep connection alive
   */
  startPingInterval() {
    this.pingInterval = setInterval(() => {
      this.send({ type: 'ping' });
    }, 30000); // Ping every 30 seconds
  }

  /**
   * Stop ping interval
   */
  stopPingInterval() {
    if (this.pingInterval) {
      clearInterval(this.pingInterval);
      this.pingInterval = null;
    }
  }

  /**
   * Attempt to reconnect WebSocket
   */
  attemptReconnect(authToken) {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('Max reconnect attempts reached');
      this.notifications.error('Unable to maintain real-time connection');
      return;
    }

    this.reconnectAttempts++;
    console.log(`Attempting to reconnect... (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);

    this.reconnectTimer = setTimeout(() => {
      this.connect(authToken);
    }, this.reconnectDelay);
  }

  /**
   * Clear reconnect timer
   */
  clearReconnectTimer() {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
  }

  /**
   * Calculate distance between two coordinates
   */
  calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radius of the Earth in kilometers
    const dLat = this.degToRad(lat2 - lat1);
    const dLon = this.degToRad(lon2 - lon1);
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(this.degToRad(lat1)) * Math.cos(this.degToRad(lat2)) *
      Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const distance = R * c;
    return distance;
  }

  /**
   * Convert degrees to radians
   */
  degToRad(deg) {
    return deg * (Math.PI / 180);
  }

  /**
   * Format ETA for display
   */
  formatETA(minutes) {
    if (minutes < 1) {
      return 'Arriving now';
    } else if (minutes === 1) {
      return '1 minute';
    } else if (minutes < 60) {
      return `${Math.round(minutes)} minutes`;
    } else {
      const hours = Math.floor(minutes / 60);
      const mins = Math.round(minutes % 60);
      return `${hours}h ${mins}m`;
    }
  }

  /**
   * Check if vehicle is near stop
   */
  isVehicleNearStop(vehicleId, stopLat, stopLon, thresholdKm = 0.5) {
    const vehicleLocation = this.getVehicleLocation(vehicleId);
    
    if (!vehicleLocation) {
      return false;
    }

    const distance = this.calculateDistance(
      vehicleLocation.latitude,
      vehicleLocation.longitude,
      stopLat,
      stopLon
    );

    return distance <= thresholdKm;
  }

  /**
   * Cleanup on service destroy
   */
  willDestroy() {
    super.willDestroy(...arguments);
    this.disconnect();
    
    // Clear all tracking
    this.activeTracking.clear();
    this.vehicleLocations.clear();
    this.routeProgress.clear();
  }
}
