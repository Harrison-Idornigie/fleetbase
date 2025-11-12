import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class RouteMapComponent extends Component {
  @service schoolTransportApi;

  mapId = `route-map-${Math.random().toString(36).substr(2, 9)}`;

  constructor() {
    super(...arguments);
    this.loadRoute();
  }

  async loadRoute() {
    if (!this.args.route) return;
    const route = this.args.route;
    // Build coordinates
    const coords = (route.stops || []).map(s => ({ latitude: s.latitude, longitude: s.longitude }));

    // Add markers for each stop
    (route.stops || []).forEach(stop => {
      const el = document.getElementById(this.mapId);
      if (!el) return;
      el.dispatchEvent(new CustomEvent('map:addOrUpdateMarker', { detail: { id: `stop-${stop.id}`, latitude: stop.latitude, longitude: stop.longitude, popup: `${stop.name} - ${stop.scheduled_time}` } }));
    });

    // Add polyline
    const el = document.getElementById(this.mapId);
    if (el && coords.length) {
      el.dispatchEvent(new CustomEvent('map:addPolyline', { detail: { id: `route-${route.id}`, coordinates: coords, options: { color: '#3b82f6' } } }));
    }
  }

  willDestroy() {
    super.willDestroy(...arguments);
    const el = document.getElementById(this.mapId);
    if (el) {
      el.dispatchEvent(new CustomEvent('map:removePolyline', { detail: { id: `route-${this.args.route?.id}` } }));
      (this.args.route?.stops || []).forEach(stop => {
        el.dispatchEvent(new CustomEvent('map:removeMarker', { detail: { id: `stop-${stop.id}` } }));
      });
    }
  }
}
