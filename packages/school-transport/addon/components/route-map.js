import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class RouteMapComponent extends Component {
  @service fetch;
  @tracked map;
  @tracked markers = [];
  @tracked polyline;

  /**
   * Initialize the Leaflet map
   */
  @action
  initializeMap(element) {
    // Initialize Leaflet map
    this.map = L.map(element, {
      center: [37.7749, -122.4194], // Default to San Francisco
      zoom: 12,
      scrollWheelZoom: true
    });

    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19
    }).addTo(this.map);

    // Load and display route if provided
    if (this.args.route) {
      this.displayRoute(this.args.route);
    }
  }

  /**
   * Display route on the map
   */
  displayRoute(route) {
    if (!this.map || !route.stops || route.stops.length === 0) return;

    const coordinates = [];
    const bounds = L.latLngBounds();

    // Clear existing markers and polyline
    this.clearMap();

    // Add markers for each stop
    route.stops.forEach((stop, index) => {
      if (stop.latitude && stop.longitude) {
        const latLng = [stop.latitude, stop.longitude];
        coordinates.push(latLng);
        bounds.extend(latLng);

        // Create custom icon based on stop type
        const icon = L.divIcon({
          html: `<div class="map-marker-number">${index + 1}</div>`,
          className: stop.is_pickup ? 'map-marker-pickup' : 'map-marker-dropoff',
          iconSize: [30, 30],
          iconAnchor: [15, 30]
        });

        const marker = L.marker(latLng, { icon })
          .bindPopup(`
            <div class="map-popup">
              <h4>${stop.name || `Stop ${index + 1}`}</h4>
              <p>${stop.address || ''}</p>
              <p><strong>Type:</strong> ${stop.is_pickup ? 'Pickup' : 'Dropoff'}</p>
              ${stop.scheduled_arrival_time ? `<p><strong>Time:</strong> ${stop.scheduled_arrival_time}</p>` : ''}
              ${stop.student_count ? `<p><strong>Students:</strong> ${stop.student_count}</p>` : ''}
            </div>
          `)
          .addTo(this.map);

        this.markers.push(marker);
      }
    });

    // Draw polyline connecting all stops
    if (coordinates.length > 1) {
      this.polyline = L.polyline(coordinates, {
        color: '#3b82f6',
        weight: 4,
        opacity: 0.7,
        smoothFactor: 1
      }).addTo(this.map);

      // Fit map to show all markers
      this.map.fitBounds(bounds, { padding: [50, 50] });
    } else if (coordinates.length === 1) {
      // Center on single marker
      this.map.setView(coordinates[0], 14);
    }
  }

  /**
   * Clear all markers and polylines from map
   */
  clearMap() {
    this.markers.forEach(marker => marker.remove());
    this.markers = [];
    
    if (this.polyline) {
      this.polyline.remove();
      this.polyline = null;
    }
  }

  /**
   * Cleanup when component is destroyed
   */
  willDestroy() {
    super.willDestroy(...arguments);
    this.clearMap();
    if (this.map) {
      this.map.remove();
      this.map = null;
    }
  }
}
