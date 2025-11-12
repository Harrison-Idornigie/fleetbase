export default function(environment) {
  let ENV = {
    modulePrefix: 'school-transport-engine',
    environment,
    
    /**
     * School Transport Engine Configuration
     */
    'school-transport-engine': {
      apiNamespace: 'school-transport/api/v1',
      wsNamespace: 'school-transport/ws',
      enableRealTimeTracking: true,
      enableParentPortal: true,
      
      // Map provider configuration
      mapProvider: 'google',
      googleMapsApiKey: process.env.GOOGLE_MAPS_API_KEY || '',
      
      // Notification providers
      twilioAccountSid: process.env.TWILIO_ACCOUNT_SID || '',
      twilioAuthToken: process.env.TWILIO_AUTH_TOKEN || '',
      twilioPhoneNumber: process.env.TWILIO_PHONE_NUMBER || '',
      
      // Email configuration
      smtpHost: process.env.SMTP_HOST || '',
      smtpPort: process.env.SMTP_PORT || 587,
      smtpUser: process.env.SMTP_USER || '',
      smtpPassword: process.env.SMTP_PASSWORD || '',
      
      // Feature flags
      features: {
        studentManagement: true,
        routeOptimization: true,
        parentCommunication: true,
        safetyCompliance: true,
        reporting: true,
        mobileApp: false
      }
    }
  };

  return ENV;
}