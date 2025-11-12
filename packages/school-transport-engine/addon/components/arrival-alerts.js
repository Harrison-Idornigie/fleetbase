import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class ArrivalAlertsComponent extends Component {
  @service realTimeTracking;
  @service schoolTransportApi;
  @service notifications;

  @tracked upcomingStops = [];
  @tracked isSubscribed = false;

  constructor() {
    super(...arguments);
    this.init();
  }

  async init() {
    // Listen for ETA updates
    this.realTimeTracking.on('eta-update', this._onEtaUpdate.bind(this));

    // Optionally load initial ETAs for the selected student
    if (this.args.selectedStudent) {
      await this.loadInitialETAs(this.args.selectedStudent);
    }
  }

  willDestroy() {
    super.willDestroy(...arguments);
    this.realTimeTracking.off('eta-update', this._onEtaUpdate.bind(this));
  }

  async loadInitialETAs(student) {
    try {
      const etas = await this.schoolTransportApi.getStudentETAs(student.id);
      this.upcomingStops = etas || [];
    } catch (error) {
      console.error('Error loading ETAs:', error);
    }
  }

  @action
  async _onEtaUpdate(payload) {
    // payload: { routeId, stopId, eta, vehicleId }
    const { stopId, eta } = payload;

    // Find stop in current upcomingStops and update
    const idx = this.upcomingStops.findIndex(s => s.id === stopId);
    if (idx > -1) {
      this.upcomingStops[idx] = { ...this.upcomingStops[idx], eta };
      this.upcomingStops = [...this.upcomingStops];
    }

    // If an ETA is <= threshold, show a notification
    if (eta <= 5) {
      this.notifications.info(`Bus arriving at ${this.upcomingStops[idx]?.name} in ${eta} minutes`);
    }
  }

  @action
  async toggleSubscription() {
    if (!this.args.selectedStudent) return;

    try {
      if (this.isSubscribed) {
        await this.schoolTransportApi.unsubscribeArrivalAlerts(this.args.selectedStudent.id);
        this.notifications.info('Unsubscribed from arrival alerts');
      } else {
        await this.schoolTransportApi.subscribeArrivalAlerts(this.args.selectedStudent.id);
        this.notifications.success('Subscribed to arrival alerts');
      }
      this.isSubscribed = !this.isSubscribed;
    } catch (error) {
      console.error('Error toggling subscription:', error);
      this.notifications.error('Unable to change subscription');
    }
  }

  @action
  formatETA(minutes) {
    if (minutes === null || minutes === undefined) return 'TBD';
    if (minutes <= 0) return 'Arriving now';
    if (minutes < 60) return `${Math.round(minutes)} min`;
    const hours = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);
    return `${hours}h ${mins}m`;
  }

  @action
  formatETAWithDistance(distance) {
    return distance ? `${distance.toFixed(1)} km` : '';
  }
}
