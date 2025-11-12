export default {
  /**
   * API Configuration
   */
  api: {
    namespace: 'school-transport/api/v1',
    timeout: 30000, // 30 seconds
    retryAttempts: 3,
    retryDelay: 1000
  },

  /**
   * WebSocket Configuration
   */
  websocket: {
    namespace: 'school-transport/ws',
    reconnectAttempts: 5,
    reconnectDelay: 3000,
    pingInterval: 30000
  },

  /**
   * Real-time Tracking Configuration
   */
  tracking: {
    enabled: true,
    updateInterval: 10000, // 10 seconds
    locationAccuracy: 'high',
    distanceUnit: 'km', // 'km' or 'mi'
    etaThreshold: 5 // minutes - when to send arrival notifications
  },

  /**
   * Parent Portal Configuration
   */
  parentPortal: {
    enabled: true,
    features: {
      trackBus: true,
      viewSchedule: true,
      receiveAlerts: true,
      viewAttendance: true,
      contactDriver: false,
      updateInfo: true
    }
  },

  /**
   * Notification Configuration
   */
  notifications: {
    email: {
      enabled: true,
      provider: 'smtp',
      from: 'noreply@schooltransport.com'
    },
    sms: {
      enabled: true,
      provider: 'twilio'
    },
    push: {
      enabled: true,
      provider: 'firebase'
    },
    types: {
      arrivalAlerts: true,
      delayNotifications: true,
      absenceReminders: true,
      routeChanges: true,
      emergencyAlerts: true
    }
  },

  /**
   * Attendance Tracking Configuration
   */
  attendance: {
    enabled: true,
    trackingMethods: ['manual', 'scan', 'gps'],
    requirePickupConfirmation: false,
    requireDropoffConfirmation: false,
    autoMarkAbsent: true,
    absentThresholdMinutes: 15
  },

  /**
   * Route Management Configuration
   */
  routes: {
    optimization: {
      enabled: true,
      algorithm: 'google', // 'google', 'mapbox', 'osrm'
      considerTraffic: true,
      maxStopsPerRoute: 30,
      maxRouteDistance: 50, // km
      maxRouteDuration: 120 // minutes
    },
    types: ['morning', 'afternoon', 'field-trip', 'special-needs', 'activity'],
    defaultCapacity: 50
  },

  /**
   * Safety & Compliance Configuration
   */
  safety: {
    driverCertification: {
      required: true,
      expiryWarningDays: 30,
      requiredDocuments: ['cdl', 'background-check', 'medical-clearance']
    },
    vehicleInspection: {
      required: true,
      frequency: 'monthly', // 'daily', 'weekly', 'monthly'
      expiryWarningDays: 7
    },
    incidentReporting: {
      enabled: true,
      requirePhotos: true,
      autoNotifyParents: true,
      severityLevels: ['minor', 'moderate', 'serious', 'critical']
    },
    emergencyProtocols: {
      enabled: true,
      contactEmergencyServices: true,
      notifySchoolAdmin: true,
      notifyParents: true
    }
  },

  /**
   * Student Management Configuration
   */
  students: {
    requiredFields: [
      'firstName',
      'lastName',
      'dateOfBirth',
      'grade',
      'homeAddress',
      'parentName',
      'parentEmail',
      'parentPhone',
      'emergencyContactName',
      'emergencyContactPhone'
    ],
    photoUpload: {
      enabled: true,
      maxSize: 5242880, // 5MB
      allowedFormats: ['image/jpeg', 'image/png']
    },
    specialNeeds: {
      enabled: true,
      trackAccommodations: true,
      requireApproval: true
    }
  },

  /**
   * Reporting Configuration
   */
  reporting: {
    enabled: true,
    types: [
      'attendance',
      'route-efficiency',
      'safety-compliance',
      'student-roster',
      'driver-performance',
      'vehicle-utilization'
    ],
    formats: ['pdf', 'csv', 'xlsx'],
    scheduling: {
      enabled: true,
      frequencies: ['daily', 'weekly', 'monthly', 'quarterly']
    }
  },

  /**
   * Map Configuration
   */
  map: {
    provider: 'google', // 'google', 'mapbox', 'openstreetmap'
    defaultZoom: 12,
    defaultCenter: { lat: 40.7128, lng: -74.0060 }, // Default to NYC
    clusterMarkers: true,
    showTraffic: true,
    show3DTerrain: false
  },

  /**
   * Feature Flags
   */
  features: {
    studentManagement: true,
    routeManagement: true,
    assignmentManagement: true,
    parentPortal: true,
    realTimeTracking: true,
    attendanceTracking: true,
    communication: true,
    reporting: true,
    safetyCompliance: true,
    analytics: true,
    mobileApp: false,
    integrations: {
      googleMaps: true,
      sms: true,
      email: true,
      sis: false // Student Information System
    }
  },

  /**
   * UI Configuration
   */
  ui: {
    theme: 'light',
    language: 'en',
    dateFormat: 'MM/DD/YYYY',
    timeFormat: '12h', // '12h' or '24h'
    timezone: 'America/New_York',
    itemsPerPage: 25,
    showWelcomeTour: true
  },

  /**
   * Performance Configuration
   */
  performance: {
    enableCaching: true,
    cacheTimeout: 300000, // 5 minutes
    lazyLoadImages: true,
    virtualScrolling: true
  },

  /**
   * Security Configuration
   */
  security: {
    sessionTimeout: 3600000, // 1 hour
    requireStrongPassword: true,
    twoFactorAuth: false,
    ipWhitelist: [],
    auditLogging: true
  }
};
