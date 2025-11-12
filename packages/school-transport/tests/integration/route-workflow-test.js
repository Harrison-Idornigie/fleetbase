import { module, test } from 'qunit';
import { setupTest } from 'ember-qunit';
import { setupMirage } from 'ember-cli-mirage/test-support';

module('Integration | Route Workflow', function(hooks) {
  setupTest(hooks);
  setupMirage(hooks);

  test('can create a new route', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const routeData = {
      route_name: 'Morning Route A',
      route_number: 'R-001',
      description: 'Main pickup route for Lincoln Elementary',
      school: 'Lincoln Elementary',
      route_type: 'pickup',
      start_time: '07:00',
      end_time: '08:00',
      estimated_duration: 60,
      estimated_distance: 15.5,
      capacity: 72,
      wheelchair_accessible: true,
      is_active: true,
      status: 'active',
      days_of_week: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
      stops: [
        { name: 'Stop 1', address: '123 Main St', time: '07:10' },
        { name: 'Stop 2', address: '456 Oak Ave', time: '07:25' },
        { name: 'School', address: '789 School Rd', time: '07:50' }
      ]
    };

    const route = store.createRecord('school-route', routeData);
    await route.save();

    assert.ok(route.id, 'Route has an ID after save');
    assert.equal(route.route_name, 'Morning Route A', 'Route name set correctly');
    assert.equal(route.stops.length, 3, 'All stops saved');
  });

  test('calculates route utilization correctly', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const route = store.createRecord('school-route', {
      route_name: 'Test Route',
      route_number: 'R-TEST',
      school: 'Test School',
      route_type: 'both',
      start_time: '07:00',
      end_time: '08:00',
      capacity: 50,
      is_active: true
    });

    await route.save();

    // Create assignments to test utilization
    for (let i = 0; i < 40; i++) {
      const student = store.createRecord('student', {
        student_id: `STU-${i}`,
        first_name: `Student${i}`,
        last_name: 'Test',
        grade: '1',
        school: 'Test School',
        home_address: '123 Test St',
        parent_name: 'Parent Test',
        parent_email: 'parent@test.com',
        parent_phone: '555-0000',
        is_active: true
      });

      await student.save();

      const assignment = store.createRecord('bus-assignment', {
        student: student,
        route: route,
        status: 'active',
        effective_date: new Date()
      });

      await assignment.save();
    }

    // Reload route to get updated assignments
    await route.reload();

    assert.equal(route.assigned_students_count, 40, 'Correct number of students assigned');
    assert.equal(route.utilization_percentage, 80, 'Utilization calculated correctly');
    assert.equal(route.available_capacity, 10, 'Available capacity calculated correctly');
  });

  test('validates route data', async function(assert) {
    const validationService = this.owner.lookup('service:validation');
    
    const invalidRoute = {
      name: 'AB', // Too short
      routeNumber: '',
      type: '',
      startTime: '08:00',
      endTime: '07:00' // End before start
    };

    const result = validationService.validateRoute(invalidRoute);

    assert.notOk(result.isValid, 'Validation fails for invalid data');
    assert.ok(result.errors.name, 'Name validation error present');
    assert.ok(result.errors.routeNumber, 'Route number error present');
    assert.ok(result.errors.endTime, 'End time validation error present');
  });

  test('can optimize route stops', async function(assert) {
    const store = this.owner.lookup('service:store');
    const api = this.owner.lookup('service:school-transport-api');
    
    const route = store.createRecord('school-route', {
      route_name: 'Optimization Test',
      route_number: 'R-OPT',
      school: 'Test School',
      route_type: 'pickup',
      start_time: '07:00',
      end_time: '08:00',
      capacity: 50,
      is_active: true,
      stops: [
        { name: 'Stop A', coordinates: { lat: 40.7128, lng: -74.0060 } },
        { name: 'Stop B', coordinates: { lat: 40.7580, lng: -73.9855 } },
        { name: 'Stop C', coordinates: { lat: 40.7489, lng: -73.9680 } }
      ]
    });

    await route.save();

    // Mock API call for route optimization
    const optimizedRoute = await api.optimizeRoute(route.id);

    assert.ok(optimizedRoute, 'Optimization returns result');
    assert.ok(optimizedRoute.waypoints, 'Optimized waypoints provided');
  });

  test('checks route operates on specific days', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const route = store.createRecord('school-route', {
      route_name: 'Weekday Route',
      route_number: 'R-WD',
      school: 'Test School',
      route_type: 'both',
      start_time: '07:00',
      end_time: '08:00',
      capacity: 50,
      is_active: true,
      days_of_week: ['monday', 'wednesday', 'friday']
    });

    await route.save();

    assert.ok(route.operatesOnDay('monday'), 'Operates on Monday');
    assert.ok(route.operatesOnDay('wednesday'), 'Operates on Wednesday');
    assert.notOk(route.operatesOnDay('tuesday'), 'Does not operate on Tuesday');
    assert.notOk(route.operatesOnDay('saturday'), 'Does not operate on Saturday');
  });

  test('calculates route efficiency score', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const route = store.createRecord('school-route', {
      route_name: 'Efficient Route',
      route_number: 'R-EFF',
      school: 'Test School',
      route_type: 'both',
      start_time: '07:00',
      end_time: '08:00',
      capacity: 50,
      estimated_distance: 10,
      is_active: true
    });

    await route.save();

    // Add students to reach 80% utilization (optimal)
    for (let i = 0; i < 40; i++) {
      const student = store.createRecord('student', {
        student_id: `EFF-${i}`,
        first_name: `Student${i}`,
        last_name: 'Efficient',
        grade: '1',
        school: 'Test School',
        home_address: '123 Test St',
        parent_name: 'Parent',
        parent_email: 'parent@test.com',
        parent_phone: '555-0000',
        is_active: true
      });

      await student.save();

      const assignment = store.createRecord('bus-assignment', {
        student: student,
        route: route,
        status: 'active',
        effective_date: new Date()
      });

      await assignment.save();
    }

    await route.reload();

    const efficiencyScore = route.efficiency_score;
    assert.ok(efficiencyScore >= 0 && efficiencyScore <= 100, 'Efficiency score in valid range');
    assert.ok(efficiencyScore > 50, 'Route has good efficiency');
  });
});

