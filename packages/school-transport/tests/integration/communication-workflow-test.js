import { module, test } from 'qunit';
import { setupTest } from 'ember-qunit';
import { setupMirage } from 'ember-cli-mirage/test-support';

module('Integration | Communication Workflow', function(hooks) {
  setupTest(hooks);
  setupMirage(hooks);

  test('can create and send notification to parents', async function(assert) {
    const store = this.owner.lookup('service:store');
    const api = this.owner.lookup('service:school-transport-api');
    
    const communication = store.createRecord('communication', {
      type: 'notification',
      title: 'Bus Delay Alert',
      message: 'Route A will be delayed by 15 minutes due to traffic',
      recipients: ['all_parents'],
      delivery_channels: ['email', 'sms'],
      priority: 'high',
      status: 'draft'
    });

    await communication.save();

    assert.ok(communication.id, 'Communication created');
    assert.equal(communication.status, 'draft', 'Initial status is draft');

    // Send the communication
    const result = await api.sendCommunication(communication.id);

    assert.ok(result.success, 'Communication sent successfully');
  });

  test('validates communication data', async function(assert) {
    const validationService = this.owner.lookup('service:validation');
    
    const invalidComm = {
      subject: '', // Empty subject
      message: 'Short', // Too short
      type: '',
      recipientType: 'custom',
      customRecipients: '' // Required but empty
    };

    const result = validationService.validateCommunication(invalidComm);

    assert.notOk(result.isValid, 'Validation fails');
    assert.ok(result.errors.subject, 'Subject error present');
    assert.ok(result.errors.message, 'Message error present');
    assert.ok(result.errors.customRecipients, 'Custom recipients error present');
  });

  test('can schedule communication for later', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const futureDate = new Date();
    futureDate.setDate(futureDate.getDate() + 1);
    futureDate.setHours(8, 0, 0, 0);

    const communication = store.createRecord('communication', {
      type: 'reminder',
      title: 'School Event Reminder',
      message: 'Don\'t forget about the field trip tomorrow!',
      recipients: ['all_parents'],
      delivery_channels: ['email'],
      priority: 'normal',
      status: 'scheduled',
      scheduled_at: futureDate
    });

    await communication.save();

    assert.ok(communication.id, 'Scheduled communication created');
    assert.equal(communication.status, 'scheduled', 'Status is scheduled');
    assert.ok(communication.scheduled_at, 'Scheduled time set');
  });

  test('can use message templates', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const template = store.createRecord('communication-template', {
      name: 'Arrival Notification',
      subject: 'Bus Arriving Soon',
      body: 'The bus for {{route_name}} will arrive at {{stop_name}} in {{eta}} minutes.',
      type: 'notification',
      variables: ['route_name', 'stop_name', 'eta']
    });

    await template.save();

    assert.ok(template.id, 'Template created');
    assert.equal(template.variables.length, 3, 'All variables captured');

    // Use template to create communication
    const communication = store.createRecord('communication', {
      type: 'notification',
      title: template.subject,
      message: template.body,
      template_data: {
        route_name: 'Route A',
        stop_name: 'Main & 1st',
        eta: 5
      },
      recipients: ['parent-123'],
      delivery_channels: ['sms'],
      priority: 'normal'
    });

    await communication.save();

    assert.ok(communication.id, 'Communication from template created');
    assert.ok(communication.template_data, 'Template data stored');
  });

  test('tracks delivery status', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const communication = store.createRecord('communication', {
      type: 'alert',
      title: 'Emergency Alert',
      message: 'School closing early due to weather',
      recipients: ['parent-1', 'parent-2', 'parent-3'],
      delivery_channels: ['email', 'sms'],
      priority: 'urgent',
      status: 'sent',
      delivery_status: {
        'parent-1': { email: 'delivered', sms: 'delivered' },
        'parent-2': { email: 'delivered', sms: 'failed' },
        'parent-3': { email: 'pending', sms: 'delivered' }
      }
    });

    await communication.save();

    assert.ok(communication.id, 'Communication with delivery status created');
    assert.ok(communication.delivery_status, 'Delivery status tracked');
    assert.equal(Object.keys(communication.delivery_status).length, 3, 'All recipients tracked');
  });

  test('can send route-specific notifications', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const route = store.createRecord('school-route', {
      route_name: 'Route B',
      route_number: 'R-002',
      school: 'Test School',
      route_type: 'both',
      start_time: '07:00',
      end_time: '08:00',
      capacity: 50,
      is_active: true
    });

    await route.save();

    const communication = store.createRecord('communication', {
      type: 'update',
      title: 'Route Change Notification',
      message: 'Route B will have a temporary stop change starting Monday',
      route: route,
      recipients: ['route_parents'],
      delivery_channels: ['email'],
      priority: 'normal',
      status: 'draft'
    });

    await communication.save();

    assert.ok(communication.id, 'Route-specific communication created');
    assert.equal(communication.route.id, route.id, 'Route linked correctly');
  });

  test('validates SMS message length', async function(assert) {
    const validationService = this.owner.lookup('service:validation');
    
    const longMessage = 'A'.repeat(1700); // Exceeds SMS limit
    
    const smsComm = {
      subject: 'Test',
      message: longMessage,
      type: 'sms',
      recipientType: 'all_parents'
    };

    const result = validationService.validateCommunication(smsComm);

    assert.notOk(result.isValid, 'Validation fails for long SMS');
    assert.ok(result.errors.message, 'Message length error present');
  });

  test('can require parent acknowledgment', async function(assert) {
    const store = this.owner.lookup('service:store');
    
    const communication = store.createRecord('communication', {
      type: 'alert',
      title: 'Important Safety Update',
      message: 'Please acknowledge receipt of this safety information',
      recipients: ['all_parents'],
      delivery_channels: ['email'],
      priority: 'high',
      requires_acknowledgment: true,
      acknowledgments: {
        'parent-1': { acknowledged: true, timestamp: new Date() },
        'parent-2': { acknowledged: false, timestamp: null }
      }
    });

    await communication.save();

    assert.ok(communication.id, 'Communication with acknowledgment created');
    assert.ok(communication.requires_acknowledgment, 'Acknowledgment required');
    assert.ok(communication.acknowledgments, 'Acknowledgment tracking enabled');
  });
});

