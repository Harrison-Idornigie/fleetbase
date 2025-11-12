import { module, test } from 'qunit';
import { setupTest } from 'ember-qunit';
import { setupMirage } from 'ember-cli-mirage/test-support';

module('Integration | Student Workflow', function(hooks) {
  setupTest(hooks);
  setupMirage(hooks);

  test('can create a new student', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const studentData = {
      student_id: 'STU-001',
      first_name: 'John',
      last_name: 'Doe',
      grade: '5',
      school: 'Lincoln Elementary',
      date_of_birth: new Date('2014-05-15'),
      home_address: '123 Main St',
      parent_name: 'Jane Doe',
      parent_email: 'jane.doe@example.com',
      parent_phone: '555-1234',
      is_active: true
    };

    const student = store.createRecord('student', studentData);
    await student.save();

    assert.ok(student.id, 'Student has an ID after save');
    assert.equal(student.full_name, 'John Doe', 'Full name computed correctly');
    assert.ok(student.age >= 9 && student.age <= 11, 'Age calculated correctly');
  });

  test('can update student information', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const student = store.createRecord('student', {
      student_id: 'STU-002',
      first_name: 'Jane',
      last_name: 'Smith',
      grade: '3',
      school: 'Washington Elementary',
      home_address: '456 Oak Ave',
      parent_name: 'Bob Smith',
      parent_email: 'bob@example.com',
      parent_phone: '555-5678',
      is_active: true
    });

    await student.save();

    student.grade = '4';
    student.parent_phone = '555-9999';
    await student.save();

    assert.equal(student.grade, '4', 'Grade updated successfully');
    assert.equal(student.parent_phone, '555-9999', 'Phone updated successfully');
  });

  test('can assign student to route', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const student = store.createRecord('student', {
      student_id: 'STU-003',
      first_name: 'Alice',
      last_name: 'Johnson',
      grade: '2',
      school: 'Jefferson Elementary',
      home_address: '789 Elm St',
      parent_name: 'Carol Johnson',
      parent_email: 'carol@example.com',
      parent_phone: '555-1111',
      is_active: true
    });

    await student.save();

    const route = store.createRecord('school-route', {
      route_name: 'Route A',
      route_number: 'R-001',
      school: 'Jefferson Elementary',
      route_type: 'both',
      start_time: '07:00',
      end_time: '08:00',
      capacity: 50,
      is_active: true
    });

    await route.save();

    const assignment = store.createRecord('bus-assignment', {
      student: student,
      route: route,
      pickup_stop: 'Elm St & 1st Ave',
      dropoff_stop: 'School Main Entrance',
      effective_date: new Date(),
      status: 'active'
    });

    await assignment.save();

    assert.ok(assignment.id, 'Assignment created successfully');
    assert.equal(assignment.student.id, student.id, 'Student linked correctly');
    assert.equal(assignment.route.id, route.id, 'Route linked correctly');
  });

  test('validates required student fields', async function(assert) {
    const store = this.owner.lookup('service:store');
    const validationService = this.owner.lookup('service:validation');
    
    const invalidData = {
      first_name: '',
      last_name: 'Test',
      grade: '1'
    };

    const result = validationService.validateStudent(invalidData);

    assert.notOk(result.isValid, 'Validation fails for missing required fields');
    assert.ok(result.errors.firstName, 'First name error present');
  });

  test('can track student attendance', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const student = store.createRecord('student', {
      student_id: 'STU-004',
      first_name: 'Bob',
      last_name: 'Wilson',
      grade: '6',
      school: 'Madison Middle',
      home_address: '321 Pine St',
      parent_name: 'Mary Wilson',
      parent_email: 'mary@example.com',
      parent_phone: '555-2222',
      is_active: true
    });

    await student.save();

    const attendance = store.createRecord('attendance', {
      student: student,
      date: new Date(),
      present: true,
      event_type: 'pickup',
      status: 'completed'
    });

    await attendance.save();

    assert.ok(attendance.id, 'Attendance record created');
    assert.equal(attendance.present, true, 'Student marked present');
  });
});

