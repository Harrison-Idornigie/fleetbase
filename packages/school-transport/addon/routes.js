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
  });
});