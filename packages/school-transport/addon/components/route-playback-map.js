import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class RoutePlaybackMapComponent extends Component {
  @service notifications;

  @tracked map = null;
  @tracked routePolyline = null;
  @tracked busMarker = null;
  @tracked studentMarkers = [];
  @tracked positionMarkers = [];

  get leaflet() {
    return window.L;
  }

  @action
  initializeMap(element) {
    if (!this.leaflet) {
      this.notifications.error('Map library not loaded');
      return;
    }

    // Initialize the map
    this.map = this.leaflet.map(element, {
      zoomControl: true,
      attributionControl: false
    });

    // Add tile layer
    this.leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(this.map);

    // Initial setup
    this.setupMap();
  }

  @action
  setupMap() {
    if (!this.map || !this.args.routeData) {
      return;
    }

    this.clearMap();
    this.drawRoute();
    this.addPositionMarkers();
    this.updateBusPosition();
    
    if (this.args.showStudentEvents) {
      this.addStudentMarkers();
    }

    this.fitMapToBounds();
  }

  @action
  clearMap() {
    if (this.routePolyline) {
      this.map.removeLayer(this.routePolyline);
      this.routePolyline = null;
    }

    if (this.busMarker) {
      this.map.removeLayer(this.busMarker);
      this.busMarker = null;
    }

    this.studentMarkers.forEach(marker => this.map.removeLayer(marker));
    this.studentMarkers = [];

    this.positionMarkers.forEach(marker => this.map.removeLayer(marker));
    this.positionMarkers = [];
  }

  @action
  drawRoute() {
    if (!this.args.routeData?.positions?.length) {
      return;
    }

    // Create polyline from positions
    const positions = this.args.routeData.positions.map(pos => [pos.latitude, pos.longitude]);
    
    this.routePolyline = this.leaflet.polyline(positions, {
      color: '#3b82f6',
      weight: 4,
      opacity: 0.7
    }).addTo(this.map);
  }

  @action
  addPositionMarkers() {
    if (!this.args.routeData?.positions?.length) {
      return;
    }

    // Add small markers for each position (optional, for detailed view)
    this.args.routeData.positions.forEach((position, index) => {
      const marker = this.leaflet.circleMarker([position.latitude, position.longitude], {
        radius: 3,
        fillColor: '#6b7280',
        color: '#374151',
        weight: 1,
        opacity: 0.5,
        fillOpacity: 0.3
      }).addTo(this.map);

      marker.on('click', () => {
        if (this.args.onPositionClick) {
          this.args.onPositionClick(index);
        }
      });

      marker.bindTooltip(`Position ${index + 1}<br>Time: ${new Date(position.created_at).toLocaleTimeString()}`);
      this.positionMarkers.push(marker);
    });
  }

  @action
  updateBusPosition() {
    if (!this.args.routeData?.positions?.length || this.args.currentPosition < 0) {
      return;
    }

    const currentPos = this.args.routeData.positions[this.args.currentPosition];
    if (!currentPos) {
      return;
    }

    // Remove existing bus marker
    if (this.busMarker) {
      this.map.removeLayer(this.busMarker);
    }

    // Create bus marker
    const busIcon = this.leaflet.divIcon({
      className: 'custom-bus-marker',
      html: `<div class="bus-marker">
        <div class="bus-icon">🚌</div>
        <div class="bus-pulse"></div>
      </div>`,
      iconSize: [40, 40],
      iconAnchor: [20, 20]
    });

    this.busMarker = this.leaflet.marker([currentPos.latitude, currentPos.longitude], {
      icon: busIcon,
      zIndexOffset: 1000
    }).addTo(this.map);

    // Add popup with current info
    const popupContent = `
      <div class="bus-popup">
        <h4>Bus Position</h4>
        <p><strong>Time:</strong> ${new Date(currentPos.created_at).toLocaleString()}</p>
        <p><strong>Speed:</strong> ${currentPos.speed || 0} mph</p>
        <p><strong>Heading:</strong> ${currentPos.heading || 0}°</p>
      </div>
    `;
    this.busMarker.bindPopup(popupContent);

    // Center map on bus if it's currently playing
    if (this.args.isPlaying) {
      this.map.setView([currentPos.latitude, currentPos.longitude], this.map.getZoom());
    }
  }

  @action
  addStudentMarkers() {
    if (!this.args.routeData?.student_events?.length) {
      return;
    }

    // Clear existing student markers
    this.studentMarkers.forEach(marker => this.map.removeLayer(marker));
    this.studentMarkers = [];

    // Add markers for student pickup/dropoff events
    this.args.routeData.student_events.forEach(event => {
      const isPickup = event.event_type === 'pickup';
      
      const icon = this.leaflet.divIcon({
        className: 'custom-student-marker',
        html: `<div class="student-marker ${isPickup ? 'pickup' : 'dropoff'}">
          <div class="student-icon">${isPickup ? '⬆️' : '⬇️'}</div>
        </div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
      });

      const marker = this.leaflet.marker([event.latitude, event.longitude], {
        icon: icon,
        zIndexOffset: 500
      }).addTo(this.map);

      const popupContent = `
        <div class="student-popup">
          <h4>${event.student.first_name} ${event.student.last_name}</h4>
          <p><strong>Event:</strong> ${isPickup ? 'Picked up' : 'Dropped off'}</p>
          <p><strong>Time:</strong> ${new Date(event.created_at).toLocaleTimeString()}</p>
          ${event.location_name ? `<p><strong>Location:</strong> ${event.location_name}</p>` : ''}
        </div>
      `;
      marker.bindPopup(popupContent);

      this.studentMarkers.push(marker);
    });
  }

  @action
  fitMapToBounds() {
    if (!this.routePolyline) {
      return;
    }

    const bounds = this.routePolyline.getBounds();
    if (bounds.isValid()) {
      this.map.fitBounds(bounds, { padding: [20, 20] });
    }
  }

  // Watch for prop changes
  @action
  didUpdateCurrentPosition() {
    this.updateBusPosition();
  }

  @action
  didUpdateShowStudentEvents() {
    if (this.args.showStudentEvents) {
      this.addStudentMarkers();
    } else {
      this.studentMarkers.forEach(marker => this.map.removeLayer(marker));
      this.studentMarkers = [];
    }
  }
}