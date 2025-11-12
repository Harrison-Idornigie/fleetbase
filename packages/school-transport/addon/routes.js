import buildRoutes from 'ember-engines/routes';

export default buildRoutes(function() {
  this.route('school-transport', function() {
    this.route('students', function() {
      this.route('index', { path: '/' });
      this.route('new');
      this.route('view', { path: '/:student_id' });
      this.route('edit', { path: '/:student_id/edit' });
      this.route('import');
    });
    
    this.route('routes', function() {
      this.route('index', { path: '/' });
      this.route('new');
      this.route('view', { path: '/:route_id' });
      this.route('edit', { path: '/:route_id/edit' });
      this.route('tracking', { path: '/:route_id/tracking' });
    });
    
    this.route('assignments', function() {
      this.route('index', { path: '/' });
      this.route('new');
      this.route('view', { path: '/:assignment_id' });
      this.route('edit', { path: '/:assignment_id/edit' });
      this.route('bulk-assign');
    });
    
    this.route('buses', function() {
      this.route('index', { path: '/' });
      this.route('new');
      this.route('view', { path: '/:bus_id' });
      this.route('edit', { path: '/:bus_id/edit' });
      this.route('maintenance', { path: '/:bus_id/maintenance' });
      this.route('fuel', { path: '/:bus_id/fuel' });
      this.route('route-playback', { path: '/:bus_id/route-playback' });
    });
    });
    
    this.route('communications', function() {
      this.route('index', { path: '/' });
      this.route('new');
      this.route('view', { path: '/:communication_id' });
      this.route('edit', { path: '/:communication_id/edit' });
      this.route('templates');
    });
    
    this.route('dashboard');
    this.route('parent-dashboard');
    
    this.route('reports', function() {
      this.route('index', { path: '/' });
      this.route('new');
      this.route('view', { path: '/:report_id' });
      this.route('attendance');
      this.route('route-efficiency');
      this.route('safety-compliance');
      this.route('custom');
    });
    
    this.route('safety', function() {
      this.route('index', { path: '/' });
      this.route('incidents', function() {
        this.route('index', { path: '/' });
        this.route('new');
        this.route('view', { path: '/:incident_id' });
      });
      this.route('inspections', function() {
        this.route('index', { path: '/' });
        this.route('new');
        this.route('view', { path: '/:inspection_id' });
      });
      this.route('certifications', function() {
        this.route('index', { path: '/' });
        this.route('view', { path: '/:certification_id' });
      });
    });
    
    // Settings extending FleetOps with school-specific configurations
    this.route('settings', function() {
      // Extend FleetOps routing settings with school-specific routing
      this.route('routing');
      // Extend FleetOps notifications with school-specific notifications  
      this.route('notifications');
      // School-specific settings not available in FleetOps
      this.route('school-hours');
      this.route('parent-portal');
      this.route('attendance-tracking');
      this.route('safety-compliance');
      this.route('emergency-contacts');
      this.route('pickup-dropoff-rules');
      this.route('student-permissions');
      this.route('reporting-preferences');
    });
  });
});