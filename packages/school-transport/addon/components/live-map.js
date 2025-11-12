import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

let L = null; // Leaflet

export default class LiveMapComponent extends Component {
  @service realTimeTracking;
  @service schoolTransportApi;
  
  @tracked mapId = `map-${Math.random().toString(36).substr(2, 9)}`;
  @tracked loading = true;
  map = null;
  markers = new Map();
  busMarkers = new Map();
  polylines = new Map();

  constructor() {
    super(...arguments);
    // Accept external mapId if provided via args
    this.mapId = this.args.mapId || (this.mapId + '-' + String(Date.now()));
    this.loadLeaflet();
  }

  async loadLeaflet() {
    try {
      if (!L) {
        // Try to import Leaflet
        const mod = await import('leaflet');
        L = mod.default || mod;
      }

      // Initialize map
      this.initMap();
      
      // Setup real-time tracking if trips provided
      if (this.args.trips) {
        this.setupRealtimeTracking();
        this.loadTrips(this.args.trips);
      }
    } catch (err) {
      console.warn('Leaflet not available:', err);
      this.loading = false;
    }
  }

  initMap() {
    const el = document.getElementById(this.mapId);
    if (!el || !L) {
      this.loading = false;
      return;
    }

    this.map = L.map(el).setView([37.7749, -122.4194], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19
    }).addTo(this.map);

    this.loading = false;
    
    // Add DOM listener for external events to add/update markers
    el.addEventListener('map:addOrUpdateMarker', (e) => {
      const { id, latitude, longitude, popup } = e.detail || {};
      if (id && latitude && longitude) {
        this.addOrUpdateMarker(id, { latitude, longitude, popup });
      }
    });
    el.addEventListener('map:removeMarker', (e) => {
      const { id } = e.detail || {};
      if (id) this.removeMarker(id);
    });
    el.addEventListener('map:centerTo', (e) => {
      const { id } = e.detail || {};
      if (id) this.centerTo(id);
    });
    el.addEventListener('map:addPolyline', (e) => {
      const { id, coordinates, options } = e.detail || {};
      if (id && Array.isArray(coordinates) && coordinates.length) {
        this.addPolyline(id, coordinates, options || {});
      }
    });
    el.addEventListener('map:removePolyline', (e) => {
      const { id } = e.detail || {};
      if (id) this.removePolyline(id);
    });
  }

  /**
   * Setup real-time tracking listeners
   */
  setupRealtimeTracking() {
    this.realTimeTracking.on('bus.location.updated', (data) => {
      this.updateBusPosition(data.tracking_log);
    });

    this.realTimeTracking.on('trip.status.changed', (data) => {
      this.updateTripMarker(data.trip);
    });
  }

  /**
   * Load trips on map
   */
  async loadTrips(trips) {
    if (!this.map) return;

    const bounds = L.latLngBounds();
    let hasValidCoordinates = false;

    for (const trip of trips) {
      if (trip.current_location?.latitude && trip.current_location?.longitude) {
        const latLng = [trip.current_location.latitude, trip.current_location.longitude];
        bounds.extend(latLng);
        hasValidCoordinates = true;
        
        this.addBusMarker(trip);
      }

      // Draw route if available
      if (trip.route?.stops) {
        this.drawRoutePolyline(trip.route);
      }
    }

    // Fit map to show all buses
    if (hasValidCoordinates) {
      this.map.fitBounds(bounds, { padding: [50, 50] });
    }
  }

  /**
   * Add or update bus marker
   */
  addBusMarker(trip) {
    if (!trip.current_location?.latitude || !trip.current_location?.longitude) return;

    const busId = trip.bus_uuid || trip.id;
    const latLng = [trip.current_location.latitude, trip.current_location.longitude];

    if (this.busMarkers.has(busId)) {
      // Update existing marker
      const marker = this.busMarkers.get(busId);
      marker.setLatLng(latLng);
      marker.getPopup().setContent(this.createBusPopupContent(trip));
    } else {
      // Create new bus marker with custom icon
      const icon = L.divIcon({
        html: `<div class="bus-marker-icon">🚌</div>`,
        className: `bus-marker bus-marker-${trip.status || 'active'}`,
        iconSize: [32, 32],
        iconAnchor: [16, 32]
      });

      const marker = L.marker(latLng, { icon })
        .bindPopup(this.createBusPopupContent(trip))
        .addTo(this.map);

      this.busMarkers.set(busId, marker);
    }
  }

  /**
   * Create popup content for bus marker
   */
  createBusPopupContent(trip) {
    return `
      <div class="bus-popup">
        <h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600;">
          Bus ${trip.bus?.vehicle_number || trip.bus_uuid?.substring(0, 8) || 'N/A'}
        </h4>
        <div style="font-size: 12px; color: #6b7280;">
          <p style="margin: 4px 0;"><strong>Route:</strong> ${trip.route?.name || 'N/A'}</p>
          <p style="margin: 4px 0;"><strong>Driver:</strong> ${trip.driver?.full_name || 'N/A'}</p>
          <p style="margin: 4px 0;"><strong>Status:</strong> <span class="status-badge status-${trip.status}">${trip.status || 'unknown'}</span></p>
          <p style="margin: 4px 0;"><strong>Students:</strong> ${trip.students_checked_in || 0}/${trip.total_students || 0}</p>
          <p style="margin: 4px 0;"><strong>Stops:</strong> ${trip.completed_stops || 0}/${trip.total_stops || 0}</p>
          ${trip.delay_minutes ? `<p style="margin: 4px 0; color: #dc2626;"><strong>Delayed:</strong> ${trip.delay_minutes} min</p>` : ''}
        </div>
      </div>
    `;
  }

  /**
   * Draw route polyline
   */
  drawRoutePolyline(route) {
    if (!route.stops || route.stops.length === 0) return;

    const routeId = route.uuid || route.id;
    const coordinates = route.stops
      .filter(stop => stop.latitude && stop.longitude)
      .map(stop => [stop.latitude, stop.longitude]);

    if (coordinates.length > 1) {
      // Remove existing polyline if any
      if (this.polylines.has(routeId)) {
        this.polylines.get(routeId).remove();
      }

      const polyline = L.polyline(coordinates, {
        color: '#3b82f6',
        weight: 3,
        opacity: 0.5,
        dashArray: '10, 10'
      }).addTo(this.map);

      this.polylines.set(routeId, polyline);
    }
  }

  /**
   * Update bus position from real-time tracking
   */
  updateBusPosition(trackingLog) {
    if (!this.map || !trackingLog.bus_uuid) return;

    const busId = trackingLog.bus_uuid;
    const latLng = [trackingLog.latitude, trackingLog.longitude];

    if (this.busMarkers.has(busId)) {
      this.busMarkers.get(busId).setLatLng(latLng);
    } else {
      // Create marker for new bus
      const trip = this.args.trips?.find(t => t.bus_uuid === busId);
      if (trip) {
        trip.current_location = {
          latitude: trackingLog.latitude,
          longitude: trackingLog.longitude
        };
        this.addBusMarker(trip);
      }
    }
  }

  /**
   * Update trip marker from status change
   */
  updateTripMarker(tripData) {
    const busId = tripData.bus_uuid;
    if (this.busMarkers.has(busId)) {
      const marker = this.busMarkers.get(busId);
      marker.getPopup().setContent(this.createBusPopupContent(tripData));
    }
  }

  addOrUpdateMarker(id, { latitude, longitude, popup } = {}) {
    if (!this.map) return;

    if (this.markers.has(id)) {
      const marker = this.markers.get(id);
      marker.setLatLng([latitude, longitude]);
      if (popup) marker.bindPopup(popup);
    } else {
      const m = L.marker([latitude, longitude]);
      if (popup) m.bindPopup(popup);
      m.addTo(this.map);
      this.markers.set(id, m);
    }
  }

  addPolyline(id, coordinates = [], options = {}) {
    if (!this.map) return;
    // convert coordinates to latlngs
    const latlngs = coordinates.map(c => [c.latitude, c.longitude]);
    // Remove existing polyline if present
    const existing = this.markers.get(`polyline-${id}`);
    if (existing) {
      this.map.removeLayer(existing);
      this.markers.delete(`polyline-${id}`);
    }
    const poly = L.polyline(latlngs, options).addTo(this.map);
    this.markers.set(`polyline-${id}`, poly);
  }

  removePolyline(id) {
    const existing = this.markers.get(`polyline-${id}`);
    if (existing) {
      this.map.removeLayer(existing);
      this.markers.delete(`polyline-${id}`);
    }
  }

  removeMarker(id) {
    const marker = this.markers.get(id);
    if (marker) {
      this.map.removeLayer(marker);
      this.markers.delete(id);
    }
  }

  centerTo(id) {
    const marker = this.markers.get(id);
    if (marker) {
      this.map.panTo(marker.getLatLng());
    }
  }

  @action
  resize() {
    if (!this.map) return;
    setTimeout(() => this.map.invalidateSize(), 100);
  }

  willDestroy() {
    super.willDestroy(...arguments);
    
    // Unsubscribe from real-time events
    if (this.realTimeTracking) {
      this.realTimeTracking.off('bus.location.updated');
      this.realTimeTracking.off('trip.status.changed');
    }
    
    // Clean up map
    if (this.map) {
      this.map.remove();
      this.map = null;
    }
    
    this.markers.clear();
    this.busMarkers.clear();
    this.polylines.clear();
  }
}
