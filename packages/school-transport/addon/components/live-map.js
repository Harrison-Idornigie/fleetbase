import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

let L = null; // Leaflet

export default class LiveMapComponent extends Component {
  @tracked mapId = `map-${Math.random().toString(36).substr(2, 9)}`;
  @tracked loading = true;
  map = null;
  markers = new Map();

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

    this.map = L.map(el).setView([0, 0], 2);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
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
    if (this.map) {
      this.map.remove();
      this.map = null;
    }
    this.markers.clear();
  }
}
