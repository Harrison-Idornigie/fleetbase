import { module, test } from 'qunit';
import { setupTest } from 'ember-qunit';

module('Unit | Component | live-map', function(hooks) {
  setupTest(hooks);

  test('it exists', function(assert) {
    const component = this.owner.factoryFor('component:live-map');
    assert.ok(component);
  });
});
