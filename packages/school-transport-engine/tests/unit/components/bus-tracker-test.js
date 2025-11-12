import { module, test } from 'qunit';
import { setupTest } from 'ember-qunit';

module('Unit | Component | bus-tracker', function(hooks) {
  setupTest(hooks);

  test('it exists', function(assert) {
    const component = this.owner.factoryFor('component:bus-tracker');
    assert.ok(component);
  });
});
