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
      this.route('templates');
    });
    
    this.route('dashboard');
    this.route('reports', function() {
      this.route('attendance');
      this.route('route-efficiency');
      this.route('safety-compliance');
    });
  });
});