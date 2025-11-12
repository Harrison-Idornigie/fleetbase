import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';

export default class NotificationCenterComponent extends Component {
  @service schoolTransportApi;
  @service notifications;

  @tracked notificationsList = [];
  @tracked filters = [
    { value: 'all', label: 'All' },
    { value: 'alerts', label: 'Alerts' },
    { value: 'messages', label: 'Messages' },
    { value: 'system', label: 'System' }
  ];
  @tracked selectedFilter = { value: 'all', label: 'All' };

  constructor() {
    super(...arguments);
    this.loadNotifications();
  }

  async loadNotifications() {
    try {
      this.notificationsList = await this.schoolTransportApi.getNotifications();
    } catch (error) {
      console.error('Unable to load notifications', error);
    }
  }

  @action
  setFilter(filter) {
    this.selectedFilter = filter;
    // Implement filter behavior
    this.loadNotifications();
  }

  @action
  toggleRead(notification) {
    const updated = { ...notification, read: !notification.read };
    this.schoolTransportApi.updateNotification(notification.id, updated)
      .then(() => this.loadNotifications())
      .catch(err => console.error('Error toggling notification read state', err));
  }

  @action
  deleteNotification(notification) {
    this.schoolTransportApi.deleteNotification(notification.id)
      .then(() => this.loadNotifications())
      .catch(err => console.error('Error deleting notification', err));
  }

  @action
  refresh() {
    this.loadNotifications();
  }

  @action
  formatTimestamp(ts) {
    const d = new Date(ts);
    return d.toLocaleString();
  }
}
