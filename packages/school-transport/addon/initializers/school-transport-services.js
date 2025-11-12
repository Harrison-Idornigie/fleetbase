export function initialize(application) {
  // Register the school transport API service
  application.inject('route', 'schoolTransportApi', 'service:school-transport-api');
  application.inject('controller', 'schoolTransportApi', 'service:school-transport-api');
  application.inject('component', 'schoolTransportApi', 'service:school-transport-api');

  // Register validation service
  application.inject('route', 'validation', 'service:validation');
  application.inject('controller', 'validation', 'service:validation');
  application.inject('component', 'validation', 'service:validation');

  // Register real-time tracking service
  application.inject('route', 'realTimeTracking', 'service:real-time-tracking');
  application.inject('controller', 'realTimeTracking', 'service:real-time-tracking');
  application.inject('component', 'realTimeTracking', 'service:real-time-tracking');
}

export default {
  name: 'school-transport-services',
  initialize
};
