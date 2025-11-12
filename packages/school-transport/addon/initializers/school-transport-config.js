import config from '../config/environment';

export function initialize(application) {
  const engineConfig = config['school-transport-engine'] || {};
  
  // Set default configuration
  const defaultConfig = {
    apiNamespace: 'school-transport/api/v1',
    wsNamespace: 'school-transport/ws',
    enableRealTimeTracking: true,
    enableParentPortal: true,
    defaultMapProvider: 'google',
    notificationSettings: {
      enableEmail: true,
      enableSMS: true,
      enablePush: true
    },
    attendanceTracking: {
      enabled: true,
      requirePickupScan: false,
      requireDropoffScan: false
    },
    safety: {
      requireDriverCertification: true,
      requireVehicleInspection: true,
      incidentReportingEnabled: true
    },
    features: {
      studentManagement: true,
      routeOptimization: true,
      parentCommunication: true,
      safetyCompliance: true,
      reporting: true
    }
  };

  // Merge with user config
  const mergedConfig = Object.assign({}, defaultConfig, engineConfig);

  // Register config in application
  application.register('config:school-transport-engine', mergedConfig, { 
    instantiate: false 
  });

  // Inject config into services
  application.inject('service', 'engineConfig', 'config:school-transport-engine');
  application.inject('route', 'engineConfig', 'config:school-transport-engine');
  application.inject('controller', 'engineConfig', 'config:school-transport-engine');
}

export default {
  name: 'school-transport-config',
  initialize
};
