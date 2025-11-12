import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class NotificationPreferencesComponent extends Component {
  @service schoolTransportApi;
  @service notifications;

  @tracked localPrefs = {
    email: true,
    sms: true,
    push: true,
    in_app: true,
    arrival: true,
    delay: true,
    cancellations: true,
    emergency: true
  };

  constructor() {
    super(...arguments);
    if (this.args.preferences) {
      this.localPrefs = { ...this.localPrefs, ...this.args.preferences };
    }
  }

  @action
  toggleChannel(channel) {
    this.localPrefs[channel] = !this.localPrefs[channel];
  }

  @action
  toggleEvent(event) {
    this.localPrefs[event] = !this.localPrefs[event];
  }

  @action
  reset(e) {
    e.preventDefault();
    this.localPrefs = {
      email: true,
      sms: true,
      push: true,
      in_app: true,
      arrival: true,
      delay: true,
      cancellations: true,
      emergency: true
    };
  }

  @action
  async save(e) {
    e.preventDefault();
    try {
      await this.schoolTransportApi.updateNotificationPreferences(this.localPrefs);
      this.notifications.success('Notification preferences saved');
      this.args.onSave?.(this.localPrefs);
    } catch (error) {
      console.error('Error saving preferences:', error);
      this.notifications.error('Unable to save preferences');
    }
  }
}
