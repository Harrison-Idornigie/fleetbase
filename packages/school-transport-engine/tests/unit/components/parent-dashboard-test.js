import { module, test } from 'qunit';
import { setupTest } from 'ember-qunit';

module('Unit | Component | parent-dashboard', function(hooks) {
  setupTest(hooks);

  test('it exists', function(assert) {
    const component = this.owner.factoryFor('component:parent-dashboard');
    assert.ok(component);
  });
});
