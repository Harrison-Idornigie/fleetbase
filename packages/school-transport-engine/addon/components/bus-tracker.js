import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class BusTrackerComponent extends Component {
  @service realTimeTracking;
  @service schoolTransportApi;
  @service notifications;

  @tracked vehicles = [];
  @tracked activeVehicleIds = new Set();
  @tracked selectedVehicle = null;
  @tracked filterQuery = '';
  @tracked isMapReady = false;

  constructor() {
    super(...arguments);
    this.init();
  }

  async init() {
    // Load available vehicles
    try {
      const list = await this.schoolTransportApi.getVehicles();
      this.vehicles = list || [];

      // Hook real-time events
      this.realTimeTracking.on('location-update', this._onLocationUpdate.bind(this));
      this.realTimeTracking.on('disconnected', () => this.notifications.warning('Real-time tracking disconnected'));
      this.realTimeTracking.on('connected', () => this.notifications.success('Real-time tracking connected'));

      this.isMapReady = true; // Map lib can render now
    } catch (error) {
      console.error('Error loading vehicles:', error);
      this.notifications.error('Unable to load vehicles');
    }
  }

  willDestroy() {
    super.willDestroy(...arguments);
    // Clean up listeners
    this.realTimeTracking.off('location-update', this._onLocationUpdate.bind(this));
  }

  @action
  onInsertLiveMap(element) {
    // Called when LiveMap component is inserted; store element reference
    this.mapElement = element;
    // Optional: Trigger a resize if map is ready
    this.realTimeTracking.pingInterval && this.resize();
  }

  @action
  _onLocationUpdate(payload) {
    // payload contains { vehicleId, location, timestamp }
    const { vehicleId, location, timestamp } = payload;
    const vIdx = this.vehicles.findIndex(v => v.id === vehicleId);
    if (vIdx > -1) {
      this.vehicles[vIdx] = {
        ...this.vehicles[vIdx],
        last_update: timestamp,
        latitude: location.latitude,
        longitude: location.longitude,
        speed: location.speed,
        heading: location.heading
      };
      // Trigger tracked update
      this.vehicles = [...this.vehicles];
    }

    // Optionally center map if selected
    if (this.selectedVehicle === vehicleId) {
      // Center map and update marker
      this._emitMapEvent('map:addOrUpdateMarker', { id: vehicleId, latitude: location.latitude, longitude: location.longitude, popup: `${this.vehicles[vIdx]?.name || 'Vehicle'} - ${this.formatTimestamp(timestamp)}` });
      this._emitMapEvent('map:centerTo', { id: vehicleId });
    }
    // Always update marker if subscribed
    if (this.activeVehicleIds.has(vehicleId)) {
      this._emitMapEvent('map:addOrUpdateMarker', { id: vehicleId, latitude: location.latitude, longitude: location.longitude, popup: `${this.vehicles[vIdx]?.name || 'Vehicle'}` });
    }
  }

  @action
  subscribeVehicle(vehicleId) {
    this.realTimeTracking.startVehicleTracking(vehicleId);
    this.activeVehicleIds.add(vehicleId);
    this.activeVehicleIds = new Set(this.activeVehicleIds);
    this.notifications.info('Subscribed to vehicle updates');
  }

  @action
  unsubscribeVehicle(vehicleId) {
    this.realTimeTracking.stopVehicleTracking(vehicleId);
    this.activeVehicleIds.delete(vehicleId);
    this.activeVehicleIds = new Set(this.activeVehicleIds);
    this.notifications.info('Unsubscribed from vehicle updates');
  }

  @action
  selectVehicle(vehicle) {
    this.selectedVehicle = vehicle.id;
    this.args.onVehicleSelected?.(vehicle.id);

    // Ensure subscription
    if (!this.activeVehicleIds.has(vehicle.id)) {
      this.subscribeVehicle(vehicle.id);
    }
    // Attempt to center map immediately
    this._emitMapEvent('map:centerTo', { id: vehicle.id });
  }

  @action
  setFilter(e) {
    this.filterQuery = e.target.value;
  }

  @action
  refresh() {
    this.init();
  }

  get filteredVehicles() {
    if (!this.filterQuery) return this.vehicles;
    const q = this.filterQuery.toLowerCase();
    return this.vehicles.filter(v => (v.name || '').toLowerCase().includes(q) || (v.plate_number || '').toLowerCase().includes(q));
  }

  _centerMapToVehicle(vehicleId, location) {
    this._emitMapEvent('map:centerTo', { id: vehicleId });
  }

  _emitMapEvent(eventType, detail = {}) {
    const el = document.getElementById('live-map');
    if (el) {
      const ev = new CustomEvent(eventType, { detail });
      el.dispatchEvent(ev);
    }
  }

  @action
  formatTimestamp(ts) {
    try {
      const d = new Date(ts);
      return d.toLocaleTimeString();
    } catch (e) {
      return '—';
    }
  }
}
